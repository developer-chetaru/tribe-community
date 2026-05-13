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
        Schema::create('motivation_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('motivation_value_id');
            $table->string('value_key'); // financial_security, stress_free, etc.
            $table->decimal('score', 8, 2)->default(0); // Total score for this motivation
            $table->integer('rank')->nullable(); // Rank 1-10 (1 = highest motivator)
            $table->date('assessment_date');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('set null');
            $table->foreign('motivation_value_id')->references('id')->on('motivation_values')->onDelete('cascade');
            $table->index(['userId', 'orgId', 'assessment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motivation_results');
    }
};
