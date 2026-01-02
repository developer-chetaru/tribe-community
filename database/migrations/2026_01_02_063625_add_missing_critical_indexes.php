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
        // Add missing indexes to users table (if table exists)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->indexExists('users', 'users_orgid_index') && Schema::hasColumn('users', 'orgId')) {
                    $table->index('orgId', 'users_orgid_index');
                }
                if (!$this->indexExists('users', 'users_officeid_index') && Schema::hasColumn('users', 'officeId')) {
                    $table->index('officeId', 'users_officeid_index');
                }
                if (!$this->indexExists('users', 'users_departmentid_index') && Schema::hasColumn('users', 'departmentId')) {
                    $table->index('departmentId', 'users_departmentid_index');
                }
            });
        }

        // Add missing indexes to invoices table (if table exists)
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                // Check if indexes don't exist before adding
                if (!$this->indexExists('invoices', 'invoices_organisation_id_index') && Schema::hasColumn('invoices', 'organisation_id')) {
                    $table->index('organisation_id', 'invoices_organisation_id_index');
                }
                if (!$this->indexExists('invoices', 'invoices_user_id_index') && Schema::hasColumn('invoices', 'user_id')) {
                    $table->index('user_id', 'invoices_user_id_index');
                }
                if (!$this->indexExists('invoices', 'invoices_subscription_id_index') && Schema::hasColumn('invoices', 'subscription_id')) {
                    $table->index('subscription_id', 'invoices_subscription_id_index');
                }
                if (!$this->indexExists('invoices', 'invoices_status_index') && Schema::hasColumn('invoices', 'status')) {
                    $table->index('status', 'invoices_status_index');
                }
            });
        }

        // Add missing indexes to subscription_records table (if table exists)
        if (Schema::hasTable('subscription_records')) {
            Schema::table('subscription_records', function (Blueprint $table) {
                if (!$this->indexExists('subscription_records', 'subscription_records_user_id_index') && Schema::hasColumn('subscription_records', 'user_id')) {
                    $table->index('user_id', 'subscription_records_user_id_index');
                }
                // Check if single organisation_id index exists (not just composite)
                $hasSingleOrgIndex = $this->indexExists('subscription_records', 'subscription_records_organisation_id_index');
                if (!$hasSingleOrgIndex && Schema::hasColumn('subscription_records', 'organisation_id')) {
                    // Check if it's only in composite index
                    $indexes = collect(DB::select("SHOW INDEX FROM subscription_records WHERE Column_name = 'organisation_id'"));
                    $hasOnlyComposite = $indexes->every(function ($idx) {
                        return str_contains($idx->Key_name, 'organisation_id') && str_contains($idx->Key_name, 'status');
                    });
                    if ($hasOnlyComposite || $indexes->isEmpty()) {
                        $table->index('organisation_id', 'subscription_records_organisation_id_index');
                    }
                }
                // Add single status index (not just composite)
                $indexes = collect(DB::select("SHOW INDEX FROM subscription_records WHERE Column_name = 'status'"));
                $hasSingleStatusIndex = $indexes->contains(function ($idx) {
                    return $idx->Key_name === 'subscription_records_status_index';
                });
                if (!$hasSingleStatusIndex && Schema::hasColumn('subscription_records', 'status')) {
                    $table->index('status', 'subscription_records_status_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'users_orgid_index');
                $this->dropIndexIfExists($table, 'users_officeid_index');
                $this->dropIndexIfExists($table, 'users_departmentid_index');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'invoices_organisation_id_index');
                $this->dropIndexIfExists($table, 'invoices_user_id_index');
                $this->dropIndexIfExists($table, 'invoices_subscription_id_index');
                $this->dropIndexIfExists($table, 'invoices_status_index');
            });
        }

        if (Schema::hasTable('subscription_records')) {
            Schema::table('subscription_records', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'subscription_records_user_id_index');
                $this->dropIndexIfExists($table, 'subscription_records_organisation_id_index');
                $this->dropIndexIfExists($table, 'subscription_records_status_index');
            });
        }
    }

    /**
     * Check if an index exists
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            if (!Schema::hasTable($table)) {
                return false;
            }
            $indexes = collect(DB::select("SHOW INDEX FROM {$table}"));
            return $indexes->pluck('Key_name')->contains($indexName);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Safely drop index if it exists
     */
    protected function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Index doesn't exist, ignore
        }
    }
};
