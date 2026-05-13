<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CultureStructureResult extends Model
{
    protected $table = 'culture_structure_results';

    protected $fillable = [
        'userId',
        'orgId',
        'culture_type_id',
        'type_key',
        'percentage',
        'score',
        'assessment_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function cultureType()
    {
        return $this->belongsTo(CultureStructureType::class, 'culture_type_id');
    }
}
