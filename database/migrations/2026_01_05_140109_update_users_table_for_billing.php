<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check current status column type and migrate data first
        $currentStatuses = DB::table('users')->pluck('id', 'status')->toArray();
        
        // Drop the old status column if it exists
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });

        // Add ENUM status column
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', [
                'pending_payment',
                'active_unverified',
                'active_verified',
                'suspended',
                'cancelled',
                'inactive'
            ])->default('pending_payment')->after('country_code');
        });

        // Migrate existing data based on email_verified_at and old status
        // If email is verified -> active_verified
        // If email not verified but status was true -> active_unverified  
        // If status was false -> pending_payment
        DB::statement("
            UPDATE users 
            SET status = CASE 
                WHEN email_verified_at IS NOT NULL THEN 'active_verified'
                WHEN email_verified_at IS NULL THEN 'active_unverified'
                ELSE 'pending_payment'
            END
        ");

        // Add missing billing-related fields
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'payment_grace_period_start')) {
                $table->timestamp('payment_grace_period_start')->nullable()->after('stripe_customer_id');
            }
            if (!Schema::hasColumn('users', 'suspension_date')) {
                $table->timestamp('suspension_date')->nullable()->after('payment_grace_period_start');
            }
            if (!Schema::hasColumn('users', 'last_payment_failure_date')) {
                $table->timestamp('last_payment_failure_date')->nullable()->after('suspension_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop new columns
            if (Schema::hasColumn('users', 'last_payment_failure_date')) {
                $table->dropColumn('last_payment_failure_date');
            }
            if (Schema::hasColumn('users', 'suspension_date')) {
                $table->dropColumn('suspension_date');
            }
            if (Schema::hasColumn('users', 'payment_grace_period_start')) {
                $table->dropColumn('payment_grace_period_start');
            }
            if (Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->dropColumn('stripe_customer_id');
            }
            
            // Drop ENUM status
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });

        // Restore boolean status
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('country_code');
        });
    }
};
