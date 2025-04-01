<?php

namespace App\Models;

use App\Casts\Money;
use App\Casts\Status;
use App\Enums\RateType;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Casts\JsonToArray;
use App\Traits\ItemLineTrait;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderPromotion extends BaseModel
{
    use HasFactory, ItemLineTrait;

    protected $table = 'order_promotions';

    public static function DISCOUNT_RATE_TYPES(): array
    {
        return array_map(fn($method) => $method->value, RateType::cases());
    }

    protected $casts = [
        'is_cancelled' => 'boolean',
        'end_datetime' => 'datetime',
        'offer_discount' => 'boolean',
        'start_datetime' => 'datetime',
        'offer_free_delivery' => 'boolean',
        'activate_using_code' => 'boolean',
        'hours_of_day' => JsonToArray::class,
        'discount_flat_rate' => Money::class,
        'minimum_grand_total' => Money::class,
        'detected_changes' => JsonToArray::class,
        'activate_for_new_customer' => 'boolean',
        'days_of_the_week' => JsonToArray::class,
        'activate_using_usage_limit' => 'boolean',
        'days_of_the_month' => JsonToArray::class,
        'months_of_the_year' => JsonToArray::class,
        'activate_using_end_datetime' => 'boolean',
        'activate_using_hours_of_day' => 'boolean',
        'cancellation_reasons' => JsonToArray::class,
        'activate_using_start_datetime' => 'boolean',
        'activate_for_existing_customer' => 'boolean',
        'activate_using_days_of_the_week' => 'boolean',
        'activate_using_days_of_the_month' => 'boolean',
        'activate_using_months_of_the_year' => 'boolean',
        'activate_using_minimum_grand_total' => 'boolean',
        'activate_using_minimum_total_products' => 'boolean',
        'activate_using_minimum_total_product_quantities' => 'boolean',
    ];

    protected $tranformableCasts = [
        'currency' => Currency::class,
        'is_cancelled' => Status::class,
        'offer_discount' => Status::class,
        'offer_free_delivery' => Status::class,
        'activate_using_code' => Status::class,
        'activate_for_new_customer' => Status::class,
        'activate_using_usage_limit' => Status::class,
        'activate_using_end_datetime' => Status::class,
        'activate_using_hours_of_day' => Status::class,
        'discount_percentage_rate' => Percentage::class,
        'activate_using_start_datetime' => Status::class,
        'activate_for_existing_customer' => Status::class,
        'activate_using_days_of_the_week' => Status::class,
        'activate_using_days_of_the_month' => Status::class,
        'activate_using_months_of_the_year' => Status::class,
        'activate_using_minimum_grand_total' => Status::class,
        'activate_using_minimum_total_products' => Status::class,
        'activate_using_minimum_total_product_quantities' => Status::class,
    ];

    protected $fillable = [

            /*  General Information */
            'name', 'description',

            /*  Offer Discount Information */
            'offer_discount', 'discount_rate_type', 'discount_percentage_rate', 'discount_flat_rate',

            /*  Offer Free Delivery Information */
            'offer_free_delivery',

            /*  Activation Information  */
            'activate_using_code', 'code',
            'activate_using_end_datetime', 'end_datetime',
            'activate_using_hours_of_day', 'hours_of_day',
            'activate_using_start_datetime', 'start_datetime',
            'activate_using_days_of_the_week', 'days_of_the_week',
            'activate_using_days_of_the_month', 'days_of_the_month',
            'activate_using_months_of_the_year', 'months_of_the_year',
            'activate_using_minimum_total_products', 'minimum_total_products',
            'activate_for_new_customer', 'activate_for_existing_customer',
            'activate_using_usage_limit', 'remaining_quantity',
            'activate_using_minimum_grand_total', 'currency', 'minimum_grand_total',
            'activate_using_minimum_total_product_quantities', 'minimum_total_product_quantities',

            /*  Cancellation Information  */
            'is_cancelled', 'cancellation_reasons',

            /*  Detected Changes Information  */
            'detected_changes',

            /*  Ownership  */
            'promotion_id', 'order_id', 'store_id'

    ];

    /****************************
     *  SCOPES                  *
     ***************************/

    /*
     *  Scope: Return products that are being searched using the product name
     */
    public function scopeSearch($query, $searchWord)
    {
        return $query->where('name', 'like', "%$searchWord%");
    }

    /**
     *  Scope promotions for a given store
     */
    public function scopeForStore($query, $store)
    {
        return $query->where('store_id', $store instanceof Model ? $store->id : $store);
    }

    /****************************
     *  RELATIONSHIPS           *
     ***************************/

    /**
     *  Returns the associated promotion
     */
    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     *  Returns the associated store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
