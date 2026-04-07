<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TimezoneService
{
    /**
     * Get timezone from latitude and longitude
     * 
     * @param float $latitude
     * @param float $longitude
     * @return string|null
     */
    public function getTimezoneFromLocation($latitude, $longitude)
    {
        try {
            // Try timeapi.io first (free, no API key needed)
            $response = Http::timeout(5)
                ->get("https://timeapi.io/api/TimeZone/coordinate", [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['timeZone'])) {
                    $timezone = $data['timeZone'];
                    
                    if (in_array($timezone, timezone_identifiers_list())) {
                        Log::info("Detected timezone from timeapi.io ({$latitude}, {$longitude}): {$timezone}");
                        return $timezone;
                    }
                }
            }
            
            // Fallback: Use Google Time Zone API (requires API key)
            if (env('GOOGLE_TIMEZONE_API_KEY')) {
                $googleTimezone = $this->getTimezoneFromGoogle($latitude, $longitude);
                if ($googleTimezone) {
                    return $googleTimezone;
                }
            }
            
            // Fallback: Use timezonedb.com (requires API key)
            if (env('TIMEZONEDB_API_KEY')) {
                return $this->getTimezoneFromTimeZoneDB($latitude, $longitude);
            }
            
            Log::warning("No timezone API available or all APIs failed for location ({$latitude}, {$longitude})");
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error getting timezone from location: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get timezone using TimeZoneDB API
     * 
     * @param float $latitude
     * @param float $longitude
     * @return string|null
     */
    protected function getTimezoneFromTimeZoneDB($latitude, $longitude)
    {
        try {
            $response = Http::timeout(5)
                ->get("https://api.timezonedb.com/v2.1/get-time-zone", [
                    'key' => env('TIMEZONEDB_API_KEY'),
                    'format' => 'json',
                    'by' => 'position',
                    'lat' => $latitude,
                    'lng' => $longitude,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'OK' && isset($data['zoneName'])) {
                    $timezone = $data['zoneName'];
                    
                    if (in_array($timezone, timezone_identifiers_list())) {
                        Log::info("Detected timezone from TimeZoneDB ({$latitude}, {$longitude}): {$timezone}");
                        return $timezone;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("TimeZoneDB API failed: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get timezone using Google Time Zone API
     * 
     * @param float $latitude
     * @param float $longitude
     * @return string|null
     */
    protected function getTimezoneFromGoogle($latitude, $longitude)
    {
        try {
            $timestamp = time();
            $response = Http::timeout(5)
                ->get("https://maps.googleapis.com/maps/api/timezone/json", [
                    'location' => "{$latitude},{$longitude}",
                    'timestamp' => $timestamp,
                    'key' => env('GOOGLE_TIMEZONE_API_KEY'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'OK' && isset($data['timeZoneId'])) {
                    $timezone = $data['timeZoneId'];
                    
                    if (in_array($timezone, timezone_identifiers_list())) {
                        return $timezone;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Google Time Zone API failed: " . $e->getMessage());
        }
        
        return null;
    }

}

