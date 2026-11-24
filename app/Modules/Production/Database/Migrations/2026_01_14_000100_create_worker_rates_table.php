<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Individual piece rates: overrides the route stage's standard rate for one
        // employee or contractor, optionally for one item, from a date onwards.
        Schema::create('worker_rates', function (Blueprint $table) {
            $table->id();
            $table->string('worker_type', 20);
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors');
            $table->foreignId('stage_id')->constrained('stages');
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->decimal('rate', 18, 4);
            $table->date('effective_from');
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['worker_type', 'employee_id', 'stage_id', 'effective_from'], 'worker_rates_employee_lookup');
            $table->index(['worker_type', 'contractor_id', 'stage_id', 'effective_from'], 'worker_rates_contractor_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_rates');
    }
};
