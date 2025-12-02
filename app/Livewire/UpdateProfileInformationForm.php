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
        
        // Auto-detect timezone from IP (force update based on current location)
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
                
                $currentTimezone = $user->timezone ?? 'not set';
                Log::info("Profile mount: User {$user->id} | IP: {$ipAddress} | Current timezone: {$currentTimezone}");
                Log::info("Profile mount: Headers - X-Forwarded-For: " . ($request->header('X-Forwarded-For') ?? 'none') . " | X-Real-IP: " . ($request->header('X-Real-IP') ?? 'none'));
                
                // Skip localhost IPs only if we can't get real IP from headers
                if (!$ipAddress || in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
                    // Try one more time with all headers
                    $allHeaders = $request->headers->all();
                    Log::info("Profile mount: All headers: " . json_encode($allHeaders));
                    
                    // If still localhost and no headers, skip
                    if (in_array($ipAddress, ['127.0.0.1', '::1', 'localhost']) && 
                        !$request->header('X-Forwarded-For') && 
                        !$request->header('X-Real-IP')) {
                        Log::info("Profile mount: Localhost detected with no proxy headers, skipping IP detection");
                        return;
                    }
                }
                
                // Always try to detect timezone from IP and update
                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->get("https://ipapi.co/{$ipAddress}/json/");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $detectedTimezone = $data['timezone'] ?? null;
                    $country = $data['country_name'] ?? 'Unknown';
                    $city = $data['city'] ?? 'Unknown';
                    
                    Log::info("Profile mount: API Response - Timezone: {$detectedTimezone}, Country: {$country}, City: {$city}");
                    
                    if ($detectedTimezone && in_array($detectedTimezone, timezone_identifiers_list())) {
                        // ALWAYS update to match current location (force update)
                        $oldTimezone = $user->timezone;
                        $user->timezone = $detectedTimezone;
                        $user->save();
                        
                        // Refresh user to get updated timezone
                        $user->refresh();
                        
                        // Update state to reflect current location
                        $this->state['timezone'] = $detectedTimezone;
                        
                        // Also update the user property so blade template can see it
                        $this->user = $user;
                        
                        // Dispatch event to update Alpine.js timezone field
                        $this->dispatch('timezone-updated', timezone: $detectedTimezone);
                        
                        Log::info("Profile mount: ✅ UPDATED timezone for user {$user->id} | IP: {$ipAddress} | From: {$oldTimezone} | To: {$detectedTimezone} | Location: {$city}, {$country}");
                    } else {
                        Log::warning("Profile mount: ❌ Invalid timezone from IP API: " . ($detectedTimezone ?? 'null') . " | Full response: " . json_encode($data));
                    }
                } else {
                    $errorBody = $response->body();
                    Log::warning("Profile mount: ❌ IP API request failed | Status: " . $response->status() . " | Response: " . substr($errorBody, 0, 200));
                }
            } catch (\Exception $e) {
                Log::error("Profile mount: ❌ Exception: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine());
            }
        } else {
            Log::warning("Profile mount: No authenticated user found");
        }
        
        // Ensure timezone is in state and user is refreshed
        $user = Auth::user();
        if ($user) {
            $user->refresh(); // Refresh to get latest timezone
            $this->user = $user; // Update component's user property
            if (!isset($this->state['timezone']) || empty($this->state['timezone'])) {
                $this->state['timezone'] = $user->timezone ?? '';
            }
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

