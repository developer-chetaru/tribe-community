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
        Schema::create('iot_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('to_bubble_user_id');
            $table->unsignedBigInteger('from_bubble_user_id')->nullable();
            $table->string('notificationType')->nullable();
            $table->string('notificationLinks')->nullable();
            $table->unsignedBigInteger('sendNotificationId')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
			$table->boolean('archive')->default(false);
            $table->timestamps();

            // Foreign Keys
            $table->foreign('to_bubble_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('from_bubble_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('sendNotificationId')->references('id')->on('send_notifications')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_notifications');
    }
};
