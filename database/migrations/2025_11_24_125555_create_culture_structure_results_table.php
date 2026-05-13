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
        Schema::create('culture_structure_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('culture_type_id');
            $table->string('type_key'); // clan, adhocracy, market, hierarchy
            $table->decimal('percentage', 5, 2)->default(0); // Percentage alignment
            $table->integer('score')->default(0); // Raw score
            $table->date('assessment_date');
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('set null');
            $table->foreign('culture_type_id')->references('id')->on('culture_structure_types')->onDelete('cascade');
            $table->index(['userId', 'orgId', 'assessment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('culture_structure_results');
    }
};
