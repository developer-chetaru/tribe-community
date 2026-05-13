<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TribeometerAnswer extends Model
{
    protected $table = 'tribeometer_answers';

    protected $fillable = [
        'userId',
        'orgId',
        'questionId',
        'optionId',
        'answer',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId');
    }

    public function question()
    {
        return $this->belongsTo(TribeometerQuestion::class, 'questionId');
    }

    public function option()
    {
        return $this->belongsTo(TribeometerOption::class, 'optionId');
    }
}
