<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CultureStructureOption extends Model
{
    protected $table = 'culture_structure_options';

    protected $fillable = [
        'question_id',
        'culture_type_id',
        'option_text',
        'order',
        'status',
    ];

    public function question()
    {
        return $this->belongsTo(CultureStructureQuestion::class, 'question_id');
    }

    public function cultureType()
    {
        return $this->belongsTo(CultureStructureType::class, 'culture_type_id');
    }
}
