<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sot_motivation_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->string('option_name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->tinyInteger('status')->default(1); // 1: Active, 0: Inactive
            $table->timestamps();

            // Optional foreign keys
            // $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
            // $table->foreign('category_id')->references('id')->on('sot_motivation_value_records')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sot_motivation_question_options');
    }
};
