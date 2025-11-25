<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = [
        'organisation_id',
        'name',
        'no_of_employees',
        'phone',
      	'country_code',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'is_head_office'
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'office_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'officeId');
    }

	public function staff()
	{
    	return $this->hasMany(User::class, 'officeId');
	}
}
