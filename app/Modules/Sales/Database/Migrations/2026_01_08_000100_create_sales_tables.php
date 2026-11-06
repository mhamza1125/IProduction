<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The customer's order: the single source of ordered quantity and price.
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('customer_id')->constrained('customers');
            $table->date('order_date')->index();
            $table->date('due_date')->nullable()->index();
            // Always the customer's currency.
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->string('customer_po_number', 60)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('payment_terms', 150)->nullable();
            $table->text('notes')->nullable();
            // Sale of rejected stock (Module 8): delivered from Rejected status.
            $table->boolean('sells_rejected_stock')->default(false);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 18, 4);           // stock units
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('unit_price', 18, 4);          // customer currency per stock unit
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('line_total', 18, 2);
            $table->date('due_date')->nullable();
            // Planning split of the quantity not covered by stock.
            $table->decimal('plan_buy', 18, 4)->default(0);
            $table->decimal('plan_make', 18, 4)->default(0);
            $table->decimal('plan_contractor', 18, 4)->default(0);
            $table->foreignId('route_id')->nullable()->constrained('production_routes');
            // Progress, maintained by deliveries, invoices and returns.
            $table->decimal('delivered_qty', 18, 4)->default(0);
            $table->decimal('invoiced_qty', 18, 4)->default(0);
            $table->decimal('returned_qty', 18, 4)->default(0);
            $table->decimal('closed_qty', 18, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('sales_order_line_id')->references('id')->on('sales_order_lines');
        });

        // Links supply (a PO line, PTC or contractor job line) to demand (a sales order line).
        // One supply can serve several order lines and one order line several supplies.
        Schema::create('supply_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_line_id')->constrained();
            $table->morphs('supply');
            // What the supply provides: the ordered product, or a component for making it.
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->decimal('quantity', 18, 4); // stock units
            $table->decimal('fulfilled_quantity', 18, 4)->default(0);
            $table->string('status', 15)->default('open'); // open | fulfilled | closed
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_allocations');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropForeign(['sales_order_line_id']));
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }
};
