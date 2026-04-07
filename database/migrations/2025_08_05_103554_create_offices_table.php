<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id')->nullable(); // foreign key
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
          	$table->string('country_code', 255)->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->string('status')->nullable();
            $table->timestamps();

            // Foreign key constraint (optional)
            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
