<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money paid out: to a vendor, contractor, employee or an expense head.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('payment_date')->index();
            $table->string('payee_kind', 15); // vendor | contractor | employee | expense
            $table->nullableMorphs('payee');
            $table->foreignId('expense_account_id')->nullable()->constrained('gl_accounts');
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->foreignId('payment_method_id')->nullable()->constrained('lookups');
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('allocated_amount', 18, 2)->default(0);
            // Allocations chosen while drafting: [{type, id, amount}]. Applied when posted.
            $table->json('planned_allocations')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Which bills / jobs / payrolls a payment settles. Unallocated = advance.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->morphs('allocatable');
            $table->decimal('amount', 18, 2);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
