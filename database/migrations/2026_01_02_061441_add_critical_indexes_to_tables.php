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
        // Add missing indexes to users table
        Schema::table('users', function (Blueprint $table) {
            // Check if indexes don't exist before adding them
            if (!$this->hasIndex('users', 'users_orgid_index')) {
                $table->index('orgId');
            }
            if (!$this->hasIndex('users', 'users_officeid_index')) {
                $table->index('officeId');
            }
            if (!$this->hasIndex('users', 'users_departmentid_index')) {
                $table->index('departmentId');
            }
        });

        // Add missing indexes to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            // Check if indexes don't exist before adding them
            if (!$this->hasIndex('invoices', 'invoices_user_id_index')) {
                $table->index('user_id');
            }
            if (!$this->hasIndex('invoices', 'invoices_subscription_id_index')) {
                $table->index('subscription_id');
            }
            // organisation_id and status already exist from original migration
        });

        // Add missing indexes to subscription_records table
        Schema::table('subscription_records', function (Blueprint $table) {
            // Check if indexes don't exist before adding them
            if (!$this->hasIndex('subscription_records', 'subscription_records_user_id_index')) {
                $table->index('user_id');
            }
            if (!$this->hasIndex('subscription_records', 'subscription_records_status_index')) {
                $table->index('status');
            }
            // organisation_id and composite index already exist from original migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['orgId']);
            $table->dropIndex(['officeId']);
            $table->dropIndex(['departmentId']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['subscription_id']);
        });

        Schema::table('subscription_records', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
        });
    }

    /**
     * Check if an index exists
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        $indexes = collect(\DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name');
        return $indexes->contains($indexName);
    }
};
