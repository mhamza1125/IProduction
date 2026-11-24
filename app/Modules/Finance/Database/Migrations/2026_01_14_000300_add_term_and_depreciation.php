<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Balance sheet classification: current or non-current (assets and liabilities only).
        Schema::table('gl_accounts', function (Blueprint $table) {
            $table->string('term', 12)->nullable()->after('type');
        });
        DB::table('gl_accounts')->whereIn('type', ['asset', 'liability', 'clearing'])->update(['term' => 'current']);

        // Straight-line depreciation of fixed assets.
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedSmallInteger('useful_life_months')->nullable()->after('value');
            $table->decimal('salvage_value', 18, 2)->nullable()->after('useful_life_months');
            $table->date('depreciation_start')->nullable()->after('salvage_value');
        });

        // One row per asset and month, so a month can never be depreciated twice.
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->date('period');
            $table->decimal('amount', 18, 2);
            $table->foreignId('journal_id')->constrained('journals');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['asset_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['useful_life_months', 'salvage_value', 'depreciation_start']);
        });
        Schema::table('gl_accounts', function (Blueprint $table) {
            $table->dropColumn('term');
        });
    }
};
