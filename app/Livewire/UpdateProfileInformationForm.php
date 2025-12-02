<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm as BaseUpdateProfileInformationForm;

class UpdateProfileInformationForm extends BaseUpdateProfileInformationForm
{
    /**
     * Prepare the component state.
     *
     * @return void
     */
    public function mount()
    {
        parent::mount();
        
        // Auto-detect timezone from IP (always check and update if needed)
        $user = Auth::user();
        if ($user) {
            try {
                $request = request();
                
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
                
                Log::info("Profile mount: Checking timezone for user {$user->id}, IP: {$ipAddress}, Current timezone: " . ($user->timezone ?? 'null'));
                
                // Skip localhost IPs
                if (!$ipAddress || in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                    Log::info("Profile mount: Localhost detected, skipping IP detection");
                    return;
                }
                
                // Always try to detect timezone from IP (update if different or empty)
                $response = \Illuminate\Support\Facades\Http::timeout(5)
                    ->get("https://ipapi.co/{$ipAddress}/json/");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $detectedTimezone = $data['timezone'] ?? null;
                    
                    if ($detectedTimezone && in_array($detectedTimezone, timezone_identifiers_list())) {
                        // Update if timezone is empty or different
                        if (empty($user->timezone) || $user->timezone !== $detectedTimezone) {
                            $user->timezone = $detectedTimezone;
                            $user->save();
                            
                            // Update state
                            $this->state['timezone'] = $detectedTimezone;
                            
                            Log::info("Profile mount: Updated timezone for user {$user->id} from IP {$ipAddress}: {$detectedTimezone} (was: " . ($user->getOriginal('timezone') ?? 'null') . ")");
                        } else {
                            Log::info("Profile mount: Timezone already set correctly for user {$user->id}: {$detectedTimezone}");
                        }
                    } else {
                        Log::warning("Profile mount: Invalid timezone from IP API: " . ($detectedTimezone ?? 'null'));
                    }
                } else {
                    Log::warning("Profile mount: IP API request failed: " . $response->status() . " - " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Profile mount: Failed to detect timezone from IP: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            }
        }
        
        // Add timezone to state if not already present
        if (!isset($this->state['timezone'])) {
            $user = Auth::user();
            $this->state['timezone'] = $user->timezone ?? '';
        }
    }

    /**
     * Update user timezone from browser or IP
     *
     * @param string|null $timezone
     * @return void
     */
    public function updateTimezone($timezone = null)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return;
            }

            // If timezone is blank/null, try to detect from IP
            if (empty($timezone) || $timezone === 'null' || $timezone === 'undefined') {
                $request = request();
                $ipAddress = $request->ip();
                
                if ($ipAddress && !in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                    try {
                        $response = \Illuminate\Support\Facades\Http::timeout(3)
                            ->get("https://ipapi.co/{$ipAddress}/json/");
                        
                        if ($response->successful()) {
                            $data = $response->json();
                            $timezone = $data['timezone'] ?? null;
                            
                            if ($timezone && in_array($timezone, timezone_identifiers_list())) {
                                $user->timezone = $timezone;
                                $user->save();
                                
                                // Update state
                                $this->state['timezone'] = $timezone;
                                
                                Log::info("Auto-detected timezone for user {$user->id} from IP {$ipAddress}: {$timezone}");
                                return;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to detect timezone from IP {$ipAddress}: " . $e->getMessage());
                    }
                }
                return;
            }

            // Validate timezone
            if (!in_array($timezone, timezone_identifiers_list())) {
                Log::warning("Invalid timezone provided: {$timezone} for user {$user->id}");
                return;
            }

            // Update only if different or if user has no timezone set
            if (empty($user->timezone) || $user->timezone !== $timezone) {
                $user->timezone = $timezone;
                $user->save();
                
                // Update state
                $this->state['timezone'] = $timezone;
                
                Log::info("Updated timezone for user {$user->id} to '{$timezone}' from profile page");
            }
        } catch (\Exception $e) {
            Log::error("Error updating user timezone: " . $e->getMessage());
        }
    }
}

