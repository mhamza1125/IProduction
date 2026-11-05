<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->foreignId('category_id')->nullable()->constrained('lookups');
            // raw_material | semi_finished | finished_good
            $table->string('type', 20)->index();
            $table->text('description')->nullable();
            $table->foreignId('stock_unit_id')->constrained('units');
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units');
            $table->foreignId('sales_unit_id')->nullable()->constrained('units');
            $table->decimal('default_selling_price', 18, 4)->nullable();
            $table->decimal('default_purchase_cost', 18, 4)->nullable();
            $table->decimal('min_stock', 18, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('tax_notes', 255)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('can_buy')->default(false);
            $table->boolean('can_make')->default(false);
            $table->boolean('can_sell')->default(false);
            $table->boolean('track_by_lot')->default(true);
            // Over-receipt tolerance for this item; NULL = vendor or company setting.
            $table->decimal('over_receipt_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('unit_conversions', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });

        // Which attributes an item varies by (e.g. Glove: Color, Size).
        Schema::create('item_attributes', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['item_id', 'attribute_id']);
        });

        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();
            $table->string('sku', 60)->unique();
            $table->string('barcode', 60)->nullable()->unique();
            // Sorted attribute value ids, e.g. "3-12"; '' for the plain variant.
            $table->string('combination_key', 191)->default('');
            $table->string('label', 191)->default('');
            $table->decimal('price_override', 18, 4)->nullable();
            $table->decimal('cost_override', 18, 4)->nullable();
            $table->decimal('min_stock', 18, 4)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['item_id', 'combination_key']);
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained();
            $table->primary(['item_variant_id', 'attribute_value_id']);
        });

        Schema::create('production_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('route_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('production_routes')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained();
            $table->unsignedInteger('sequence');
            $table->boolean('is_optional')->default(false);
            $table->string('default_work_type', 12)->default('employee'); // employee | contractor
            $table->decimal('wage_rate', 18, 4)->nullable();
            $table->decimal('contractor_rate', 18, 4)->nullable();
            $table->string('machine', 100)->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'sequence']);
        });

        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('version');
            $table->date('effective_date');
            // The BOM describes the components for this many output units.
            $table->decimal('output_quantity', 18, 4)->default(1);
            $table->string('status', 20)->default('draft'); // draft | approved | superseded
            $table->boolean('is_active')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['item_id', 'version']);
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items');
            // NULL = the component item's only variant, or chosen by an override.
            $table->foreignId('component_variant_id')->nullable()->constrained('item_variants');
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->boolean('is_optional')->default(false);
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Variant-specific changes: "more leather for Large", "red thread for Red".
        Schema::create('bom_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            // Which parent variants it applies to: one attribute value OR one variant.
            $table->foreignId('match_attribute_value_id')->nullable()->constrained('attribute_values');
            $table->foreignId('match_variant_id')->nullable()->constrained('item_variants');
            // The line it changes; NULL adds an extra component.
            $table->foreignId('bom_line_id')->nullable()->constrained('bom_lines')->cascadeOnDelete();
            $table->foreignId('component_item_id')->nullable()->constrained('items');
            $table->foreignId('component_variant_id')->nullable()->constrained('item_variants');
            $table->decimal('quantity', 18, 4)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->decimal('wastage_percent', 5, 2)->nullable();
            $table->foreignId('stage_id')->nullable()->constrained('stages');
            $table->boolean('removes_line')->default(false);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_overrides');
        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('boms');
        Schema::dropIfExists('route_stages');
        Schema::dropIfExists('production_routes');
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('item_variants');
        Schema::dropIfExists('item_attributes');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::table('unit_conversions', fn (Blueprint $table) => $table->dropForeign(['item_id']));
        Schema::dropIfExists('items');
    }
};
