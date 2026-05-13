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
        Schema::create('culture_structure_individual_user_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userid');
            $table->unsignedBigInteger('orgId');
            $table->date('date');
            $table->boolean('completeStatus')->default(false);
            $table->timestamps();

            $table->index(['userid', 'orgId', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('culture_structure_individual_user_status');
    }
};
