<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_themes', function (Blueprint $table) {
            $table->id();
            $table->timestamp('dateOpened');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->integer('type')->nullable();
            $table->unsignedBigInteger('orgId');
            $table->string('submission', 255)->nullable();
            $table->enum('initialLikelihood', ['0', '1', '2', '3', '4', '5'])->nullable();
            $table->enum('initialConsequence', ['0', '1', '2', '3', '4', '5'])->nullable();
            $table->integer('initialRiskRating')->nullable();
            $table->enum('currentLikelihood', ['0', '1', '2', '3', '4', '5'])->nullable();
            $table->enum('currentConsequence', ['0', '1', '2', '3', '4', '5'])->nullable();
            $table->integer('currentRiskRating')->nullable();
            $table->string('linkedAction', 255)->nullable();
            $table->text('comment')->nullable();
            $table->enum('themeStatus', ['Open', 'Closed'])->default('Open');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_themes');
    }
};

