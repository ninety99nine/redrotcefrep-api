<?php

namespace App\Models;

use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoBillingSchedule extends BaseModel
{
    use HasFactory;

    protected $casts = [
        'active' => 'boolean',
        'next_attempt_date' => 'datetime'
    ];

    protected $fillable = [
        'active', 'pricing_plan_id', 'user_id', 'store_id', 'payment_method_id',
        'next_attempt_date', 'attempts', 'total_successful_attempts', 'total_failed_attempts'
    ];

    // Scopes

    public function scopeSearch($query, $searchWord)
    {
        return $query->whereHas('store', function($query) use ($searchWord) {
                    $query->search($searchWord);
                })->orWhereHas('pricingPlan', function($query) use ($searchWord) {
                    $query->search($searchWord);
                });
    }

    public function scopeActive($query)
    {
        return $query->where('active', '1');
    }

    public function scopeInactive($query)
    {
        return $query->where('active', '0');
    }

    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function pricingPlan()
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
