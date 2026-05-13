<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotFeedbackStatus extends Model
{
    use HasFactory;

    protected $table = 'iot_feedback_status';

    protected $fillable = [
        'title',
        'status',
    ];
}

