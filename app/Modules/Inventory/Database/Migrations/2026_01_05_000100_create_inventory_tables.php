<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('type', 20); // store | production | contractor
            // Stock at a contractor's location still belongs to the company.
            $table->foreignId('contractor_id')->nullable()->unique()->constrained('contractors');
            $table->string('system_key', 30)->nullable()->unique(); // main_store | production_floor
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A traceable batch with its own source and cost.
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_number', 30)->unique();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            // purchased | made | contractor_made | customer_return | rejection_recovery | opening | adjustment
            $table->string('origin', 30)->index();
            $table->nullableMorphs('source');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors');
            $table->date('received_date')->index();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // Genealogy: output lot <- input lot (production, processing, rework).
        Schema::create('lot_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('lots');
            $table->foreignId('source_lot_id')->constrained('lots');
            $table->decimal('quantity', 18, 4)->nullable();
            $table->nullableMorphs('document');
            $table->timestamps();

            $table->index(['source_lot_id', 'lot_id']);
        });

        // Append-only stock ledger. Never updated or deleted.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date')->index();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->string('status', 20); // available | in_production | rejected | returned
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('lot_id')->constrained('lots');
            $table->decimal('qty_in', 18, 4)->default(0);
            $table->decimal('qty_out', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->morphs('source');
            $table->unsignedBigInteger('source_line_id')->nullable();
            // Sales order line the stock is for (reserved or delivered). FK added by Sales.
            $table->unsignedBigInteger('sales_order_line_id')->nullable()->index();
            // Employee or contractor holding the stock (production issues).
            $table->nullableMorphs('holder');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_variant_id', 'status', 'location_id']);
            $table->index('lot_id');
        });

        // Current balances, kept in step with stock_movements in the same
        // transaction. Rebuildable: php artisan stock:rebuild-balances.
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            // "variant:stage:status:location:lot" so NULL stages stay unique.
            $table->string('bucket', 120)->unique();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->string('status', 20);
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('lot_id')->constrained('lots');
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['item_variant_id', 'status']);
        });

        // Quantity held for a demand (a sales order line). Does not move stock.
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->morphs('demand');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            // NULL = item-level reservation; set = this lot only.
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->decimal('quantity', 18, 4);
            // Quantity already issued/delivered against this reservation.
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->string('status', 20)->default('active')->index(); // active | released | consumed
            $table->string('release_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['item_variant_id', 'status']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('adjustment_date');
            $table->foreignId('reason_id')->nullable()->constrained('lookups');
            $table->text('notes')->nullable();
            $table->boolean('from_count')->default(false);
            $table->timestamps();
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->string('status', 20)->default('available');
            $table->foreignId('location_id')->constrained('locations');
            // Decrease: which lot (NULL = FIFO). Increase: existing lot, or NULL for a new Adjustment lot.
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->decimal('quantity', 18, 4); // + increase, − decrease
            $table->decimal('unit_cost', 18, 4)->nullable();
            // Counted quantity when the line came from a physical count.
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('system_quantity', 18, 4)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('lot_links');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('locations');
    }
};
