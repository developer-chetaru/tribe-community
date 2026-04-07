<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DotValueList extends Model
{
    use HasFactory;

    protected $table = 'dot_value_list';

    protected $fillable = [
        'name',
        'value_url',
        'value_desc',
        'status',
    ];
}
