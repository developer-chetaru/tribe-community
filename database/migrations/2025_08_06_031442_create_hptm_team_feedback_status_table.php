<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hptm_team_feedback_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('toUserId')->nullable();
            $table->unsignedBigInteger('fromUserId')->nullable();
            $table->boolean('completeStatus')->default(0);
            $table->timestamps();

            // Optional: If you're using MySQL 'users' table for both
            // $table->foreign('toUserId')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('fromUserId')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hptm_team_feedback_status');
    }
};
