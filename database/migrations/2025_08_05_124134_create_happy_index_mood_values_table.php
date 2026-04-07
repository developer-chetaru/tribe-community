<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHappyIndexMoodValuesTable extends Migration
{
    public function up(): void
    {
        Schema::create('happy_index_mood_values', function (Blueprint $table) {
            $table->id();
            $table->string('moodName');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('happy_index_mood_values');
    }
}
