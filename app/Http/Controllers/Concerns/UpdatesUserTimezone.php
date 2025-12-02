<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait UpdatesUserTimezone
{
    /**
     * Update user's timezone from request if provided and different.
     * 
     * Checks for timezone in:
     * 1. Request header: X-Timezone or Timezone
     * 2. Request body/query: timezone
     * 3. If blank, detects from IP geolocation
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

            // Get timezone from various sources
            $timezone = $request->header('X-Timezone') 
                ?? $request->header('Timezone')
                ?? $request->input('timezone')
                ?? $request->query('timezone');

            // If timezone is blank or empty, try to detect from IP
            if (empty($timezone) || $timezone === 'null' || $timezone === 'undefined') {
                $timezone = $this->detectTimezoneFromIP($request);
            }

            if (empty($timezone)) {
                return;
            }

            // Validate timezone
            if (!in_array($timezone, timezone_identifiers_list())) {
                Log::warning("Invalid timezone provided: {$timezone} for user {$user->id}");
                return;
            }

            // Update only if different or if user has no timezone set
            $currentTimezone = $user->timezone;
            if (empty($currentTimezone) || $currentTimezone !== $timezone) {
                $user->timezone = $timezone;
                $user->save();
                
                Log::info("Updated timezone for user {$user->id} from '{$currentTimezone}' to '{$timezone}'");
            }
        } catch (\Exception $e) {
            Log::error("Error updating user timezone: " . $e->getMessage());
            // Don't throw - this is a non-critical update
        }
    }

    /**
     * Detect timezone from user's IP address using geolocation
     * 
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function detectTimezoneFromIP($request)
    {
        try {
            // Get real IP address (handle proxies/load balancers)
            $ipAddress = $request->header('X-Forwarded-For');
            if ($ipAddress) {
                // X-Forwarded-For can contain multiple IPs, get the first one
                $ipAddress = trim(explode(',', $ipAddress)[0]);
            }
            
            if (!$ipAddress) {
                $ipAddress = $request->header('X-Real-IP');
            }
            
            if (!$ipAddress) {
                $ipAddress = $request->ip();
            }
            
            // Skip localhost IPs
            if (!$ipAddress || in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                Log::info("Skipping timezone detection for localhost IP: {$ipAddress}");
                return null;
            }

            // Try ipapi.co first (free, no API key needed)
            try {
                $response = Http::timeout(3)->get("https://ipapi.co/{$ipAddress}/timezone/");
                
                if ($response->successful()) {
                    $timezone = trim($response->body());
                    
                    // Validate timezone
                    if ($timezone && in_array($timezone, timezone_identifiers_list())) {
                        Log::info("Detected timezone from IP {$ipAddress}: {$timezone}");
                        return $timezone;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("ipapi.co failed for IP {$ipAddress}: " . $e->getMessage());
            }

            // Fallback: Try ipapi.co JSON endpoint (more reliable)
            try {
                $response = Http::timeout(3)->get("https://ipapi.co/{$ipAddress}/json/");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $timezone = $data['timezone'] ?? null;
                    
                    // Validate timezone
                    if ($timezone && in_array($timezone, timezone_identifiers_list())) {
                        Log::info("Detected timezone from IP {$ipAddress} (JSON): {$timezone}");
                        return $timezone;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("ipapi.co JSON failed for IP {$ipAddress}: " . $e->getMessage());
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error detecting timezone from IP: " . $e->getMessage());
            return null;
        }
    }
}

