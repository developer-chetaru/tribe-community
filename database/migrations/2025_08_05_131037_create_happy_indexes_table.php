<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHappyIndexesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('happy_indexes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // corresponds to userId
            $table->unsignedBigInteger('mood_value'); // corresponds to moodValue
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            // Foreign keys (optional but recommended)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('mood_value')->references('id')->on('happy_index_mood_values')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('happy_indexes');
    }
}
