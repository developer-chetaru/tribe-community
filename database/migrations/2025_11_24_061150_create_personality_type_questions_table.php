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
        Schema::create('personality_type_questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->unsignedBigInteger('personality_type_value_id')->nullable(); // FK to personality_type_values
            $table->string('summary_trait')->nullable(); // Thinker, Solitary, etc.
            $table->integer('order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('personality_type_value_id')->references('id')->on('personality_type_values')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personality_type_questions');
    }
};
