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
        Schema::create('tribeometer_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('questionId');
            $table->unsignedBigInteger('optionId');
            $table->text('answer')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('set null');
            $table->foreign('questionId')->references('id')->on('tribeometer_questions')->onDelete('cascade');
            $table->foreign('optionId')->references('id')->on('tribeometer_options')->onDelete('cascade');
            
            $table->unique(['userId', 'questionId'], 'unique_user_question');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribeometer_answers');
    }
};
