<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration drops the old subscriptions table and related data
     * as we're migrating to the new subscription_records system.
     */
    public function up(): void
    {
        // Drop foreign key constraints first
        if (Schema::hasTable('invoices')) {
            try {
                // Get all foreign keys on invoices table that reference subscriptions
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'invoices' 
                    AND CONSTRAINT_NAME LIKE '%subscription%'
                ");
                
                if (!empty($foreignKeys)) {
                    Schema::table('invoices', function (Blueprint $table) use ($foreignKeys) {
                        foreach ($foreignKeys as $fk) {
                            try {
                                $table->dropForeign($fk->CONSTRAINT_NAME);
                            } catch (\Exception $e) {
                                // Foreign key might not exist, continue
                            }
                        }
                    });
                }
            } catch (\Exception $e) {
                // If query fails, try alternative method
                try {
                    Schema::table('invoices', function (Blueprint $table) {
                        $table->dropForeign(['subscription_id']);
                    });
                } catch (\Exception $e2) {
                    // Foreign key doesn't exist or already dropped, continue
                }
            }
        }

        // Drop the old subscriptions table
        if (Schema::hasTable('subscriptions')) {
            Schema::dropIfExists('subscriptions');
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This will recreate the old table structure but data will be lost.
     */
    public function down(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->integer('user_count')->default(0);
            $table->decimal('price_per_user', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->string('status')->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_billing_date');
            $table->string('billing_cycle')->default('monthly');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->index('organisation_id');
            $table->index('status');
        });
    }
};

