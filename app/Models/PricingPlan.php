<?php

namespace App\Models;

use App\Casts\Money;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Casts\JsonToArray;
use App\Enums\PlatformType;
use App\Models\Base\BaseModel;
use App\Enums\PricingPlanType;
use App\Enums\PricingPlanBillingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PricingPlan extends BaseModel
{
    use HasFactory;

    public static function TYPES(): array
    {
        return array_map(fn($status) => $status->value, PricingPlanType::cases());
    }

    public static function BILLING_TYPES(): array
    {
        return array_map(fn($status) => $status->value, PricingPlanBillingType::cases());
    }

    public static function PLATFORM_TYPES(): array
    {
        return array_map(fn($status) => $status->value, PlatformType::cases());
    }

    /**
     *  Magic Numbers
     */
    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    const DESCRIPTION_MIN_CHARACTERS = 3;
    const DESCRIPTION_MAX_CHARACTERS = 200;

    protected $casts = [
        'active' => 'boolean',
        'price' => Money::class,
        'supports_web' => 'boolean',
        'supports_ussd' => 'boolean',
        'supports_mobile' => 'boolean',
        'metadata' => JsonToArray::class,
        'features' => JsonToArray::class,
    ];

    protected $tranformableCasts = [
        'currency' => Currency::class,
        'discount_percentage_rate' => Percentage::class,
    ];

    protected $fillable = [
        'active', 'name', 'type', 'description', 'billing_type', 'currency', 'price',
        'discount_percentage_rate', 'max_auto_billing_attempts', 'supports_ussd',
        'supports_mobile', 'supports_web', 'metadata', 'features', 'position',
        'auto_billing_disabled_sms_message'
    ];

    public function scopeSearch($query, $searchWord)
    {
        return $query->where('name', 'like', "%{$searchWord}%")
                     ->orWhere('type', 'like', "%{$searchWord}%");
    }
}
