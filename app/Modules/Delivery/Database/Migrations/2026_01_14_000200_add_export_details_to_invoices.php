<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Export shipping details printed on the commercial invoice. They do not
        // affect the accounts, so they stay editable after posting.
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('incoterm', 3)->nullable()->after('payment_terms');
            $table->string('incoterm_place', 100)->nullable()->after('incoterm');
            $table->string('port_of_loading', 100)->nullable()->after('incoterm_place');
            $table->string('port_of_discharge', 100)->nullable()->after('port_of_loading');
            $table->string('final_destination', 100)->nullable()->after('port_of_discharge');
            $table->string('vessel_flight', 100)->nullable()->after('final_destination');
            $table->string('container_no', 100)->nullable()->after('vessel_flight');
            $table->string('bl_awb_no', 60)->nullable()->after('container_no');
            $table->date('bl_awb_date')->nullable()->after('bl_awb_no');
            $table->string('fi_number', 60)->nullable()->after('bl_awb_date');
            $table->string('gd_number', 60)->nullable()->after('fi_number');
            $table->date('gd_date')->nullable()->after('gd_number');
            $table->text('consignee')->nullable()->after('gd_date');
            $table->text('notify_party')->nullable()->after('consignee');
            $table->text('shipping_marks')->nullable()->after('notify_party');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['incoterm', 'incoterm_place', 'port_of_loading', 'port_of_discharge', 'final_destination', 'vessel_flight',
                'container_no', 'bl_awb_no', 'bl_awb_date', 'fi_number', 'gd_number', 'gd_date', 'consignee', 'notify_party', 'shipping_marks']);
        });
    }
};
