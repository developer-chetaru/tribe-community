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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // e.g., 'user', 'organisation', 'invoice', 'payment', etc.
            $table->string('action'); // e.g., 'created', 'updated', 'deleted', 'login', 'logout', etc.
            $table->text('description')->nullable(); // Human-readable description
            $table->unsignedBigInteger('user_id')->nullable(); // Who performed the action
            $table->string('user_name')->nullable(); // User name for quick reference
            $table->string('user_email')->nullable(); // User email for quick reference
            $table->unsignedBigInteger('subject_id')->nullable(); // ID of the subject (e.g., invoice_id, user_id)
            $table->string('subject_type')->nullable(); // Type of subject (e.g., 'App\Models\Invoice')
            $table->json('old_values')->nullable(); // Old values before update
            $table->json('new_values')->nullable(); // New values after update
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('module');
            $table->index('action');
            $table->index('user_id');
            $table->index('subject_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
