<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSotMotivationAnswersTable extends Migration
{
    public function up(): void
    {
        Schema::create('sot_motivation_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId')->index();
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('questionId')->nullable();
            $table->unsignedBigInteger('optionId')->nullable();
            $table->text('answer')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();

            // Foreign key example (optional, if relevant models exist)
            // $table->foreign('optionId')->references('id')->on('sot_motivation_question_options')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sot_motivation_answers');
    }
}
