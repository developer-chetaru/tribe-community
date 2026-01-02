<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table exists before trying to alter it
        if (!Schema::hasTable('organisations')) {
            return;
        }

        Schema::table('organisations', function (Blueprint $table) {
            // Billing contact information
            $table->string('admin_email')->nullable()->after('name');
            $table->string('admin_first_name')->nullable()->after('admin_email');
            $table->string('admin_last_name')->nullable()->after('admin_first_name');
            
            // Billing address
            $table->string('billing_address_line1')->nullable()->after('admin_last_name');
            $table->string('billing_address_line2')->nullable()->after('billing_address_line1');
            $table->string('billing_city')->nullable()->after('billing_address_line2');
            $table->string('billing_postcode')->nullable()->after('billing_city');
            $table->string('billing_country')->default('GB')->after('billing_postcode');
            
            // Payment gateway IDs
            $table->string('stripe_customer_id')->nullable()->after('billing_country');
            $table->string('paypal_customer_id')->nullable()->after('stripe_customer_id');
            
            // Subscription tier
            $table->enum('subscription_tier', ['spark', 'momentum', 'vision', 'basecamp'])->default('basecamp')->after('paypal_customer_id');
            
            // User type
            $table->enum('user_type', ['organisation', 'basecamp'])->default('basecamp')->after('subscription_tier');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'admin_email',
                'admin_first_name',
                'admin_last_name',
                'billing_address_line1',
                'billing_address_line2',
                'billing_city',
                'billing_postcode',
                'billing_country',
                'stripe_customer_id',
                'paypal_customer_id',
                'subscription_tier',
                'user_type',
            ]);
        });
    }
};

