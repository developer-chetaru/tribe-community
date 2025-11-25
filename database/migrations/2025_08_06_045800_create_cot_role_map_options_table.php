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
    Schema::create('cot_role_map_options', function (Blueprint $table) {
        $table->id();
        $table->string('maper')->nullable();
        $table->string('maper_key')->nullable();
        $table->unsignedBigInteger('categoryId')->nullable();
        $table->text('short_description')->nullable();
        $table->longText('long_description')->nullable();
        $table->enum('status', ['Active', 'Inactive'])->default('Active');
        $table->timestamps();

        // Agar categoryId kisi aur table ka foreign key hai
        // $table->foreign('categoryId')->references('id')->on('categories')->onDelete('set null');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cot_role_map_options');
    }
};
