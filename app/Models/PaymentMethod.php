<?php

namespace App\Models;

use App\Casts\JsonToArray;
use App\Models\Base\BaseModel;
use App\Traits\Base\BaseTrait;
use App\Enums\PaymentMethodType;
use App\Traits\PaymentMethodTrait;
use App\Models\Pivots\StorePaymentMethod;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends BaseModel
{
    use HasFactory, BaseTrait, PaymentMethodTrait;

    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 40;
    const TYPE_MIN_CHARACTERS = 3;
    const TYPE_MAX_CHARACTERS = 40;

    public static function PAYMENT_METHOD_TYPES(): array
    {
        return array_map(fn($method) => $method->value, PaymentMethodType::cases());
    }

    protected $casts = [
        'active' => 'boolean',
        'countries' => JsonToArray::class,
        'currencies' => JsonToArray::class,
        'ussd_codes' => JsonToArray::class,
        'automated_verification' => 'boolean',
        'config_schema' => JsonToArray::class,
        'allowed_countries' => JsonToArray::class,
    ];

    protected $tranformableCasts = [];

    protected $fillable = [
        'active', 'name', 'type', 'automated_verification',
        'currencies', 'countries', 'allowed_countries',
        'ussd_codes', 'config_schema', 'position'
    ];

    /****************************
     *  SCOPES                  *
     ***************************/

    public function scopeSearch($query, $searchWord)
    {
        return $query->where('name', 'like', "%$searchWord%");
    }

    public function scopeActive($query)
    {
        return $query->where('active', '1');
    }

    /********************
     *  RELATIONSHIPS   *
     *******************/

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_payment_method', 'payment_method_id', 'store_id')
                    ->withPivot(StorePaymentMethod::VISIBLE_COLUMNS)
                    ->using(StorePaymentMethod::class)
                    ->as('store_payment_method');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /****************************
     *  ACCESSORS               *
     ***************************/

    protected $appends = [
        'image_url'
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => asset('/images/payment-method-logos/'.$this->type.'.jpg')
        );
    }
}
