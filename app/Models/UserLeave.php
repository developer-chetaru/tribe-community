<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLeave extends Model
{
    protected $table = 'user_leave_management'; 

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'resume_date',
        'leave_status',
    ];

    public $timestamps = true; 
  
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
