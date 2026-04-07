<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HptmPrinciple extends Model
{
    protected $table = 'hptm_principles';

    protected $fillable = [
        'title',
		'priority',
        'description',
        'created_at',
        'updated_at',
    ];

    public function questions()
    {
        return $this->hasMany(HptmTeamFeedbackQuestion::class, 'principleId', 'id');
    }
}
