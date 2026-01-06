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
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscription_records')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('organisation_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type'); // created, renewed, cancelled, suspended, reactivated, payment_failed, payment_succeeded
            $table->text('event_data')->nullable(); // JSON data with event details
            $table->string('triggered_by')->nullable(); // system, user, admin, webhook
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('event_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('subscription_id');
            $table->index('user_id');
            $table->index('organisation_id');
            $table->index('event_type');
            $table->index('event_date');
            $table->index(['subscription_id', 'event_type']);
            $table->index(['user_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
