<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotRiskPriority extends Model
{
    use HasFactory;

    protected $table = 'iot_risk_priority';

    protected $fillable = [
        'title',
        'status',
    ];
}

