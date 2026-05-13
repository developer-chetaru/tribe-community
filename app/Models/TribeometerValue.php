<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TribeometerValue extends Model
{
    protected $table = 'tribeometer_values';

    protected $fillable = [
        'value_key',
        'title',
        'description',
        'order',
        'status',
    ];

    public function questions()
    {
        return $this->hasMany(TribeometerQuestion::class, 'value_id');
    }

    public function results()
    {
        return $this->hasMany(TribeometerResult::class, 'value_id');
    }
}
