<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->date('delivery_date')->index();
            $table->text('shipping_address')->nullable();
            $table->string('shipment_method', 50)->nullable();
            $table->string('vehicle', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries');
            $table->foreignId('sales_order_line_id')->constrained('sales_order_lines');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->string('source_status', 20)->default('available');
            $table->decimal('quantity', 18, 4);
            $table->decimal('returned_qty', 18, 4)->default(0);
            $table->foreignId('reservation_id')->nullable()->constrained('reservations');
            $table->string('over_reason', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The lots allocated to a delivery line (and dispatched from).
        Schema::create('delivery_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_line_id')->constrained('delivery_lines');
            $table->foreignId('lot_id')->constrained('lots');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->string('status', 20);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('packing_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained('deliveries');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cartons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_list_id')->constrained('packing_lists');
            $table->unsignedInteger('carton_number');
            $table->string('carton_type', 50)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('gross_weight', 10, 3)->nullable();
            $table->decimal('net_weight', 10, 3)->nullable();
            $table->string('marks', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['packing_list_id', 'carton_number']);
        });

        Schema::create('carton_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carton_id')->constrained('cartons')->cascadeOnDelete();
            $table->foreignId('delivery_line_id')->constrained('delivery_lines');
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('payment_terms', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices');
            // A delivery line is invoiced once (cancelled invoices free it again).
            $table->foreignId('delivery_line_id')->constrained('delivery_lines');
            $table->foreignId('sales_order_line_id')->constrained('sales_order_lines');
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('order_price', 18, 4);
            $table->decimal('discount', 18, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('amount', 18, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // What reduced an invoice's balance: credit notes now, receipts later.
        Schema::create('invoice_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->morphs('source');
            $table->date('allocation_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('delivery_returns', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('delivery_line_id')->constrained('delivery_lines');
            $table->foreignId('invoice_line_id')->nullable()->constrained('invoice_lines');
            $table->foreignId('lot_id')->constrained('lots');
            $table->decimal('quantity', 18, 4);
            $table->string('reason', 500);
            $table->date('return_date')->index();
            $table->boolean('replacement_required')->default(false);
            $table->text('notes')->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('restocked_qty', 18, 4)->default(0);
            $table->decimal('rejected_qty', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('delivery_return_id')->nullable()->constrained('delivery_returns');
            $table->date('credit_date')->index();
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes');
            $table->string('description', 255);
            $table->decimal('amount', 18, 2);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('source_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex(['source_line_id']));
        foreach (['credit_note_lines', 'credit_notes', 'delivery_returns', 'invoice_allocations', 'invoice_lines', 'invoices', 'carton_lines', 'cartons', 'packing_lists', 'delivery_allocations', 'delivery_lines', 'deliveries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
