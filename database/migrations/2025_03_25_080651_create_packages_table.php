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
        // Update packages table to include features_metadata field
        Schema::table('packages', function (Blueprint $table) {
            $table->json('features_metadata')->nullable()->after('unit_price');
        });

        // Create package_features table
        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->boolean('is_included')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();

            // Add index for faster queries
            $table->index('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_features');

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('features_metadata');
        });
    }
};
