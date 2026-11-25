<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additional purchase charges (freight, delivery, customs duty, clearing,
        // insurance) added to the cost of the goods they were paid for.
        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('cost_date')->index();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');           // payable to (transporter, agent)
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts'); // or paid now
            $table->string('method', 10)->default('value');                              // value | quantity
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->timestamps();
        });

        Schema::create('landed_cost_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_id')->constrained()->cascadeOnDelete();
            $table->string('charge_type', 20);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('landed_cost_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts');
            $table->unique(['landed_cost_id', 'goods_receipt_id']);
        });

        // Worked out when posting: what each receipt line's lot absorbed.
        Schema::create('landed_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('goods_receipt_lines');
            $table->foreignId('lot_id')->constrained('lots');
            $table->decimal('basis', 18, 4);               // line value or lot quantity
            $table->decimal('lot_qty', 18, 4);             // received into the lot
            $table->decimal('on_hand_qty', 18, 4);         // still in stock when posted
            $table->decimal('amount', 18, 2);              // share of the charges
            $table->decimal('to_stock', 18, 2);            // onto stock on hand (Inventory)
            $table->decimal('to_production', 18, 2)->default(0); // of which held by PTCs (added to their cost)
            $table->decimal('to_cogs', 18, 2);             // for quantity already used or sold
            $table->decimal('unit_cost_before', 18, 4)->nullable();
            $table->decimal('unit_cost_after', 18, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_allocations');
        Schema::dropIfExists('landed_cost_receipts');
        Schema::dropIfExists('landed_cost_charges');
        Schema::dropIfExists('landed_costs');
    }
};
