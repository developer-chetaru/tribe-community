<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CultureStructureQuestion extends Model
{
    protected $table = 'culture_structure_questions';

    protected $fillable = [
        'question',
        'order',
        'status',
    ];

    public function options()
    {
        return $this->hasMany(CultureStructureOption::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(CultureStructureAnswer::class, 'question_id');
    }
}
