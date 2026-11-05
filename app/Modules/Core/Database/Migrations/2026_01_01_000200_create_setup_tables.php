<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('symbol', 15)->unique();
            $table->boolean('allows_decimal')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units');
            $table->foreignId('to_unit_id')->constrained('units');
            // 1 from-unit = factor to-units, e.g. 1 dozen = 12 pieces.
            $table->decimal('factor', 20, 6);
            // Item-specific factor (e.g. 1 roll of this leather = 25 metres). FK added by the Catalog module.
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('units');
    }
};
