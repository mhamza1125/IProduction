<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cash and bank accounts. Each has its own GL account; foreign-currency
        // accounts keep their balance in that currency.
        Schema::create('money_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 10); // cash | bank
            $table->string('bank_name', 100)->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('account_title', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('iban', 34)->nullable();
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->foreignId('gl_account_id')->unique()->constrained('gl_accounts');
            $table->boolean('allow_overdraft')->default(false);
            // cash_book: the permanent Cash Book / Petty Cash.
            $table->string('system_key', 30)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // A balanced set of lines in ONE currency. Created only by JournalPoster;
        // never edited or deleted. Mistakes are fixed with a reversal journal.
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('journal_date')->index();
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->string('description', 500);
            $table->nullableMorphs('source');
            $table->foreignId('reverses_journal_id')->nullable()->constrained('journals');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals');
            $table->date('line_date')->index();
            $table->foreignId('gl_account_id')->constrained('gl_accounts');
            $table->char('currency_code', 3);
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            // Who the line is about: customer / vendor / employee / contractor profile, and its party.
            $table->nullableMorphs('ledgerable');
            $table->foreignId('party_id')->nullable()->constrained('parties');
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts');
            $table->string('memo', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['gl_account_id', 'line_date']);
            $table->index(['ledgerable_type', 'ledgerable_id', 'line_date']);
            $table->index(['money_account_id', 'line_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('money_accounts');
    }
};
