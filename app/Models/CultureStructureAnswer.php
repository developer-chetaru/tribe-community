<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CultureStructureAnswer extends Model
{
    protected $table = 'culture_structure_answers';

    protected $fillable = [
        'userId',
        'orgId',
        'question_id',
        'option_id',
        'culture_type_id',
        'assessment_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function question()
    {
        return $this->belongsTo(CultureStructureQuestion::class, 'question_id');
    }

    public function option()
    {
        return $this->belongsTo(CultureStructureOption::class, 'option_id');
    }

    public function cultureType()
    {
        return $this->belongsTo(CultureStructureType::class, 'culture_type_id');
    }
}
