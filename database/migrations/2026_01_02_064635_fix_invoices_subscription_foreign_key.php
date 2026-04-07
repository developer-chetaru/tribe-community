<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix invoices.subscription_id foreign key to point to subscription_records instead of subscriptions
     */
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        // Get all foreign keys on subscription_id column
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'invoices' 
            AND COLUMN_NAME = 'subscription_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop existing foreign keys
        Schema::table('invoices', function (Blueprint $table) use ($foreignKeys) {
            foreach ($foreignKeys as $fk) {
                try {
                    $table->dropForeign($fk->CONSTRAINT_NAME);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
            }
        });

        // Make subscription_id nullable if not already
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
        });

        // Update any invalid subscription_id references
        if (Schema::hasTable('subscription_records')) {
            DB::statement("
                UPDATE invoices 
                SET subscription_id = NULL 
                WHERE subscription_id IS NOT NULL 
                AND subscription_id NOT IN (SELECT id FROM subscription_records)
            ");
        }

        // Add new foreign key to subscription_records (nullable)
        if (Schema::hasTable('subscription_records')) {
            Schema::table('invoices', function (Blueprint $table) {
                try {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscription_records')
                        ->onDelete('set null');
                } catch (\Exception $e) {
                    // Foreign key might already exist, continue
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                try {
                    $table->dropForeign(['subscription_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            });
        }
    }
};
