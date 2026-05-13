<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotAnswer extends Model
{
    protected $table = 'cot_answers'; 
    protected $fillable = [
        'userId',
        'orgId',
        'questionId',
        'optionId',
        'answer',
        'cot_role_map_option_id',
        'status',
        'created_at',
        'updated_at',
    ];

    public function question()
    {
        return $this->belongsTo(CotQuestion::class, 'questionId', 'id');
    }

    public function roleMapOption()
    {
        return $this->belongsTo(CotRoleMapOption::class, 'cot_role_map_option_id', 'id');
    }
}
