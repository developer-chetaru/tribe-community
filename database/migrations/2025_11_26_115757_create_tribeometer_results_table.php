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
        Schema::create('tribeometer_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('value_id');
            $table->decimal('score', 5, 2)->default(0); // Percentage score 0-100
            $table->decimal('average_score', 5, 2)->default(0); // Average of option scores
            $table->integer('total_responses')->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('set null');
            $table->foreign('value_id')->references('id')->on('tribeometer_values')->onDelete('cascade');
            
            $table->unique(['userId', 'value_id'], 'unique_user_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribeometer_results');
    }
};
