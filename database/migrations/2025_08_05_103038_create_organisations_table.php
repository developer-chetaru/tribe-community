<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
          	$table->string('country_code', 255)->nullable();
            $table->string('industry')->nullable();
            $table->string('turnover')->nullable();
            $table->string('profile_visibility')->nullable();
            $table->json('working_days')->nullable();
            $table->string('founded_year')->nullable();
            $table->string('url')->nullable();
            $table->string('progress_step')->nullable();
            $table->string('appPaymentVersion')->nullable();
            $table->string('status')->nullable();
            $table->boolean('HI_include_saturday')->default(false);
            $table->boolean('HI_include_sunday')->default(false);
            $table->json('personaliseData')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
