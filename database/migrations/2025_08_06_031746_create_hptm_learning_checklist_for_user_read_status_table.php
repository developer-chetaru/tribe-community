<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hptm_learning_checklist_for_user_read_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('checklistId')->nullable();
            $table->unsignedBigInteger('userId')->nullable();
            $table->boolean('readStatus')->default(0);
            $table->timestamps();
            $table->foreign('checklistId')->references('id')->on('hptm_learning_checklist')->onDelete('set null');
            $table->foreign('userId')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hptm_learning_checklist_for_user_read_status');
    }
};
