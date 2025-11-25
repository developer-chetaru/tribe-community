<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHptmLearningChecklistTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hptm_learning_checklist', function (Blueprint $table) {
            $table->id(); // auto-increment primary key
            $table->unsignedBigInteger('principleId')->nullable(); // Foreign key to principles
            $table->unsignedBigInteger('output')->nullable();      // Foreign key to output type
            $table->string('link')->nullable();
            $table->string('document')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('readStatus')->default('unread');
            $table->timestamps();

            // Foreign keys (optional, uncomment if referenced tables exist)
            $table->foreign('principleId')->references('id')->on('hptm_principles')->onDelete('set null');
            $table->foreign('output')->references('id')->on('hptm_learning_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hptm_learning_checklist');
    }
}
