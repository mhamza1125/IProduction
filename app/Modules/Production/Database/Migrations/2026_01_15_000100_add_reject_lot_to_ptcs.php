<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pieces rejected inside a PTC move to a zero-cost "rejects" lot: their cost
        // is an allowed loss already carried by the good pieces, so valuing them at
        // the output lot's cost counted that cost twice (audit finding F-01).
        Schema::table('ptcs', function (Blueprint $table) {
            $table->foreignId('reject_lot_id')->nullable()->after('output_lot_id')->constrained('lots');
        });
    }

    public function down(): void
    {
        Schema::table('ptcs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reject_lot_id');
        });
    }
};
