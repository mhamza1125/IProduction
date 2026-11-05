<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts. Journal lines post to these.
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            // asset | liability | equity | income | cost | expense | clearing
            $table->string('type', 20)->index();
            $table->foreignId('parent_id')->nullable()->constrained('gl_accounts');
            // Accounts the posting code relies on (accounts_receivable, inventory...). Cannot be deleted.
            $table->string('system_key', 50)->nullable()->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_accounts');
    }
};
