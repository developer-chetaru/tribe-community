<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('subscription_id')->nullable()->constrained('subscription_records')->onDelete('set null');
            
            // Stripe fields
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_refund_id')->nullable();
            
            // PayPal fields
            $table->string('paypal_sale_id')->nullable();
            $table->string('paypal_capture_id')->nullable();
            $table->string('paypal_refund_id')->nullable();
            
            // Common fields
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->string('status'); // succeeded, failed, refunded, pending
            $table->enum('type', ['subscription_payment', 'one_time_payment', 'refund'])->default('subscription_payment');
            $table->text('failure_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            
            $table->index(['organisation_id', 'status']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};

