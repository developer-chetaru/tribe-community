<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotTeamRoleResult extends Model
{
    use HasFactory;

    protected $table = 'cot_team_role_results';

    protected $fillable = [
        'userId',
        'orgId',
        'role_key',
        'score',
        'preference_rank',
        'assessment_date',
    ];

    protected $casts = [
        'assessment_date' => 'date',
    ];

    public function roleDescription()
    {
        return $this->belongsTo(CotTeamRoleDescription::class, 'role_key', 'role_key');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId', 'id');
    }
}

