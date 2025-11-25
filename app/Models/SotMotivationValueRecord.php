<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SotMotivationValueRecord extends Model
{
    protected $table = 'sot_motivation_value_records';

    protected $fillable = [
        'title',
        'description',
        'status',
    ];

    public function sotMotivationQuestionOptions()
    {
        return $this->hasMany(SotMotivationQuestionOption::class, 'category_id', 'id');
    }
}
