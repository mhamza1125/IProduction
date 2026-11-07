<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wage_work', function (Blueprint $table) {
            // Manual entries describe the work ("other work description").
            $table->string('description', 255)->nullable()->after('ptc_transaction_id');
            $table->index(['employee_id', 'status', 'payroll_line_id']);
        });
    }

    public function down(): void
    {
        Schema::table('wage_work', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'status', 'payroll_line_id']);
            $table->dropColumn('description');
        });
    }
};
