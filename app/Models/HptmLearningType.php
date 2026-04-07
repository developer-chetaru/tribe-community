<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HptmLearningType extends Model
{
    protected $table = 'hptm_learning_types';

    protected $fillable = [
        'title',
        'score',
        'priority',
        'created_at',
        'updated_at',
    ];

    public function checklists()
    {
        return $this->hasMany(HptmLearningChecklist::class, 'output', 'id');
    }
}
