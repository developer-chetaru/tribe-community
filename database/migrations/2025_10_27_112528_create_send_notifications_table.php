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
        Schema::create('send_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('officeId')->nullable();
            $table->unsignedBigInteger('departmentId')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('links')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            // Foreign Keys (optional but good for relationships)
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('officeId')->references('id')->on('offices')->onDelete('cascade');
            // $table->foreign('departmentId')->references('id')->on('departments')->onDelete('cascade');
            $table->foreign('departmentId')->references('id')->on('all_departments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('send_notifications');
    }
};
