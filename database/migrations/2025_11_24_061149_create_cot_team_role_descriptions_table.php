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
        Schema::create('cot_team_role_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('role_key')->unique(); // pioneer, motivator, soloist, etc.
            $table->string('title'); // e.g., "Value Driver", "Deliberator"
            $table->string('value_focus'); // e.g., "Value Focused", "Analysis"
            $table->text('description')->nullable();
            $table->text('focus')->nullable();
            $table->text('standard_questions')->nullable(); // JSON or text
            $table->text('disruption')->nullable(); // Disruption patterns
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
        Schema::dropIfExists('cot_team_role_descriptions');
    }
};
