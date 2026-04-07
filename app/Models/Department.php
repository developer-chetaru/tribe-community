<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'department',
        'numberOfEmployees',
        'office_id',       
        'organisation_id', 
        'all_department_id', 
        'status',
    ];

    public function allDepartment()
    {
        return $this->belongsTo(AllDepartment::class, 'all_department_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

 	public function users()
	{
    	return $this->hasMany(User::class, 'departmentId');
	}
}
