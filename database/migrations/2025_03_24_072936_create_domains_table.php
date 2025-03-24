<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');            // e.g. "Hungarian", "English", etc.
            $table->string('domain');          // e.g. "example.hu", "example.com"
            $table->string('database_name');   // Database name for this domain
            $table->string('database_host')->default('localhost');
            $table->string('database_username');
            $table->string('database_password');
            $table->boolean('is_active')->default(true);
            $table->string('currency')->default('EUR'); // Default currency for this domain
            $table->string('country_code', 2);  // Country code (HU, EN, etc.)
            $table->string('language_code', 5); // Language code (hu-HU, en-US, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
