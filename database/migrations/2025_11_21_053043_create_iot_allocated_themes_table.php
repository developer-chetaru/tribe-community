<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_allocated_themes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feedbackId');
            $table->unsignedBigInteger('themeId');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('feedbackId')->references('id')->on('iot_feedbacks')->onDelete('cascade');
            $table->foreign('themeId')->references('id')->on('iot_themes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_allocated_themes');
    }
};

