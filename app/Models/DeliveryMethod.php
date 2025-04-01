<?php

namespace App\Models;

use App\Casts\Money;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Casts\JsonToArray;
use App\Models\Base\BaseModel;
use App\Traits\Base\BaseTrait;
use App\Enums\DeliveryTimeUnit;
use App\Traits\DeliveryMethodTrait;
use App\Enums\DeliveryMethodFeeType;
use App\Enums\AutoGenerateTimeSlotsUnit;
use App\Enums\DeliveryMethodScheduleType;
use App\Enums\DeliveryMethodFallbackFeeType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryMethod extends BaseModel
{
    use HasFactory, DeliveryMethodTrait, BaseTrait;

    /**
     *  Magic Numbers
     */
    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 40;
    const DESCRIPTION_MIN_CHARACTERS = 3;
    const DESCRIPTION_MAX_CHARACTERS = 200;
    const BASE_LOCATION_MIN_CHARACTERS = 3;
    const BASE_LOCATION_MAX_CHARACTERS = 255;

    public static function DELIVERY_TIME_UNITS(): array
    {
        return array_map(fn($method) => $method->value, DeliveryTimeUnit::cases());
    }

    public static function DELIVERY_METHOD_FEE_TYPES(): array
    {
        return array_map(fn($method) => $method->value, DeliveryMethodFeeType::cases());
    }

    public static function DELIVERY_METHOD_SCHEDULE_TYPES(): array
    {
        return array_map(fn($method) => $method->value, DeliveryMethodScheduleType::cases());
    }

    public static function DELIVERY_METHOD_FALLBACK_FEE_TYPES(): array
    {
        return array_map(fn($method) => $method->value, DeliveryMethodFallbackFeeType::cases());
    }

    public static function AUTO_GENERATE_TIME_SLOTS_UNITS(): array
    {
        return array_map(fn($method) => $method->value, AutoGenerateTimeSlotsUnit::cases());
    }

    protected $casts = [
        'active' => 'boolean',
        'charge_fee' => 'boolean',
        'set_schedule' => 'boolean',
        'flat_fee_rate' => Money::class,
        'same_day_delivery' => 'boolean',
        'ask_for_an_address' => 'boolean',
        'pin_location_on_map' => 'boolean',
        'set_daily_order_limit' => 'boolean',
        'weight_categories' => JsonToArray::class,
        'minimum_grand_total' => Money::class,
        'distance_zones' => JsonToArray::class,
        'show_distance_on_invoice' => 'boolean',
        'auto_generate_time_slots' => 'boolean',
        'capture_additional_fields' => 'boolean',
        'fallback_flat_fee_rate' => Money::class,
        'additional_fields' => JsonToArray::class,
        'postal_code_zones' => JsonToArray::class,
        'operational_hours' => JsonToArray::class,
        'qualify_on_minimum_grand_total' => 'boolean',
        'require_minimum_notice_for_orders' => 'boolean',
        'restrict_maximum_notice_for_orders' => 'boolean',
        'free_delivery_minimum_grand_total' => Money::class,
        'offer_free_delivery_on_minimum_grand_total' => 'boolean',
    ];

    protected $tranformableCasts = [
        'currency' => Currency::class,
        'percentage_fee_rate' => Percentage::class,
        'fallback_percentage_fee_rate' => Percentage::class,
    ];

    protected $fillable = [
        'active','name','description','currency','qualify_on_minimum_grand_total','minimum_grand_total',
        'offer_free_delivery_on_minimum_grand_total','free_delivery_minimum_grand_total',
        'ask_for_an_address', 'pin_location_on_map', 'show_distance_on_invoice','charge_fee','fee_type',
        'percentage_fee_rate','flat_fee_rate','weight_categories','postal_code_zones','distance_zones','fallback_fee_type',
        'fallback_percentage_fee_rate','fallback_flat_fee_rate','set_schedule',
        'schedule_type','operational_hours','auto_generate_time_slots','time_slot_interval_value',
        'time_slot_interval_unit','same_day_delivery','require_minimum_notice_for_orders','earliest_delivery_time_value',
        'earliest_delivery_time_unit','restrict_maximum_notice_for_orders','latest_delivery_time_value',
        'set_daily_order_limit','daily_order_limit','capture_additional_fields','additional_fields',
        'position','store_id',
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

    public function scopeInactive($query)
    {
        return $query->where('active', '0');
    }

    /********************
     *  RELATIONSHIPS   *
     *******************/

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'owner');
    }

    /****************************
     *  ACCESSORS               *
     ***************************/

    protected $appends = [
        'schedule_options'
    ];

    protected function scheduleOptions(): Attribute
    {
        return Attribute::make(
            get: function() {
                if(!$this->set_schedule) return null;

                return [
                    'min_date' => $this->minDate(),
                    'max_date' => $this->maxDate(),
                    'dates_disabled' => $this->datesDisabled(),
                    'days_of_week_disabled' => $this->daysOfWeekDisabled()
                ];
            }
        );
    }
}
