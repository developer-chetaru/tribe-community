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
        'short_description',
        'long_description',
        'status',
    ];

    public function cotAnswers()
    {
        return $this->hasMany(CotAnswer::class, 'cot_role_map_option_id', 'id');
    }
}
