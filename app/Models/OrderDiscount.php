<?php

namespace App\Models;

use App\Casts\Money;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDiscount extends BaseModel
{
    use HasFactory;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;

    protected $casts = [
        'amount' => Money::class,
    ];

    protected $fillable = [
        'name', 'amount', 'order_id', 'store_id'
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
