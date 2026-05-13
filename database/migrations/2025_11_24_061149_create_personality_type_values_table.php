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
        Schema::create('personality_type_values', function (Blueprint $table) {
            $table->id();
            $table->string('dimension_key')->unique(); // thinker, solitary, observant, etc.
            $table->string('title'); // e.g., "Thinker", "Solitary"
            $table->text('description')->nullable();
            $table->text('characteristics')->nullable();
            $table->text('real_world_applications')->nullable();
            $table->text('team_collaboration_tips')->nullable();
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
        Schema::dropIfExists('personality_type_values');
    }
};
