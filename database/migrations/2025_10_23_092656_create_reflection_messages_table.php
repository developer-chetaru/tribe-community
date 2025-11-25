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
        Schema::create('reflection_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id(); // BIGINT UNSIGNED
            $table->unsignedBigInteger('reflectionId'); // must match reflections.id
            $table->unsignedBigInteger('sendFrom'); // must match users.id
            $table->unsignedBigInteger('sendTo');   // must match users.id
            $table->text('message')->nullable();
            $table->string('file')->nullable();
            $table->enum('status', ['Active','Inactive'])->default('Active');
            $table->timestamps();

            // Foreign keys
            $table->foreign('reflectionId')->references('id')->on('hptm_reflections')->onDelete('cascade');
            $table->foreign('sendFrom')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sendTo')->references('id')->on('users')->onDelete('cascade');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reflection_messages');
    }
};
