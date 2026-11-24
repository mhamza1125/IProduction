<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Printed per line on commercial and proforma invoices. NULL origin = the company's country.
        Schema::table('items', function (Blueprint $table) {
            $table->string('hs_code', 20)->nullable()->after('name');
            $table->string('origin_country', 60)->nullable()->after('hs_code');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['hs_code', 'origin_country']);
        });
    }
};
