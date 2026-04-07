<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'org_id',
    ];

    public function organisations()
    {
        return $this->hasMany(Organisation::class, 'industry_id');
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class, 'industry_id');
    }
}
