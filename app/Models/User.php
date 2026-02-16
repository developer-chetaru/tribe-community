<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Notifications\CustomResetPasswordNotification; 
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasProfilePhoto, TwoFactorAuthenticatable, HasRoles;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'profile_photo_path', 'EIScore',
        'fcmToken', 'lastHIDate', 'onLeave', 'hptmEvaluationScore', 'HI_include_sunday',
        'HI_include_saturday', 'working_monday', 'working_tuesday', 'working_wednesday',
        'working_thursday', 'working_friday', 'orgId', 'departmentId', 'officeId', 'first_login_at',
        'deviceType', 'deviceId', 'contact', 'status', 'phone', 'hptmScore', 'last_login_at',
        'time_spent_on_app', 'country_code', 'timezone', 'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = ['profile_photo_url'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', 
   		'last_login_at' => 'datetime',
  		'first_login_at' => 'datetime',
        // Status is ENUM: 'pending_payment', 'active_unverified', 'active_verified', 'suspended', 'cancelled', 'inactive'
    ];

 	public function getNameAttribute()
	{
    	if ($this->first_name || $this->last_name) {
        	return trim("{$this->first_name} {$this->last_name}");
    	}
    	return $this->email;
	}

	public function getProfilePhotoUrlAttribute()
	{
    	return $this->profile_photo_path
                ? Storage::url($this->profile_photo_path)
                : $this->defaultProfilePhotoUrl();
	}

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }


    public function organisation()
    {
        return $this->belongsTo(\App\Models\Organisation::class, 'orgId');
    }

    public function office() {
        return $this->belongsTo(Office::class, 'officeId');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'departmentId', 'id'); 
    }

    public function cotAnswers()
    {
        return $this->hasMany(CotAnswer::class, 'userId');
    }

    public function sotMotivationAnswers()
    {
        return $this->hasMany(SotMotivationAnswer::class, 'userId');
    }
  
	public function happyindexes()
	{
    	return $this->hasMany(\App\Models\HappyIndex::class, 'user_id', 'id');
	}
  
	public function getTimeSpentOnAppFormattedAttribute()
	{
    	if (empty($this->time_spent_on_app)) {
        	return '-';
    	}

    	$hours = floor($this->time_spent_on_app / 3600);
    	$minutes = floor(($this->time_spent_on_app / 60) % 60);
    	$seconds = $this->time_spent_on_app % 60;

    	return "{$hours}h {$minutes}m {$seconds}s";
	}

    public function sendPasswordResetNotification($token)
    {
     
        $orgName = $this->organisation ? $this->organisation->name : 'Organisation';

        $this->notify(new CustomResetPasswordNotification($token, $orgName));
    }
}
