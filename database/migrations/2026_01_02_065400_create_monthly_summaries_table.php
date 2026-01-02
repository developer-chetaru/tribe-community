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
        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->integer('month');
            $table->string('month_label')->nullable(); // e.g., "January 2026"
            $table->text('summary')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['user_id', 'year', 'month']);
            $table->unique(['user_id', 'year', 'month']); // One summary per user per month
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_summaries');
    }
};
