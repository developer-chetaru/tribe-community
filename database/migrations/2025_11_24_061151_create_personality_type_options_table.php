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
        Schema::create('personality_type_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->text('option_text');
            $table->unsignedBigInteger('personality_type_value_id')->nullable();
            $table->integer('score_value')->default(0); // Score for this option
            $table->integer('order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('personality_type_questions')->onDelete('cascade');
            $table->foreign('personality_type_value_id')->references('id')->on('personality_type_values')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personality_type_options');
    }
};
