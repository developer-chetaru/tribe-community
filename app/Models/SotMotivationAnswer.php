<?php
// app/Models/SotMotivationAnswer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SotMotivationAnswer extends Model
{
    protected $fillable = [
        'userId',
        'orgId',
        'questionId',
        'optionId',
        'answer',
        'status',
    ];
}
