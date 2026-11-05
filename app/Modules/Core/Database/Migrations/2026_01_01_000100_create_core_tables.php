<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 100);
            $table->string('symbol', 10)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Numbering format per document type, e.g. SO-26-0001.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50)->unique();
            $table->string('prefix', 20);
            $table->string('separator', 3)->default('-');
            $table->string('year_format', 10)->default('YY'); // YY | YYYY | none
            $table->unsignedTinyInteger('padding')->default(4);
            $table->timestamps();
        });

        // One counter per document type and year. Locked while a number is issued.
        Schema::create('number_sequence_counters', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            // Year part ("26", "2026") or '' when numbers never reset.
            // Not nullable, because MySQL unique indexes allow repeated NULLs.
            $table->string('period', 10)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['document_type', 'period']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('number_sequence_counters');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('settings');
    }
};
