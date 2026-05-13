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
        Schema::create('culture_structure_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('culture_type_id');
            $table->date('assessment_date');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('set null');
            $table->foreign('question_id')->references('id')->on('culture_structure_questions')->onDelete('cascade');
            $table->foreign('option_id')->references('id')->on('culture_structure_options')->onDelete('cascade');
            $table->foreign('culture_type_id')->references('id')->on('culture_structure_types')->onDelete('cascade');
            $table->index(['userId', 'orgId', 'question_id', 'assessment_date'], 'culture_answers_user_question_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('culture_structure_answers');
    }
};
