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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('image')->nullable();
            $table->float('EIScore')->nullable();
            $table->string('fcmToken')->nullable();
            $table->date('lastHIDate')->nullable();
            $table->boolean('onLeave')->default(false);
            $table->float('hptmEvaluationScore')->nullable();
            $table->boolean('HI_include_sunday')->default(false);
            $table->boolean('HI_include_saturday')->default(false);
            $table->unsignedBigInteger('orgId')->nullable();
            $table->unsignedBigInteger('departmentId')->nullable();
            $table->unsignedBigInteger('officeId')->nullable();
            $table->boolean('firstLogin')->default(true);
            $table->string('deviceType')->nullable();
            $table->string('deviceId')->nullable();
            $table->string('contact')->nullable();
            $table->string('phone')->nullable();
			$table->string('country_code', 255)->nullable();
            $table->boolean('status')->default(true);

            $table->rememberToken();
            $table->foreignId('current_team_id')->nullable();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->timestamps();
        });

        
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
