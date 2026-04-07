<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use App\Models\User;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeletePendingPaymentUsers extends Command
{
    protected $signature = 'users:delete-pending-payment
                            {--days=20 : Delete users in pending_payment for this many days}
                            {--dry-run : Only show users that would be deleted}';

    protected $description = 'Auto-delete basecamp users in pending_payment status after N days without successful payment';

    public function handle(OneSignalService $oneSignalService): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $this->info($dryRun
            ? "Dry run: finding pending_payment users older than {$days} days..."
            : "Deleting pending_payment users older than {$days} days...");

        $users = User::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<=', $cutoff)
            ->whereHas('roles', fn ($q) => $q->where('name', 'basecamp'))
            ->whereDoesntHave('organisation')
            ->get()
            ->filter(function (User $user) {
                $hasPaidInvoice = Invoice::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->where('status', 'paid')
                    ->exists();

                if ($hasPaidInvoice) {
                    return false;
                }

                $hasActiveSubscription = SubscriptionRecord::where('user_id', $user->id)
                    ->where('tier', 'basecamp')
                    ->where('status', 'active')
                    ->where('current_period_end', '>', now())
                    ->exists();

                return !$hasActiveSubscription;
            });

        if ($users->isEmpty()) {
            $this->info('No pending_payment users found to delete.');
            return self::SUCCESS;
        }

        $this->info("Found {$users->count()} pending_payment user(s).");

        foreach ($users as $user) {
            $label = "User {$user->id} ({$user->email})";

            if ($dryRun) {
                $this->line("[dry-run] Would delete {$label}");
                continue;
            }

            try {
                // Remove OneSignal user/subscriptions first so push/email stops immediately.
                $oneSignalRemoved = $oneSignalService->deleteUserByExternalId($user->id);
                if (!$oneSignalRemoved) {
                    Log::warning('OneSignal user removal failed before local deletion', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);
                }

                DB::transaction(function () use ($user) {
                    // Extra cleanup before user deletion. Most related rows are also cascade-deleted by FKs.
                    SubscriptionRecord::where('user_id', $user->id)->delete();
                    Invoice::where('user_id', $user->id)->where('status', '!=', 'paid')->delete();
                    $user->delete();
                });

                $this->info("Deleted {$label}");
                Log::info('Auto-deleted pending_payment user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } catch (\Throwable $e) {
                $this->error("Failed to delete {$label}: {$e->getMessage()}");
                Log::error('Failed auto-delete pending_payment user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
