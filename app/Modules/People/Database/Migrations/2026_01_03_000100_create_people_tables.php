<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The real-world business or person behind one or more profiles.
        // A vendor that also takes contractor jobs has two profiles sharing one party.
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties');
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('company', 150)->nullable();
            // One currency per customer for its whole life (specification, Module 2).
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('country', 80)->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('ntn', 30)->nullable();
            $table->string('payment_terms', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties');
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('ntn', 30)->nullable();
            $table->string('payment_terms', 150)->nullable();
            // Over-receipt tolerance for this vendor; NULL = use the company setting.
            $table->decimal('over_receipt_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties');
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('father_name', 150)->nullable();
            $table->string('cnic', 20)->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('lookups');
            $table->foreignId('designation_id')->nullable()->constrained('lookups');
            // salary | wage: a fixed system choice, it changes payroll logic.
            $table->string('employee_type', 10);
            $table->date('join_date');
            $table->date('exit_date')->nullable();
            $table->decimal('monthly_salary', 18, 2)->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('lookups');
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_title', 150)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('iban', 34)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties');
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('contact_person', 150)->nullable();
            $table->string('cnic_ntn', 30)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->json('work_types')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('parties');
    }
};
