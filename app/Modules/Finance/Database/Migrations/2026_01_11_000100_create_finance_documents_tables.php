<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer receipts: amount cleared from the customer = deductions + net received. No exchange rate.
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('receipt_date')->index();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('amount', 18, 2);
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->decimal('net_received', 18, 2);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('planned_allocations')->nullable();
            $table->decimal('allocated_amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('receipt_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained('gl_accounts');
            $table->decimal('amount', 18, 2);
            $table->string('memo', 255)->nullable();
            $table->timestamps();
        });

        // Advance (unallocated receipt) later set against an invoice.
        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts');
            $table->morphs('receivable');
            $table->date('allocation_date');
            $table->decimal('amount', 18, 2);
            $table->boolean('from_advance')->default(false);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('currency_conversions', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('conversion_date')->index();
            $table->foreignId('from_account_id')->constrained('money_accounts');
            $table->decimal('from_amount', 18, 2);
            $table->foreignId('to_account_id')->constrained('money_accounts');
            $table->decimal('pkr_received', 18, 2);
            $table->decimal('deductions', 18, 2)->nullable();
            $table->foreignId('deduction_account_id')->nullable()->constrained('gl_accounts');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('transfer_date')->index();
            $table->foreignId('from_account_id')->constrained('money_accounts');
            $table->foreignId('to_account_id')->constrained('money_accounts');
            $table->decimal('amount', 18, 2);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('expense_date')->index();
            $table->foreignId('expense_account_id')->constrained('gl_accounts');
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts'); // paid now
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');               // or payable to a vendor
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('voucher_date')->index();
            $table->string('currency_code', 3)->default('PKR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->string('description', 500);
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->timestamps();
        });

        Schema::create('journal_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_voucher_id')->constrained('journal_vouchers')->cascadeOnDelete();
            $table->foreignId('gl_account_id')->nullable()->constrained('gl_accounts');
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts');
            $table->nullableMorphs('party');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('memo', 255)->nullable();
            // A fine charged to an employee that payroll deducts.
            $table->boolean('deduct_from_payroll')->default(false);
            $table->unsignedBigInteger('payroll_line_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Record-only asset register: no accounts, no stock.
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('category', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('value', 18, 2)->nullable();
            $table->string('location', 150)->nullable();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees');
            $table->string('serial_number', 100)->nullable();
            $table->string('status', 20)->default('in_use');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['assets', 'journal_voucher_lines', 'journal_vouchers', 'expenses', 'transfers', 'currency_conversions', 'receipt_allocations', 'receipt_deductions', 'receipts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
