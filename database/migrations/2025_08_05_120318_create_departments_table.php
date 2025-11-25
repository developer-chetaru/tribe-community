<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->integer('numberOfEmployees')->nullable();

            $table->unsignedBigInteger('office_id');
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('all_department_id');

            $table->string('status')->default('active');
            $table->timestamps();

            // Foreign keys (optional, only if tables exist and you want constraints)
            $table->foreign('office_id')->references('id')->on('offices')->onDelete('cascade');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('all_department_id')->references('id')->on('all_departments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
