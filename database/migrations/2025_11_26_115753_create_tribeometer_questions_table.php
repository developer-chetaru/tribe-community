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
        Schema::create('tribeometer_questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('measure', 255)->nullable();
            $table->unsignedBigInteger('value_id');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('value_id')->references('id')->on('tribeometer_values')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribeometer_questions');
    }
};
