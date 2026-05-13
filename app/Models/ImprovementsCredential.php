<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImprovementsCredential extends Model
{
    use HasFactory;

    protected $table = 'improvements_credentials';

    protected $fillable = [
        'email',
        'password_md5',
    ];
}

