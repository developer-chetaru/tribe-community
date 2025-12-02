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
        
        // Auto-detect timezone from IP if user doesn't have one
        $user = Auth::user();
        if ($user && (empty($user->timezone) || $user->timezone === null || trim($user->timezone) === '')) {
            try {
                $request = request();
                $ipAddress = $request->ip();
                
                Log::info("Profile mount: Checking timezone for user {$user->id}, IP: {$ipAddress}, Current timezone: " . ($user->timezone ?? 'null'));
                
                // For localhost, use a default or try to get real IP from headers
                if (in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                    // Try to get real IP from headers
                    $ipAddress = $request->header('X-Forwarded-For') 
                        ?? $request->header('X-Real-IP')
                        ?? $request->ip();
                    
                    // If still localhost, use a test IP or default timezone
                    if (in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                        Log::info("Profile mount: Localhost detected, using default timezone Asia/Kolkata");
                        $user->timezone = 'Asia/Kolkata';
                        $user->save();
                        $this->state['timezone'] = 'Asia/Kolkata';
                        return;
                    }
                }
                
                if ($ipAddress) {
                    $response = \Illuminate\Support\Facades\Http::timeout(5)
                        ->get("https://ipapi.co/{$ipAddress}/json/");
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        $timezone = $data['timezone'] ?? null;
                        
                        if ($timezone && in_array($timezone, timezone_identifiers_list())) {
                            $user->timezone = $timezone;
                            $user->save();
                            
                            // Update state
                            $this->state['timezone'] = $timezone;
                            
                            Log::info("Profile mount: Auto-detected timezone for user {$user->id} from IP {$ipAddress}: {$timezone}");
                        } else {
                            Log::warning("Profile mount: Invalid timezone from IP API: " . ($timezone ?? 'null'));
                        }
                    } else {
                        Log::warning("Profile mount: IP API request failed: " . $response->status());
                    }
                }
            } catch (\Exception $e) {
                Log::error("Profile mount: Failed to detect timezone from IP: " . $e->getMessage());
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

