<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait UpdatesUserTimezone
{
    /**
     * Update user's timezone from request.
     * 
     * Checks for timezone in:
     * 1. Request header: X-Timezone or Timezone
     * 2. Request body/query: timezone
     * 
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    protected function updateUserTimezoneIfNeeded($request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return;
            }

            // Get timezone from request
            $timezone = $request->header('X-Timezone') 
                ?? $request->header('Timezone')
                ?? $request->input('timezone')
                ?? $request->query('timezone');

            if (empty($timezone) || $timezone === 'null' || $timezone === 'undefined') {
                return;
            }

            // Validate timezone
            if (!in_array($timezone, timezone_identifiers_list())) {
                Log::warning("Invalid timezone provided: {$timezone} for user {$user->id}");
                return;
            }

            // Update timezone
            if ($user->timezone !== $timezone) {
                $user->timezone = $timezone;
                $user->save();
                
                Log::info("Updated timezone for user {$user->id} to '{$timezone}'");
            }
        } catch (\Exception $e) {
            Log::error("Error updating user timezone: " . $e->getMessage());
        }
    }
}

