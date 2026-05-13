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
        Schema::create('diagnostic_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question', 225);
            $table->string('measure', 255)->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('diagnostic_questions_category')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnostic_questions');
    }
};

