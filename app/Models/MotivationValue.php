<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivationValue extends Model
{
    protected $table = 'motivation_values';

    protected $fillable = [
        'value_key',
        'title',
        'description',
        'characteristics',
        'management_strategy',
        'order',
        'status',
    ];

    public function options()
    {
        return $this->hasMany(MotivationOption::class, 'motivation_value_id');
    }

    public function results()
    {
        return $this->hasMany(MotivationResult::class, 'motivation_value_id');
    }
}
