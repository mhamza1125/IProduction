<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monthly attendance summary, entered by hand (no machine export).
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('month'); // first day of the month
            $table->decimal('working_days', 5, 2)->nullable();
            $table->decimal('days_off', 5, 2)->nullable();
            $table->decimal('paid_leaves', 5, 2)->nullable();
            $table->decimal('unpaid_leaves', 5, 2)->nullable();
            $table->decimal('late_hours', 8, 2)->nullable();
            $table->decimal('overtime_hours', 8, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'month']);
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('loan_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->decimal('installment', 18, 2);
            $table->date('start_month');
            $table->decimal('recovered_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Reduce, skip (0) or increase one month's installment, with a reason.
        Schema::create('loan_installment_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans');
            $table->date('month');
            $table->decimal('amount', 18, 2);
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['employee_loan_id', 'month']);
        });

        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('advance_date');
            $table->decimal('amount', 18, 2);
            $table->date('salary_month');
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->decimal('recovered_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('month')->index();
            $table->foreignId('department_id')->nullable()->constrained('lookups');
            $table->string('employee_type', 10)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_net', 18, 2)->default(0);
            $table->decimal('total_paid', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs');
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('month');
            $table->string('employee_type', 10);
            $table->decimal('monthly_salary', 18, 2)->nullable();
            $table->decimal('earnings', 18, 2)->default(0);
            $table->decimal('overtime', 18, 2)->default(0);
            $table->decimal('allowances', 18, 2)->default(0);
            $table->decimal('attendance_deduction', 18, 2)->default(0);
            $table->decimal('calculated_attendance_deduction', 18, 2)->default(0);
            $table->string('deduction_override_reason', 500)->nullable();
            $table->decimal('fines', 18, 2)->default(0);
            $table->decimal('loan_installment', 18, 2)->default(0);
            $table->decimal('advance_deduction', 18, 2)->default(0);
            $table->decimal('brought_forward', 18, 2)->default(0);
            $table->decimal('net_payable', 18, 2)->default(0);
            $table->decimal('carried_forward', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            // "employee-yyyy-mm" once posted: one posted payroll per employee and month.
            $table->string('posted_key', 40)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('payroll_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained('payroll_lines')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('description', 255);
            $table->decimal('amount', 18, 2); // + earning, − deduction
            $table->nullableMorphs('source');
            $table->timestamps();
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans');
            $table->foreignId('payroll_line_id')->constrained('payroll_lines');
            $table->date('month');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['loan_installments', 'payroll_line_items', 'payroll_lines', 'payroll_runs', 'salary_advances', 'loan_installment_changes', 'employee_loans', 'attendance_summaries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
