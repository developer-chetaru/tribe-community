<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    protected $fillable = [
        'name',
        'phone',
   		'country_code',
        'turnover',
        'profile_visibility',
        'working_days',
        'founded_year',
        'url',
        'progress_step',
        'appPaymentVersion',
        'status',
        'HI_include_saturday',
        'HI_include_sunday',
        'personaliseData',
        'image',
      	'industry_id',
    ];

    protected $casts = [
        'working_days' => 'array', 
    ];

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function departments()
    {
        return $this->hasMany(AllDepartment::class, 'organisation_id');
    }
  
  	public function indus()
    {
        return $this->belongsTo(\App\Models\Industry::class, 'industry_id', 'id');
    }

	public function users()
	{
    	return $this->hasMany(User::class, 'orgId');
	}

	public function subscription()
	{
    	return $this->hasOne(Subscription::class)->latestOfMany();
	}

	public function subscriptions()
	{
    	return $this->hasMany(Subscription::class);
	}
}
