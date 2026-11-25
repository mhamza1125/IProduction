<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contractor stage work waits for approval like wage work before it is
        // owed (audit F-17), and the person who recorded the work may not approve it.
        Schema::table('wage_work', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
            $table->foreignId('contractor_id')->nullable()->after('employee_id')->constrained('contractors');
            $table->foreignId('created_by')->nullable()->after('approved_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('wage_work', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('contractor_id');
        });
    }
};
