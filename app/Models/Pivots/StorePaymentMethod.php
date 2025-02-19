<?php

namespace App\Models\Pivots;

use App\Models\Store;
use App\Models\MediaFile;
use App\Casts\JsonToArray;
use App\Models\PaymentMethod;
use App\Models\Base\BasePivot;
use App\Enums\RequestFileName;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StorePaymentMethod extends BasePivot
{
    use HasFactory;

    protected $table = 'store_payment_method';

    protected $casts = [
        'active' => 'boolean',
        'configs' => JsonToArray::class
    ];

    protected $fillable = [
        'id', 'active', 'custom_name', 'instruction', 'configs', 'position',
        'store_id', 'payment_method_id', 'created_at', 'updated_at'
    ];

    const VISIBLE_COLUMNS = [
        'id', 'active', 'custom_name', 'instruction', 'configs', 'position',
        'store_id', 'payment_method_id', 'created_at', 'updated_at'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function logo()
    {
        return $this->morphOne(MediaFile::class, 'mediable')->where('type', RequestFileName::STORE_PAYMENT_METHOD_LOGO->value);
    }

    public function photo()
    {
        return $this->morphOne(MediaFile::class, 'mediable')->where('type', RequestFileName::STORE_PAYMENT_METHOD_PHOTO->value);
    }
}
