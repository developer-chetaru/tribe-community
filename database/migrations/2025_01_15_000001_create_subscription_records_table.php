<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table already exists (in case it was created manually or migration was partially run)
        if (Schema::hasTable('subscription_records')) {
            return;
        }

        Schema::create('subscription_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->onDelete('cascade');
            
            // Stripe fields
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_customer_id')->nullable();
            
            // PayPal fields
            $table->string('paypal_subscription_id')->nullable()->unique();
            $table->string('paypal_subscriber_id')->nullable();
            
            // Common fields
            $table->enum('tier', ['spark', 'momentum', 'vision', 'basecamp'])->default('basecamp');
            $table->integer('user_count')->default(1);
            $table->string('status')->default('active'); // active, past_due, canceled, suspended
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->timestamp('last_payment_date')->nullable();
            $table->integer('payment_failed_count')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            
            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_records');
    }
};

