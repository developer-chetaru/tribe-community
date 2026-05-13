<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CultureStructureIndividualUserStatus extends Model
{
    protected $table = 'culture_structure_individual_user_status';

    protected $fillable = [
        'userid',
        'orgId',
        'date',
        'completeStatus',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
