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
        Schema::create('motivation_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('motivation_value_id');
            $table->integer('rating')->default(0); // 0-5 rating
            $table->date('assessment_date');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('set null');
            $table->foreign('question_id')->references('id')->on('motivation_questions')->onDelete('cascade');
            $table->foreign('option_id')->references('id')->on('motivation_options')->onDelete('cascade');
            $table->foreign('motivation_value_id')->references('id')->on('motivation_values')->onDelete('cascade');
            $table->index(['userId', 'orgId', 'question_id', 'assessment_date'], 'motivation_answers_user_question_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motivation_answers');
    }
};
