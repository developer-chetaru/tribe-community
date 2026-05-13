<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TribeometerQuestion extends Model
{
    protected $table = 'tribeometer_questions';

    protected $fillable = [
        'question',
        'measure',
        'value_id',
        'status',
    ];

    public function value()
    {
        return $this->belongsTo(TribeometerValue::class, 'value_id');
    }

    public function answers()
    {
        return $this->hasMany(TribeometerAnswer::class, 'questionId');
    }
}
