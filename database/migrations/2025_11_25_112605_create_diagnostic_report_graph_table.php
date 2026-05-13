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
        Schema::create('diagnostic_report_graph', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('officeId')->nullable();
            $table->unsignedBigInteger('departmentId')->nullable();
            $table->unsignedBigInteger('categoryId');
            $table->double('with_weekend')->nullable();
            $table->double('without_weekend')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('categoryId')->references('id')->on('diagnostic_questions_category')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnostic_report_graph');
    }
};

