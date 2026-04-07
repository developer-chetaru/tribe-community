<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use App\Services\Billing\StripeService;
use App\Services\OneSignalService;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     * Cancels Stripe subscriptions and removes OneSignal subscriptions before deletion.
     */
    public function delete(User $user): void
    {
        // Cancel Stripe subscriptions so no further charges occur after account is deleted
        try {
            app(StripeService::class)->cancelSubscriptionsForUser($user);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Stripe cancel on user delete failed (continuing with delete)', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Remove user from OneSignal (push/email subscriptions) so they stop receiving notifications
        try {
            app(OneSignalService::class)->deleteUserByExternalId($user->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OneSignal remove on user delete failed (continuing with delete)', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $user->deleteProfilePhoto();
        
        // Delete tokens if they exist
        if ($user->tokens && $user->tokens->count() > 0) {
            $user->tokens->each->delete();
        }
        
        $user->delete();
    }
}
