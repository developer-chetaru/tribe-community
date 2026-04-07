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
        if (!Schema::hasTable('invoices')) {
            return;
        }
        
        // First, remove any duplicate invoices that might exist
        // Keep only the first (oldest) invoice for each user/subscription/date combination
        try {
            // For user-based invoices (basecamp)
            $userDuplicates = DB::table('invoices')
                ->select('user_id', 'subscription_id', DB::raw('DATE(invoice_date) as invoice_date_only'))
                ->whereNotNull('user_id')
                ->groupBy('user_id', 'subscription_id', DB::raw('DATE(invoice_date)'))
                ->havingRaw('COUNT(*) > 1')
                ->get();
            
            foreach ($userDuplicates as $dup) {
                // Keep the oldest invoice, delete the rest
                $invoices = DB::table('invoices')
                    ->where('user_id', $dup->user_id)
                    ->where('subscription_id', $dup->subscription_id)
                    ->whereDate('invoice_date', $dup->invoice_date_only)
                    ->orderBy('id', 'asc')
                    ->get();
                
                // Delete all except the first one
                if ($invoices->count() > 1) {
                    $idsToDelete = $invoices->skip(1)->pluck('id');
                    DB::table('invoices')->whereIn('id', $idsToDelete)->delete();
                }
            }
            
            // For organisation-based invoices
            $orgDuplicates = DB::table('invoices')
                ->select('organisation_id', 'subscription_id', DB::raw('DATE(invoice_date) as invoice_date_only'))
                ->whereNotNull('organisation_id')
                ->whereNull('user_id')
                ->groupBy('organisation_id', 'subscription_id', DB::raw('DATE(invoice_date)'))
                ->havingRaw('COUNT(*) > 1')
                ->get();
            
            foreach ($orgDuplicates as $dup) {
                // Keep the oldest invoice, delete the rest
                $invoices = DB::table('invoices')
                    ->where('organisation_id', $dup->organisation_id)
                    ->where('subscription_id', $dup->subscription_id)
                    ->whereDate('invoice_date', $dup->invoice_date_only)
                    ->orderBy('id', 'asc')
                    ->get();
                
                // Delete all except the first one
                if ($invoices->count() > 1) {
                    $idsToDelete = $invoices->skip(1)->pluck('id');
                    DB::table('invoices')->whereIn('id', $idsToDelete)->delete();
                }
            }
        } catch (\Exception $e) {
            // If there's an error, log it but continue
            \Log::warning('Error cleaning duplicate invoices: ' . $e->getMessage());
        }
        
        // Add unique index to prevent duplicate invoices on the same date
        // Note: MySQL allows multiple NULLs in unique indexes, so this works for both user_id and organisation_id
        try {
            Schema::table('invoices', function (Blueprint $table) {
                // For basecamp users: user_id + subscription_id + invoice_date must be unique
                // This index will prevent duplicate invoices for same user/subscription on same date
                if (!$this->indexExists('invoices', 'unique_user_subscription_date')) {
                    $table->unique(['user_id', 'subscription_id', 'invoice_date'], 'unique_user_subscription_date');
                }
            });
        } catch (\Exception $e) {
            \Log::warning('Could not add unique index for user invoices: ' . $e->getMessage());
        }
        
        try {
            Schema::table('invoices', function (Blueprint $table) {
                // For organisation invoices: organisation_id + subscription_id + invoice_date must be unique
                if (!$this->indexExists('invoices', 'unique_org_subscription_date')) {
                    $table->unique(['organisation_id', 'subscription_id', 'invoice_date'], 'unique_org_subscription_date');
                }
            });
        } catch (\Exception $e) {
            \Log::warning('Could not add unique index for org invoices: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if an index exists
     */
    private function indexExists($table, $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        $indexes = $connection->select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return count($indexes) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique('unique_user_subscription_date');
                $table->dropUnique('unique_org_subscription_date');
            });
        }
    }
};
