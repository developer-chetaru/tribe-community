<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dot_values_individual_user_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId')->nullable();
            $table->unsignedBigInteger('orgId')->nullable();
            $table->date('date')->nullable();
            $table->boolean('completeStatus')->default(false);
            $table->timestamps();

            // Optional: Add foreign keys if needed
            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dot_values_individual_user_status');
    }
};
