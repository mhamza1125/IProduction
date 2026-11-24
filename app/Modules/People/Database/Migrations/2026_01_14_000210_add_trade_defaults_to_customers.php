<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copied onto new invoices and printed on proforma invoices.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('default_incoterm', 3)->nullable()->after('payment_terms');
            $table->string('default_incoterm_place', 100)->nullable()->after('default_incoterm');
            $table->string('default_port_of_discharge', 100)->nullable()->after('default_incoterm_place');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['default_incoterm', 'default_incoterm_place', 'default_port_of_discharge']);
        });
    }
};
