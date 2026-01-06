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
        Schema::create('payment_failure_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('organisation_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained('subscription_records')->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('payment_method')->nullable(); // stripe, card, etc.
            $table->string('transaction_id')->nullable(); // Stripe payment intent ID
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('failure_reason')->nullable(); // declined, insufficient_funds, expired_card, etc.
            $table->text('failure_message')->nullable(); // Detailed error message
            $table->integer('retry_attempt')->default(1);
            $table->timestamp('failure_date');
            $table->enum('status', ['pending_retry', 'retried', 'resolved', 'abandoned'])->default('pending_retry');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('organisation_id');
            $table->index('subscription_id');
            $table->index('invoice_id');
            $table->index('failure_date');
            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index(['organisation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_failure_logs');
    }
};
