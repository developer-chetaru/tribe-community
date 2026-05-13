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
        Schema::create('culture_structure_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_key')->unique(); // clan, adhocracy, market, hierarchy
            $table->string('title'); // Clan Culture, Adhocracy Culture, etc.
            $table->text('summary')->nullable(); // Brief description
            $table->longText('description')->nullable(); // Detailed description
            $table->text('characteristics')->nullable(); // Key characteristics
            $table->string('icon')->nullable(); // Icon/image path
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
        Schema::dropIfExists('culture_structure_types');
    }
};
