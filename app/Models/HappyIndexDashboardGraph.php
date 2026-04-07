<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HappyIndexDashboardGraph extends Model
{
    protected $table = 'happy_index_dashboard_graphs';
    protected $fillable = [
        'orgId',
        'officeId',
        'departmentId',
        'categoryId',
        'date',
        'with_weekend',
        'without_weekend',
        'status',
      	'userId'
    ];

    public $timestamps = true;

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'officeId');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'departmentId');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'categoryId');
    }
}
