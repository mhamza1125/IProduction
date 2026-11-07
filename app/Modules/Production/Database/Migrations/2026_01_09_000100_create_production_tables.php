<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptcs', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->decimal('planned_qty', 18, 4);
            $table->foreignId('route_id')->constrained('production_routes');
            $table->foreignId('bom_id')->nullable()->constrained('boms');
            $table->foreignId('start_stage_id')->nullable()->constrained('stages');
            $table->foreignId('end_stage_id')->nullable()->constrained('stages');
            $table->foreignId('parent_id')->nullable()->constrained('ptcs');
            $table->decimal('parent_qty', 18, 4)->nullable();
            $table->boolean('required_by_parent')->default(true);
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees');
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('output_lot_id')->nullable()->constrained('lots');
            $table->decimal('released_qty', 18, 4)->default(0);
            $table->decimal('merged_out_qty', 18, 4)->default(0);
            $table->string('close_reason', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['item_variant_id', 'status']);
        });

        // Route copy taken at approval.
        Schema::create('ptc_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ptc_id')->constrained('ptcs');
            $table->foreignId('stage_id')->constrained('stages');
            $table->unsignedInteger('sequence');
            $table->boolean('is_optional')->default(false);
            $table->string('work_type', 12)->default('employee');
            $table->decimal('wage_rate', 18, 4)->nullable();
            $table->decimal('contractor_rate', 18, 4)->nullable();
            $table->string('machine', 100)->nullable();
            $table->decimal('issued_qty', 18, 4)->default(0);
            $table->decimal('received_qty', 18, 4)->default(0);
            $table->decimal('rejected_qty', 18, 4)->default(0);
            $table->boolean('is_skipped')->default(false);
            $table->string('skip_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['ptc_id', 'sequence']);
        });

        // BOM copy taken at approval: material per output unit, wastage included, in stock units.
        Schema::create('ptc_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ptc_id')->constrained('ptcs');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->decimal('per_unit', 18, 6);
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();
        });

        Schema::create('ptc_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ptc_id')->constrained('ptcs');
            $table->foreignId('ptc_stage_id')->nullable()->constrained('ptc_stages');
            $table->string('type', 20)->index();
            $table->date('transaction_date')->index();
            $table->string('worker_type', 12)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->foreignId('related_ptc_id')->nullable()->constrained('ptcs');
            $table->string('reason', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        // Signed PKR cost entries; their sum is the cost not yet released.
        Schema::create('ptc_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ptc_id')->constrained('ptcs');
            $table->string('category', 20)->index();
            $table->decimal('amount', 18, 2);
            $table->nullableMorphs('source');
            $table->string('memo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ptc_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ptc_id')->constrained('ptcs');
            $table->foreignId('source_ptc_id')->constrained('ptcs');
            $table->string('type', 10); // child | merge | split
            $table->decimal('quantity', 18, 4);
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('wage_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('ptc_id')->nullable()->constrained('ptcs');
            $table->foreignId('ptc_stage_id')->nullable()->constrained('ptc_stages');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->foreignId('ptc_transaction_id')->nullable()->constrained('ptc_transactions');
            $table->date('work_date')->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('rejected_qty', 18, 4)->default(0);
            $table->decimal('paid_rejected_qty', 18, 4)->default(0);
            $table->decimal('rate', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->string('status', 12)->default('pending')->index(); // pending | approved | rejected
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->unsignedBigInteger('payroll_line_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_jobs', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('contractor_id')->constrained('contractors');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines');
            $table->date('job_date');
            $table->date('due_date')->nullable();
            $table->string('payment_basis', 20)->default('per_piece');
            $table->text('notes')->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_job_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_job_id')->constrained('contractor_jobs');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->decimal('quantity', 18, 4);
            $table->decimal('rate', 18, 4);
            $table->decimal('received_qty', 18, 4)->default(0);
            $table->decimal('accepted_qty', 18, 4)->default(0);
            $table->decimal('rejected_qty', 18, 4)->default(0);
            $table->decimal('paid_qty', 18, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Expected material use per job.
        Schema::create('contractor_job_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_job_id')->constrained('contractor_jobs');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->decimal('expected_qty', 18, 4);
            $table->timestamps();
        });

        Schema::create('contractor_job_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_job_id')->constrained('contractor_jobs');
            $table->foreignId('contractor_job_line_id')->nullable()->constrained('contractor_job_lines');
            $table->string('type', 20)->index(); // issue | return | wastage | consume | receipt
            $table->date('transaction_date');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->decimal('quantity', 18, 4);
            $table->decimal('accepted_qty', 18, 4)->nullable();
            $table->decimal('rejected_qty', 18, 4)->nullable();
            $table->decimal('paid_qty', 18, 4)->nullable();
            $table->string('over_receipt_action', 20)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('material_processes', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('process_date');
            $table->string('process_name', 150);
            $table->string('worker_type', 12)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines');
            $table->foreignId('ptc_id')->nullable()->constrained('ptcs');
            $table->decimal('labor_cost', 18, 2)->nullable();
            $table->decimal('recoverable_value', 18, 2)->nullable();
            $table->decimal('input_cost', 18, 2)->nullable();
            $table->decimal('output_unit_cost', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('material_process_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_process_id')->constrained('material_processes');
            $table->string('kind', 10); // input | output | loss
            $table->foreignId('item_variant_id')->nullable()->constrained('item_variants');
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->decimal('expected_qty', 18, 4)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('loss_type', 10)->nullable(); // wastage | rejected
            $table->string('reason', 255)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rejections', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('rejection_date')->index();
            $table->nullableMorphs('source');
            $table->foreignId('ptc_id')->nullable()->constrained('ptcs');
            $table->foreignId('contractor_job_id')->nullable()->constrained('contractor_jobs');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->foreignId('lot_id')->constrained('lots');
            $table->foreignId('location_id')->constrained('locations');
            $table->decimal('quantity', 18, 4);
            $table->decimal('decided_qty', 18, 4)->default(0);
            $table->string('reason', 500);
            $table->string('found_by', 150)->nullable();
            $table->nullableMorphs('responsible');
            $table->text('notes')->nullable();
            $table->string('status', 12)->default('open')->index(); // open | decided
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('rejection_decisions', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('rejection_id')->constrained('rejections');
            $table->date('decision_date');
            $table->string('decision', 10); // rework | scrap | sell | recover
            $table->decimal('quantity', 18, 4)->nullable();
            $table->foreignId('target_ptc_id')->nullable()->constrained('ptcs');
            $table->foreignId('target_stage_id')->nullable()->constrained('stages');
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('reason', 500);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['rejection_decisions', 'rejections', 'material_process_lines', 'material_processes', 'contractor_job_transactions', 'contractor_job_materials', 'contractor_job_lines', 'contractor_jobs', 'wage_work', 'ptc_links', 'ptc_costs', 'ptc_transactions', 'ptc_materials', 'ptc_stages', 'ptcs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
