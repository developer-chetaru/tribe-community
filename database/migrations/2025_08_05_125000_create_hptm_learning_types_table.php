<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHptmLearningTypesTable extends Migration
{
    public function up(): void
    {
        Schema::create('hptm_learning_types', function (Blueprint $table) {
            $table->id(); // auto-increment
            $table->string('title');
            $table->integer('score');
            $table->integer('priority');
            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hptm_learning_types');
    }
}
