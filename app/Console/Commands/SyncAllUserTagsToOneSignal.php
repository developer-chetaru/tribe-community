<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OneSignalService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SyncAllUserTagsToOneSignal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onesignal:sync-all-tags {--force : Force update all users regardless of changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all user tags to OneSignal (runs every 5 minutes)';

    /**
     * Execute the console command.
     */
    public function handle(OneSignalService $oneSignal)
    {
        $force = $this->option('force');
        
        $this->info('Starting OneSignal tags sync...');
        Log::info('Cron: SyncAllUserTagsToOneSignal started');

        // Get all active users
        // Status is ENUM: 'pending_payment', 'active_unverified', 'active_verified', 'suspended', 'cancelled', 'inactive'
        $users = User::whereIn('status', ['active_verified', 'active_unverified', 'pending_payment'])
            ->with('organisation')
            ->get();
        
        // Get all users (regardless of status) to check excluded users by organization
        $allUsers = User::with('organisation')->get();
        $excludedUsers = $allUsers->whereNotIn('id', $users->pluck('id'));
        
        // Group excluded users by organization
        $excludedByOrg = [];
        foreach ($excludedUsers as $excludedUser) {
            $orgId = $excludedUser->orgId ? (string) $excludedUser->orgId : 'basecamp';
            $orgName = $excludedUser->organisation?->name ?? 'No Organization';
            
            if (!isset($excludedByOrg[$orgId])) {
                $excludedByOrg[$orgId] = [
                    'org_id' => $orgId,
                    'org_name' => $orgName,
                    'users' => [],
                ];
            }
            
            $excludedByOrg[$orgId]['users'][] = [
                'id' => $excludedUser->id,
                'email' => $excludedUser->email,
                'status' => $excludedUser->status,
            ];
        }
        
        // Display excluded users by organization
        if (!empty($excludedByOrg)) {
            $this->newLine();
            $this->warn('⚠️  Users excluded due to status (not in active_verified, active_unverified, pending_payment):');
            foreach ($excludedByOrg as $orgData) {
                $userCount = count($orgData['users']);
                $this->line("   Organization: {$orgData['org_name']} (ID: {$orgData['org_id']}) - {$userCount} users excluded");
                foreach ($orgData['users'] as $excludedUser) {
                    $this->line("      - {$excludedUser['email']} (ID: {$excludedUser['id']}, Status: {$excludedUser['status']})");
                }
            }
            
            Log::warning('Users excluded from sync due to status', [
                'excluded_by_org' => $excludedByOrg,
            ]);
        }

        if ($users->isEmpty()) {
            $this->warn('No active users found.');
            Log::warning('Cron: SyncAllUserTagsToOneSignal - No active users');
            return 0;
        }

        $this->info("Found {$users->count()} active users. Syncing tags...");

        $stats = [
            'total' => $users->count(),
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];
        
        // Track organization-wise statistics
        $orgStats = [];
        $orgWorkingDayStats = [];

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            try {
                // Refresh user to get latest data
                $user->refresh();
                
                // Reload organization relationship if user has orgId
                if ($user->orgId) {
                    $user->load('organisation');
                }
                
                // Track organization statistics
                $orgId = $user->orgId ? (string) $user->orgId : 'basecamp';
                $orgName = $user->organisation?->name ?? 'No Organization';
                
                if (!isset($orgStats[$orgId])) {
                    $orgStats[$orgId] = [
                        'org_id' => $orgId,
                        'org_name' => $orgName,
                        'total_users' => 0,
                        'synced' => 0,
                        'failed' => 0,
                    ];
                }
                $orgStats[$orgId]['total_users']++;
                
                // Sync all tags to OneSignal
                // This creates user if doesn't exist and updates all tags
                $result = $oneSignal->setUserTagsOnLogin($user);

                if ($result) {
                    $stats['synced']++;
                    $orgStats[$orgId]['synced']++;
                    
                    // Check working day status for organization users
                    $isWorkingDay = $oneSignal->isWorkingDayToday($user);
                    
                    // Track working day statistics by organization
                    if ($user->orgId) {
                        if (!isset($orgWorkingDayStats[$orgId])) {
                            $orgWorkingDayStats[$orgId] = [
                                'org_id' => $orgId,
                                'org_name' => $orgName,
                                'working_days' => $user->organisation?->working_days,
                                'true_count' => 0,
                                'false_count' => 0,
                                'users' => [],
                            ];
                        }
                        
                        if ($isWorkingDay) {
                            $orgWorkingDayStats[$orgId]['true_count']++;
                        } else {
                            $orgWorkingDayStats[$orgId]['false_count']++;
                        }
                        
                        $orgWorkingDayStats[$orgId]['users'][] = [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'has_working_today' => $isWorkingDay,
                        ];
                    }
                    
                    // Log specific users for debugging
                    if (in_array($user->email, ['santosh@chetaru.com', 'mousam@chetaru.com'])) {
                        Log::info('OneSignal tag sync - specific user', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'org_id' => $user->orgId,
                            'org_name' => $orgName,
                            'has_working_today' => $isWorkingDay,
                            'working_days' => $user->organisation?->working_days,
                        ]);
                    }
                } else {
                    $stats['failed']++;
                    $orgStats[$orgId]['failed']++;
                    Log::warning('OneSignal tag sync failed', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'org_id' => $user->orgId,
                    ]);
                }
                
                // Add small delay to avoid rate limiting (50ms between users)
                usleep(50000); // 50ms = 0.05 seconds
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('OneSignal tag sync exception', [
                    'user_id' => $user->id,
                    'email' => $user->email ?? null,
                    'org_id' => $user->orgId ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display stats
        $this->info('✅ Sync completed:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users', $stats['total']],
                ['Synced', $stats['synced']],
                ['Failed', $stats['failed']],
                ['Skipped', $stats['skipped']],
            ]
        );
        
        // Display organization-wise statistics
        $this->newLine();
        $this->info('📊 Organization-wise User Count:');
        $orgTableData = [];
        foreach ($orgStats as $orgStat) {
            $orgTableData[] = [
                $orgStat['org_id'],
                $orgStat['org_name'],
                $orgStat['total_users'],
                $orgStat['synced'],
                $orgStat['failed'],
            ];
        }
        $this->table(
            ['Org ID', 'Org Name', 'Total Users', 'Synced', 'Failed'],
            $orgTableData
        );
        
        // Display working day statistics
        if (!empty($orgWorkingDayStats)) {
            $this->newLine();
            $this->info('📅 Organization-wise Working Day Status:');
            $workingDayTableData = [];
            foreach ($orgWorkingDayStats as $orgStat) {
                $workingDaysStr = is_array($orgStat['working_days']) 
                    ? implode(', ', $orgStat['working_days'])
                    : ($orgStat['working_days'] ?? 'All days');
                    
                $workingDayTableData[] = [
                    $orgStat['org_id'],
                    $orgStat['org_name'],
                    $workingDaysStr,
                    $orgStat['true_count'],
                    $orgStat['false_count'],
                ];
            }
            $this->table(
                ['Org ID', 'Org Name', 'Working Days', 'True Count', 'False Count'],
                $workingDayTableData
            );
            
            // Log detailed information for organizations with issues
            foreach ($orgWorkingDayStats as $orgStat) {
                if ($orgStat['true_count'] > 0 && $orgStat['false_count'] > 0) {
                    // Organization has mixed results - log details
                    Log::warning('Organization has mixed has_working_today values', [
                        'org_id' => $orgStat['org_id'],
                        'org_name' => $orgStat['org_name'],
                        'working_days' => $orgStat['working_days'],
                        'true_count' => $orgStat['true_count'],
                        'false_count' => $orgStat['false_count'],
                        'users' => $orgStat['users'],
                    ]);
                }
            }
        }

        Log::info('Cron: SyncAllUserTagsToOneSignal completed', [
            'stats' => $stats,
            'org_stats' => $orgStats,
            'org_working_day_stats' => $orgWorkingDayStats,
        ]);

        return 0;
    }
}
