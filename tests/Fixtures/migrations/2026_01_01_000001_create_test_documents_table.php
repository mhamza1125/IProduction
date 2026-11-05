<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Loaded only in the testing environment (see AppServiceProvider).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_documents', function (Blueprint $table) {
            $table->id();
            $table->documentWorkflow();
            $table->date('document_date');
            $table->string('title')->nullable();
            $table->unsignedInteger('effects_run')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_documents');
    }
};
