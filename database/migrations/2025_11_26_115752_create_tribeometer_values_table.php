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
        Schema::create('tribeometer_values', function (Blueprint $table) {
            $table->id();
            $table->string('value_key', 50)->unique(); // directed, committed, selfless, honesty
            $table->string('title', 100); // Display name
            $table->text('description')->nullable();
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
        Schema::dropIfExists('tribeometer_values');
    }
};
