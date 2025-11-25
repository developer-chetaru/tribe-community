<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHptmTeamFeedbackQuestionsTable extends Migration
{
    public function up()
    {
        Schema::create('hptm_team_feedback_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question'); // you can adjust length or type if needed
            $table->unsignedBigInteger('principle_id')->nullable(); // FK to principles table
            $table->timestamps();

            // Foreign key constraint (optional, but recommended)
            $table->foreign('principle_id')->references('id')->on('hptm_principles')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hptm_team_feedback_questions');
    }
}
