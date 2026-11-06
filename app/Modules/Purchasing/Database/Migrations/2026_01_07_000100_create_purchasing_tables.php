<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A commitment to buy. It never changes stock or the vendor balance itself.
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->date('order_date')->index();
            $table->date('expected_date')->nullable();
            $table->string('payment_terms', 150)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            // Part-made goods enter stock at this stage.
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained('units');
            // 1 order unit = unit_factor stock units (snapshot when ordered).
            $table->decimal('unit_factor', 18, 6)->default(1);
            $table->decimal('rate', 18, 4); // PKR per order unit
            $table->decimal('amount', 18, 2);
            $table->date('expected_date')->nullable();
            // Progress, in order units, maintained by posted receipts, returns and bills.
            $table->decimal('received_qty', 18, 4)->default(0);
            $table->decimal('accepted_qty', 18, 4)->default(0);
            $table->decimal('rejected_qty', 18, 4)->default(0);
            $table->decimal('returned_qty', 18, 4)->default(0);
            $table->decimal('billed_qty', 18, 4)->default(0);
            $table->decimal('closed_short_qty', 18, 4)->default(0);
            $table->string('close_reason', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->date('receipt_date')->index();
            $table->string('vendor_delivery_note', 60)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->decimal('total_value', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            // Snapshots when the receipt was made (order units).
            $table->decimal('ordered_qty', 18, 4);
            $table->decimal('previously_accepted_qty', 18, 4);
            // received = accepted + rejected (order units)
            $table->decimal('received_qty', 18, 4);
            $table->decimal('accepted_qty', 18, 4);
            $table->decimal('rejected_qty', 18, 4)->default(0);
            // return: rejected goods go back and never enter stock; keep: company keeps them in Rejected status.
            $table->string('rejected_action', 10)->default('return');
            $table->foreignId('reject_reason_id')->nullable()->constrained('lookups');
            // Over-receipt beyond tolerance: accept_pay | accept_free | return_extra
            $table->string('over_receipt_action', 15)->nullable();
            $table->decimal('extra_qty', 18, 4)->default(0);
            $table->decimal('stock_qty', 18, 4)->default(0);   // accepted into Available (stock units)
            $table->decimal('payable_qty', 18, 4)->default(0); // charged by the vendor (order units)
            $table->decimal('rate', 18, 4);                     // PKR per order unit (from the PO)
            $table->decimal('unit_cost', 18, 4)->nullable();    // PKR per stock unit, for the lot
            $table->decimal('value', 18, 2)->default(0);        // payable value posted to Inventory / AP
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->foreignId('lot_id')->nullable()->constrained('lots');
            $table->decimal('billed_qty', 18, 4)->default(0);
            $table->decimal('returned_qty', 18, 4)->default(0); // stock units returned later
            $table->timestamps();
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_invoice_number', 60)->nullable();
            $table->date('bill_date')->index();
            $table->date('due_date')->nullable();
            $table->decimal('goods_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('charges_amount', 18, 2)->default(0);
            $table->decimal('deductions_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'vendor_invoice_number']);
        });

        Schema::create('vendor_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->decimal('quantity', 18, 4); // order units
            $table->decimal('rate', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('goods_receipt_id')->constrained();
            $table->date('return_date')->index();
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            // Replacement wanted: the returned quantity becomes outstanding on the order again.
            $table->boolean('replacement_required')->default(false);
            $table->foreignId('replacement_receipt_id')->nullable()->constrained('goods_receipts');
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained();
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('lot_id')->constrained('lots');
            $table->string('status', 20)->default('available'); // available | rejected
            $table->foreignId('location_id')->constrained('locations');
            $table->decimal('quantity', 18, 4); // stock units
            $table->decimal('rate', 18, 4);      // PKR per stock unit (lot cost)
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['purchase_return_lines', 'purchase_returns', 'vendor_bill_lines', 'vendor_bills', 'goods_receipt_lines', 'goods_receipts', 'purchase_order_lines', 'purchase_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
