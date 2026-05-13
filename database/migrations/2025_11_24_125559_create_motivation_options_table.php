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
        Schema::create('motivation_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('motivation_value_id');
            $table->text('option_text');
            $table->string('option_label')->nullable(); // Option A, Option B, etc.
            $table->integer('order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('motivation_questions')->onDelete('cascade');
            $table->foreign('motivation_value_id')->references('id')->on('motivation_values')->onDelete('cascade');
            $table->index(['question_id', 'motivation_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motivation_options');
    }
};
