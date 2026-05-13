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
        Schema::create('motivation_values', function (Blueprint $table) {
            $table->id();
            $table->string('value_key')->unique(); // financial_security, stress_free, etc.
            $table->string('title'); // Financial Security, Stress Free, etc.
            $table->text('description')->nullable(); // Detailed description
            $table->text('characteristics')->nullable(); // Key characteristics
            $table->text('management_strategy')->nullable(); // Management recommendations
            $table->integer('order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motivation_values');
    }
};
