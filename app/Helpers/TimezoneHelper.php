<?php

namespace App\Helpers;

use Carbon\Carbon;
use DateTimeZone;

class TimezoneHelper
{
    /**
     * Default timezone to use when user timezone is invalid or empty
     */
    const DEFAULT_TIMEZONE = 'Europe/London';

    /**
     * Get a safe timezone for a user.
     * Returns a valid timezone identifier or the default timezone.
     * 
     * @param string|null $timezone User's timezone (can be null or empty)
     * @return string Valid timezone identifier
     */
    public static function getSafeTimezone(?string $timezone = null): string
    {
        // If timezone is empty, null, or not a valid identifier, return default
        if (empty($timezone) || $timezone === 'null' || $timezone === 'undefined') {
            return self::DEFAULT_TIMEZONE;
        }

        // Validate that it's a valid timezone identifier
        if (!in_array($timezone, timezone_identifiers_list())) {
            \Log::warning("Invalid timezone provided: '{$timezone}', using default: " . self::DEFAULT_TIMEZONE);
            return self::DEFAULT_TIMEZONE;
        }

        return $timezone;
    }

    /**
     * Get a safe timezone from a User model.
     * 
     * @param \App\Models\User|null $user
     * @return string Valid timezone identifier
     */
    public static function getUserTimezone($user = null): string
    {
        if (!$user) {
            return self::DEFAULT_TIMEZONE;
        }

        return self::getSafeTimezone($user->timezone);
    }

    /**
     * Create a Carbon instance with safe timezone handling.
     * 
     * @param string|null $time If null, uses current time
     * @param string|null $timezone User's timezone (can be null or empty)
     * @return Carbon
     */
    public static function carbon(?string $time = null, ?string $timezone = null): Carbon
    {
        $safeTimezone = self::getSafeTimezone($timezone);
        
        if ($time === null) {
            return Carbon::now($safeTimezone);
        }

        return Carbon::parse($time, $safeTimezone);
    }

    /**
     * Create a safe DateTimeZone instance.
     * 
     * @param string|null $timezone User's timezone (can be null or empty)
     * @return DateTimeZone
     */
    public static function dateTimeZone(?string $timezone = null): DateTimeZone
    {
        $safeTimezone = self::getSafeTimezone($timezone);
        return new DateTimeZone($safeTimezone);
    }

    /**
     * Set timezone on a Carbon instance safely.
     * 
     * @param Carbon $carbon
     * @param string|null $timezone User's timezone (can be null or empty)
     * @return Carbon
     */
    public static function setTimezone(Carbon $carbon, ?string $timezone = null): Carbon
    {
        $safeTimezone = self::getSafeTimezone($timezone);
        return $carbon->setTimezone($safeTimezone);
    }
}
