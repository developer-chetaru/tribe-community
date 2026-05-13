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
        Schema::create('cot_team_role_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->string('role_key'); // pioneer, motivator, etc.
            $table->integer('score')->default(0);
            $table->integer('preference_rank')->nullable(); // 1-8 (1 = highest preference)
            $table->date('assessment_date');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('set null');
            $table->index(['userId', 'orgId', 'assessment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cot_team_role_results');
    }
};
