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
        Schema::create('login_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('session_id')->nullable(); // Laravel session ID for web logins
            $table->string('token_id')->nullable(); // JWT token ID for API logins
            
            // Platform & Device Info
            $table->enum('platform', ['web', 'mobile', 'api'])->default('web'); // web, mobile app, or API
            $table->string('device_type')->nullable(); // ios, android, web
            $table->string('device_id')->nullable(); // Unique device identifier
            $table->string('device_name')->nullable(); // Device name/model
            $table->string('os_name')->nullable(); // iOS, Android, Windows, macOS, Linux
            $table->string('os_version')->nullable(); // OS version
            $table->string('browser_name')->nullable(); // Chrome, Safari, Firefox, etc.
            $table->string('browser_version')->nullable(); // Browser version
            
            // Network & Location
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->nullable();
            
            // Session Timing
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->integer('session_duration_seconds')->nullable(); // Duration in seconds
            
            // Additional Info
            $table->string('fcm_token')->nullable(); // Firebase Cloud Messaging token
            $table->text('additional_data')->nullable(); // JSON for extra info
            
            // Status
            $table->enum('status', ['active', 'expired', 'logged_out'])->default('active');
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('session_id');
            $table->index('token_id');
            $table->index('platform');
            $table->index('device_type');
            $table->index('login_at');
            $table->index('status');
            $table->index(['user_id', 'status']);
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
    }
};
