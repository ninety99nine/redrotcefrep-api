<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\RateType;
use App\Casts\Percentage;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderFee extends BaseModel
{
    use HasFactory;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    public static function FEE_RATE_TYPES(): array
    {
        return array_map(fn($method) => $method->value, RateType::cases());
    }

    protected $casts = [
        'amount' => Money::class
    ];

    protected $tranformableCasts = [
        'percentage_rate' => Percentage::class,
    ];

    protected $fillable = [
        'name', 'rate_type', 'amount', 'percentage_rate', 'currency', 'order_id', 'store_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
