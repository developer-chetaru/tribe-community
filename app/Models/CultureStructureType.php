<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CultureStructureType extends Model
{
    protected $table = 'culture_structure_types';

    protected $fillable = [
        'type_key',
        'title',
        'summary',
        'description',
        'characteristics',
        'icon',
        'order',
        'status',
    ];

    public function options()
    {
        return $this->hasMany(CultureStructureOption::class, 'culture_type_id');
    }

    public function results()
    {
        return $this->hasMany(CultureStructureResult::class, 'culture_type_id');
    }
}
