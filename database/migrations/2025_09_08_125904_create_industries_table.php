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
    Schema::create('industries', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('org_id'); // link industry to organisation
        $table->string('name');
        $table->boolean('status')->default(1); // 1 = active, 0 = inactive
        $table->timestamps();

        $table->foreign('org_id')->references('id')->on('organisations')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::dropIfExists('industries');
}


};
