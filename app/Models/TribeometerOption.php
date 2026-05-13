<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TribeometerOption extends Model
{
    protected $table = 'tribeometer_options';

    protected $fillable = [
        'option_name',
        'value_score',
        'status',
    ];

    public function answers()
    {
        return $this->hasMany(TribeometerAnswer::class, 'optionId');
    }
}
