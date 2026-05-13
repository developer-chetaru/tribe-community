<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->mediumText('message');
            $table->string('image', 255)->nullable();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId');
            $table->string('SWOT', 255)->nullable();
            $table->text('themeId')->nullable();
            $table->text('feedbackSummary')->nullable();
            $table->integer('initialRiskScore')->default(1);
            $table->text('actionTaken')->nullable();
            $table->string('feedbackStatus', 255)->default('1');
            $table->string('mitigatedScore', 255)->default('1');
            $table->text('updatedText')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Completed'])->default('Active');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_feedbacks');
    }
};

