<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotQuestion extends Model
{
    use HasFactory;

    protected $table = 'cot_questions';

    protected $fillable = [
        'question',
        'order',
        'status',
    ];

    public function roleMapOptions()
    {
        return $this->hasMany(CotRoleMapOption::class, 'categoryId', 'id');
    }
}

