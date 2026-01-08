<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'phone',
   		'country_code',
        'turnover',
        'profile_visibility',
        'working_days',
        'founded_year',
        'url',
        'progress_step',
        'appPaymentVersion',
        'status',
        'HI_include_saturday',
        'HI_include_sunday',
        'personaliseData',
        'image',
      	'industry_id',
        // Billing fields
        'admin_email',
        'admin_first_name',
        'admin_last_name',
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_postcode',
        'billing_country',
        'stripe_customer_id',
        'paypal_customer_id',
        'subscription_tier',
        'user_type',
    ];

    protected $casts = [
        'working_days' => 'array', 
    ];

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function departments()
    {
        return $this->hasMany(AllDepartment::class, 'organisation_id');
    }
  
  	public function indus()
    {
        return $this->belongsTo(\App\Models\Industry::class, 'industry_id', 'id');
    }

	public function users()
	{
    	return $this->hasMany(User::class, 'orgId');
	}

    /**
     * Get the subscription record (new billing system)
     */
    public function subscriptionRecord()
    {
        return $this->hasOne(SubscriptionRecord::class)->latestOfMany();
    }

    /**
     * Get all subscription records
     */
    public function subscriptionRecords()
    {
        return $this->hasMany(SubscriptionRecord::class);
    }

    /**
     * Alias for subscriptionRecord (backward compatibility)
     */
    public function subscription()
    {
        return $this->subscriptionRecord();
    }

    /**
     * Alias for subscriptionRecords (backward compatibility)
     */
    public function subscriptions()
    {
        return $this->subscriptionRecords();
    }

    /**
     * Get payment records
     */
    public function paymentRecords()
    {
        return $this->hasMany(PaymentRecord::class);
    }
}
