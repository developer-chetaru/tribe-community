<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CotRoleMapOption extends Model
{
    use HasFactory;

    protected $table = 'cot_role_map_options';

    protected $fillable = [
        'maper',
        'maper_key',
        'categoryId',
        'role_description_id',
        'short_description',
        'long_description',
        'status',
    ];

    public function roleDescription()
    {
        return $this->belongsTo(CotTeamRoleDescription::class, 'role_description_id', 'id');
    }

    public function cotAnswers()
    {
        return $this->hasMany(CotAnswer::class, 'cot_role_map_option_id', 'id');
    }
}
