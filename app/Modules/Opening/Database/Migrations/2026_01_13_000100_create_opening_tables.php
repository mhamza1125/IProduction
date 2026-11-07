<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A dated opening document, imported from an Excel/CSV file.
        Schema::create('opening_batches', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->string('type', 30)->index();
            $table->date('batch_date');
            $table->string('file_name', 255);
            $table->char('file_hash', 64)->index();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('control_totals')->nullable(); // currency => total from the old system
            $table->json('totals')->nullable();         // currency => total imported
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('opening_batch_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_batch_id')->constrained('opening_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->json('errors')->nullable();
            $table->nullableMorphs('result');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_batch_rows');
        Schema::dropIfExists('opening_batches');
    }
};
