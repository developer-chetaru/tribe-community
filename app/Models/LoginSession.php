<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LoginSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'token_id',
        'platform',
        'device_type',
        'device_id',
        'device_name',
        'os_name',
        'os_version',
        'browser_name',
        'browser_version',
        'ip_address',
        'user_agent',
        'country',
        'city',
        'timezone',
        'login_at',
        'logout_at',
        'session_duration_seconds',
        'fcm_token',
        'additional_data',
        'status',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'session_duration_seconds' => 'integer',
        'additional_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the login session
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by platform
     */
    public function scopeForPlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('login_at', [$startDate, $endDate]);
    }

    /**
     * Calculate and update session duration
     */
    public function calculateDuration()
    {
        if ($this->logout_at && $this->login_at) {
            $this->session_duration_seconds = $this->login_at->diffInSeconds($this->logout_at);
            $this->save();
        }
    }

    /**
     * Get session duration in seconds (calculated on the fly if not stored)
     */
    public function getDurationInSecondsAttribute()
    {
        if ($this->session_duration_seconds !== null) {
            return $this->session_duration_seconds;
        }
        
        // Calculate on the fly if logout_at exists
        if ($this->logout_at && $this->login_at) {
            return $this->login_at->diffInSeconds($this->logout_at);
        }
        
        // If still active, calculate from login to now
        if ($this->status === 'active' && $this->login_at) {
            return $this->login_at->diffInSeconds(now());
        }
        
        return null;
    }

    /**
     * Get formatted session duration (with auto-calculation)
     */
    public function getFormattedDurationAttribute()
    {
        $seconds = $this->duration_in_seconds;
        
        if ($seconds === null) {
            return 'N/A';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }
}
