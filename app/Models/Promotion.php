<?php

namespace App\Models;

use Carbon\Carbon;
use App\Casts\Money;
use App\Casts\Status;
use App\Enums\RateType;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Casts\JsonToArray;
use App\Traits\PromotionTrait;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends BaseModel
{
    use HasFactory, PromotionTrait;

    public static function DISCOUNT_RATE_TYPES(): array
    {
        return array_map(fn($method) => $method->value, RateType::cases());
    }

    /**
     *  Magic Numbers
     */
    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 60;
    const CODE_MIN_CHARACTERS = 1;
    const CODE_MAX_CHARACTERS = 10;
    const DESCRIPTION_MIN_CHARACTERS = 3;
    const DESCRIPTION_MAX_CHARACTERS = 120;
    const REMAINING_QUANTITY_MAX = 16777215;      //  since we use unsignedMediumInteger() table schema

    protected $casts = [
        'active' => 'boolean',
        'end_datetime' => 'datetime',
        'offer_discount' => 'boolean',
        'start_datetime' => 'datetime',
        'offer_free_delivery' => 'boolean',
        'activate_using_code' => 'boolean',
        'hours_of_day' => JsonToArray::class,
        'discount_flat_rate' => Money::class,
        'minimum_grand_total' => Money::class,
        'days_of_the_week' => JsonToArray::class,
        'activate_for_new_customer' => 'boolean',
        'days_of_the_month' => JsonToArray::class,
        'activate_using_usage_limit' => 'boolean',
        'months_of_the_year' => JsonToArray::class,
        'activate_using_end_datetime' => 'boolean',
        'activate_using_hours_of_day' => 'boolean',
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
        'active' => Status::class,
        'currency' => Currency::class,
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
        'name', 'description', 'active',

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

        /*  Ownership  */
        'store_id', 'user_id'

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

    /**
     *  Scope active promotions
     */
    public function scopeActive($query)
    {
        return $query->where('active', '1');
    }

    /**
     *  Scope inactive promotions
     */
    public function scopeInactive($query)
    {
        return $query->where('active', '0');
    }

    /****************************
     *  RELATIONSHIPS           *
     ***************************/

    /**
     *  Returns the associated store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     *  Returns the associated order promotions
     */
    public function orderPromotions()
    {
        return $this->hasMany(OrderPromotion::class);
    }

    /****************************
     *  ACCESSORS               *
     ***************************/

    protected $appends = ['instructions'];

    public function getInstructionsAttribute()
    {
        $instructions = [];

        // Check the status for a transformed and untransformed Promotion
        $isActive = function($input) {
            return is_array($input) ? $input['status'] : $input;
        };

        if($isActive($this->activate_using_code)) {
            $instructions[] = 'Enter the promotion code "'.$this->code.'"';
        }

        if($isActive($this->activate_using_start_datetime) && $isActive($this->activate_using_end_datetime)) {

            $instructions[] = 'Place an order between the '.Carbon::parse($this->start_datetime)->format('d M Y H:i').' and the '.Carbon::parse($this->end_datetime)->format('d M Y H:i');

        }else{

            if($isActive($this->activate_using_start_datetime)) {
                $instructions[] = 'Place an order after the '.Carbon::parse($this->start_datetime)->format('d M Y H:i');
            }

            if($isActive($this->activate_using_end_datetime)) {
                $instructions[] = 'Place an order before the '.Carbon::parse($this->end_datetime)->format('d M Y H:i');
            }

        }

        if($isActive($this->activate_using_hours_of_day)) {
            $instructions[] = 'Place an order on any of the following hours of the day: '.collect($this->hours_of_day)->join(', ', ' or ');
        }

        if($isActive($this->activate_using_days_of_the_week)) {
            $instructions[] = 'Place an order on any of the following days of the week: '.collect($this->days_of_the_week)->join(', ', ' or ');
        }

        if($isActive($this->activate_using_days_of_the_month)) {
            $instructions[] = 'Place an order on any of the following days of the month: '.collect($this->days_of_the_month)->join(', ', ' or ');
        }

        if($isActive($this->activate_using_months_of_the_year)) {
            $instructions[] = 'Place an order on any of the following months of the year: '.collect($this->months_of_the_year)->join(', ', ' or ');
        }

        if($isActive($this->activate_using_minimum_total_products)) {
            $instructions[] = 'Place an order with '.$this->minimum_total_products.' or more different products';
        }

        if($isActive($this->activate_using_minimum_total_product_quantities)) {
            $instructions[] = 'Place an order with '.$this->minimum_total_product_quantities.' or more product quantities';
        }

        if($isActive($this->activate_using_minimum_grand_total)) {
            $instructions[] = 'Place an order worth '.$this->minimum_grand_total->amountWithCurrency.' or more';
        }

        if($isActive($this->activate_for_new_customer)) {
            $instructions[] = 'Place an order as a new customer';
        }

        if($isActive($this->activate_for_existing_customer)) {
            $instructions[] = 'Place an order as an existing customer';
        }

        if($isActive($this->activate_using_usage_limit)) {
            $instructions[] = 'Place an order before this limited offer runs out. '.$this->remaining_quantity.' remaining';
        }

        /**
         *  If the promotion instructions are empty, then the customer
         *  does not need to do anything special to activate this
         *  promotion
         */
        if(empty($instructions)) {
            $instructions[] = 'Simply place an order to claim this offer';
        }

        return $instructions;
    }
}
