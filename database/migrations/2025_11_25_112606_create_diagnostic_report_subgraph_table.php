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
        Schema::create('diagnostic_report_subgraph', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orgId');
            $table->unsignedBigInteger('officeId')->nullable();
            $table->unsignedBigInteger('departmentId')->nullable();
            $table->unsignedBigInteger('categoryId');
            $table->unsignedBigInteger('quesId');
            $table->date('date');
            $table->double('with_weekend')->nullable();
            $table->double('without_weekend')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->nullable();
            $table->timestamps();

            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('categoryId')->references('id')->on('diagnostic_questions_category')->onDelete('cascade');
            $table->foreign('quesId')->references('id')->on('diagnostic_questions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnostic_report_subgraph');
    }
};

