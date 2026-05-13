<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feedbackId');
            $table->mediumText('message')->nullable();
            $table->unsignedBigInteger('sendTo');
            $table->unsignedBigInteger('sendFrom');
            $table->string('file', 255)->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('feedbackId')->references('id')->on('iot_feedbacks')->onDelete('cascade');
            $table->foreign('sendTo')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sendFrom')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_messages');
    }
};

