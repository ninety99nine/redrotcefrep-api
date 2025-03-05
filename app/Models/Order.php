<?php

namespace App\Models;

use App\Casts\Money;
use App\Casts\Status;
use App\Models\Store;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Traits\AuthTrait;
use App\Traits\OrderTrait;
use App\Casts\OrderStatus;
use App\Models\Base\BaseModel;
use App\Casts\OrderPaymentStatus;
use App\Services\Ussd\UssdService;
use App\Casts\E164PhoneNumberCast;
use App\Enums\OrderCancellationReason;
use App\Enums\OrderStatus as EnumsOrderStatus;
use App\Models\Pivots\UserOrderViewAssociation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\OrderPaymentStatus as EnumsOrderPaymentStatus;
use App\Enums\OrderCollectionType as EnumsOrderCollectionType;

class Order extends BaseModel
{
    use HasFactory, OrderTrait, AuthTrait;

    const USER_ORDER_ASSOCIATIONS = ['Customer', 'Team Member'];

    public static function STATUSES(): array
    {
        return array_map(fn($status) => $status->value, EnumsOrderStatus::cases());
    }

    public static function PAYMENT_STATUSES(): array
    {
        return array_map(fn($status) => $status->value, EnumsOrderPaymentStatus::cases());
    }

    public static function COLLECTION_TYPES(): array
    {
        return array_map(fn($status) => $status->value, EnumsOrderCollectionType::cases());
    }

    public static function CANCELLATION_REASONS(): array
    {
        return array_map(fn($status) => $status->value, OrderCancellationReason::cases());
    }

    public static function STATUSES_BEFORE_CANCELLATION(): array
    {
        return array_values(array_filter(self::STATUSES(), fn($status) => $status !== EnumsOrderStatus::CANCELLED->value));
    }

    /**
     *  Magic Numbers
     */
    const STORE_NOTE_MIN_CHARACTERS = 3;
    const STORE_NOTE_MAX_CHARACTERS = 1000;
    const CUSTOMER_NOTE_MIN_CHARACTERS = 3;
    const CUSTOMER_NOTE_MAX_CHARACTERS = 400;
    const COLLECTION_NOTE_MIN_CHARACTERS = 3;
    const COLLECTION_NOTE_MAX_CHARACTERS = 400;
    const OTHER_CANCELLATION_REASON_MIN_CHARACTERS = 3;
    const OTHER_CANCELLATION_REASON_MAX_CHARACTERS = 400;

    protected $casts = [
        'subtotal' => Money::class,
        'fee_total' => Money::class,
        'vat_amount' => Money::class,
        'paid_total' => Money::class,
        'free_delivery' => 'boolean',
        'cancelled_at' => 'datetime',
        'delivery_date' => 'datetime',
        'grand_total' => Money::class,
        'pending_total' => Money::class,
        'discount_total' => Money::class,
        'collection_verified' => 'boolean',
        'outstanding_total' => Money::class,
        'applied_promotion_code' => 'boolean',
        'collection_verified_at' => 'datetime',
        'last_viewed_by_team_at' => 'datetime',
        'first_viewed_by_team_at' => 'datetime',
        'subtotal_after_discount' => Money::class,
        'collection_code_expires_at' => 'datetime',
        'customer_mobile_number' => E164PhoneNumberCast::class,
    ];


    protected $tranformableCasts = [
        'currency' => Currency::class,
        'status' => OrderStatus::class,
        'vat_rate' => Percentage::class,
        'paid_percentage' => Percentage::class,
        'collection_verified' => Status::class,
        'pending_percentage' => Percentage::class,
        'payment_status' => OrderPaymentStatus::class,
        'outstanding_percentage' => Percentage::class
    ];

    protected $fillable = [

        /* General Information */
        'summary', 'status', 'currency', 'subtotal', 'discount_total', 'subtotal_after_discount',
        'vat_method', 'vat_rate', 'vat_amount', 'fee_total', 'grand_total',

        /* Payment Information */
        'payment_status', 'paid_total', 'paid_percentage', 'pending_total', 'pending_percentage',
        'outstanding_total', 'outstanding_percentage',

        /* Product Information */
        'total_products', 'total_cancelled_products', 'total_uncancelled_products',
        'total_product_quantities', 'total_cancelled_product_quantities',
        'total_uncancelled_product_quantities',

        /* Promotion Information */
        'total_promotions', 'total_cancelled_promotions', 'total_uncancelled_promotions',
        'applied_promotion_code',

        /* Delivery Information */
        'delivery_method_name', 'delivery_distance_value', 'delivery_distance_unit', 'delivery_distance_text',
        'delivery_duration_value', 'delivery_duration_text', 'delivery_weight_value', 'delivery_weight_unit',
        'delivery_weight_text', 'free_delivery', 'delivery_date', 'delivery_timeslot', 'delivery_method_id',

        /* Collection Verification */
        'collection_code', 'collection_qr_code', 'collection_code_expires_at', 'collection_verified',
        'collection_verified_at', 'collection_verified_by_user_id', 'collection_note',

        /* Cancellation Information */
        'cancellation_reason', 'other_cancellation_reason', 'cancelled_at',

        /* Customer Information */
        'customer_first_name', 'customer_last_name', 'customer_mobile_number',
        'customer_email', 'customer_note', 'customer_id', 'placed_by_user_id',

        /* Team Views */
        'total_views_by_team', 'first_viewed_by_team_at', 'last_viewed_by_team_at',

        /* Notes */
        'store_note',

        /* Other Relationships */
        'assigned_to_user_id', 'created_by_user_id', 'store_id', 'occasion_id', 'friend_group_id',

    ];

    /************
     *  SCOPES  *
     ***********/

    /*
     *  Scope: Return orders that are being searched
     */
    public function scopeSearch($query, $searchWord)
    {
        // Remove the leading zeros to retrive the order id
        $searchWordWithoutLeadingZeros = ltrim($searchWord);

        /**
         *  Search: Order number, associated user's first name, last name or mobile number
         */
        return $query->where('id', $searchWordWithoutLeadingZeros)
                    ->orWhere('summary', 'like', "%{$searchWord}%")
                    ->orWhereHas('customer', function($query) use ($searchWord) {
                        $query->search($searchWord);
                    })->orWhereHas('orderProducts', function($query) use ($searchWord) {
                        $query->search($searchWord);
                    })->orWhereHas('orderPromotions', function($query) use ($searchWord) {
                        $query->search($searchWord);
                    });
    }

    /********************
     *  RELATIONSHIPS   *
     *******************/

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function orderFees()
    {
        return $this->hasMany(OrderFee::class);
    }

    public function orderComments()
    {
        return $this->hasMany(OrderComment::class);
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function orderDiscounts()
    {
        return $this->hasMany(OrderDiscount::class);
    }

    public function orderPromotions()
    {
        return $this->hasMany(OrderPromotion::class);
    }

    public function orderHistory()
    {
        return $this->hasMany(OrderHistory::class);
    }




     public function store()
     {
         return $this->belongsTo(Store::class);
     }

     public function customer()
     {
         return $this->belongsTo(Customer::class);
     }

     public function occasion()
     {
         return $this->belongsTo(Occasion::class);
     }

    public function friendGroup()
    {
        return $this->belongsTo(FriendGroup::class);
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'owner');
    }

    public function placedByUser()
    {
        return $this->belongsTo(User::class, 'placed_by_user_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function deliveryAddress()
    {
        return $this->hasOne(DeliveryAddress::class);
    }

    public function collectionVerifiedByUser()
    {
        return $this->belongsTo(User::class, 'collection_verified_by_user_id');
    }

    public function viewers()
    {
        return $this->belongsToMany(User::class, 'user_order_view_association', 'order_id', 'user_id')
                    ->withPivot(UserOrderViewAssociation::VISIBLE_COLUMNS)
                    ->using(UserOrderViewAssociation::class)
                    ->as('user_order_view_association');
    }

    /****************************
     *  ACCESSORS               *
     ***************************/

    protected $appends = [
        'customer_name', 'customer_display_name', 'number', 'dial_to_show_collection_code', 'follow_up_statuses',
        'can_cancel', 'can_uncancel', 'can_delete', 'can_mark_as_paid', 'can_request_payment', 'payable_amounts',
        'is_paid', 'is_unpaid', 'is_partially_paid', 'is_pending_payment', 'is_waiting', 'is_on_its_way',
        'is_ready_for_pickup', 'is_cancelled', 'is_completed'
    ];

    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn () => random_int(10000, 90000),
            set: fn ($value) => $value
        );
    }

    protected function customerName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->getRawOriginal('customer_first_name').' '.$this->getRawOriginal('customer_last_name')),
            set: fn ($value) => $value
        );
    }

    protected function customerDisplayName(): Attribute
    {
        $isMe = !is_null($this->placed_by_user_id) && $this->hasAuthUser() && $this->getAuthUser()->id == $this->placed_by_user_id;

        return Attribute::make(
            get: fn () => $isMe ? 'Me' : $this->customer_name
        );
    }

    protected function cancellationReason(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => empty($value) ? null : ucfirst($value),
            set: fn ($value) => empty($value) ? null : strtolower($value)
        );
    }

    protected function otherCancellationReason(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => empty($value) ? null : ucfirst(strtolower($value)),
            set: fn ($value) => empty($value) ? null : strtolower($value)
        );
    }

    public function setCustomerDisplayNameAttribute($value)
    {
        /**
         *  This allows us to modify the "customer_display_name" e.g setting the value to "Me"
         *  when transforming the order depending on whether or not the authenticated user
         *  placed this order. We use this to determine how the customer, friend, store
         *  team members and general public see this order.
         */
        $this->customer_display_name = $value;
    }

    public function getDialToShowCollectionCodeAttribute()
    {
        if($this->customer_mobile_number) {

            $mobileVerificationShortcode = UssdService::getMobileVerificationShortcode($this->customer_mobile_number->getCountry());

            if($mobileVerificationShortcode) {

                $customerName = $this->customer_name;
                $mobileNumber = $this->customer_mobile_number->formatNational();
                $instruction = 'Ask '.$customerName.' to dial '.$mobileVerificationShortcode.' on '. $mobileNumber . ', then enter the 6 digit code that appears on screen to complete this order';

                return [
                    'code' => $mobileVerificationShortcode,
                    'instruction' => $instruction
                ];
            }

        }

        return null;
    }

    public function getFollowUpStatusesAttribute()
    {
        $currentStatus = $this->getRawOriginal('status');

        $description = [
            'Waiting' => 'Order is waiting to be processed',
            'Ready For Pickup' => 'Order is ready for pickup',
            'On Its Way' => 'Order is on its way to being delivered',
            'Cancelled' => 'Order has been cancelled',
            'Completed' => 'Order has been completed',
        ];

        return collect($description)->except(ucwords($currentStatus))
                ->map(fn($description, $status) => ['name' => $status, 'description' => $description])
                ->values()
                ->toArray();
    }

    public function getCanCancelAttribute()
    {
        /**
         *  This order can only be cancelled if it has not already been cancelled
         */
        return !$this->is_cancelled;
    }

    public function getCanUncancelAttribute()
    {
        /**
         *  This order can only be uncancelled if it has already been cancelled
         */
        return $this->is_cancelled;
    }

    public function getCanDeleteAttribute()
    {
        /**
         *  This order can only be deleted if it has been cancelled
         */
        return $this->is_cancelled;
    }

    public function getCanMarkAsPaidAttribute()
    {
        // Ensure grand_total is loaded
        if ($this->grand_total && $this->grand_total->amount > 0) {

            // Check if the store is loaded
            if (request()->store || $this->relationLoaded('store')) {

                // Attempt to get the store from the request or eager loaded relation
                $store = request()->store ?? $this->getRelation('store');

                // Check if there are payable amounts
                $hasPayableAmounts = count($this->payable_amounts) > 0;

                if ($hasPayableAmounts) {

                    // Get the UserStoreAssociation
                    $userStoreAssociation = $store->user_store_association;

                    // Check if the store has payment methods
                    $hasAutomatedPayments = $store->has_payment_methods;

                    // Check if the UserStoreAssociation is provided
                    if ($userStoreAssociation) {

                        // Return the specific condition for marking as paid
                        return $userStoreAssociation->is_team_member_who_has_joined;
                    }
                }

            } else {

                // Handle the case where the store is not loaded
                return null;

            }
        }

        // Default case: cannot mark as paid
        return false;
    }

    public function getCanRequestPaymentAttribute()
    {
        $store = request()->store ?? ($this->relationLoaded('store') ? $this->getRelation('store') : null);

        if($store == null) return null;
        $hasPayableAmounts = count($this->payable_amounts) > 0;
        $hasAutomatedPaymentMethods = $store->has_automated_payment_methods;
        return $hasPayableAmounts && $hasAutomatedPaymentMethods;
    }

    public function getPayableAmountsAttribute()
    {
        $store = request()->store ?? ($this->relationLoaded('store') ? $this->getRelation('store') : null);
        $options = [];

        if ($store) {

            $amountPaidPercentage = $this->getRawOriginal('paid_percentage');
            $amountPendingPercentage = $this->getRawOriginal('paid_pending_percentage');
            $amountOutstandingPercentage = $this->getRawOriginal('outstanding_percentage');

            //  Get the remaining outstanding percentage balance that is still payable
            $remainingPercentage = $amountOutstandingPercentage - $amountPendingPercentage;

            if($store && $remainingPercentage > 0) {

                $option = fn($name, $type, $percentage) => [
                    'name' => $name,
                    'type' => $type,
                    'percentage' => (int) $percentage,
                    'amount' => $this->convertToMoneyFormat($this->getRawOriginal('grand_total') * $percentage / 100, $this->getRawOriginal('currency'))
                ];

                //  Get deposit options
                $getDepositPaymentOptions = function($existingOptions) use ($store, $option) {

                    //  If the store supports deposit payments
                    if($store->allow_deposit_payments) {

                        //  Get the deposit options
                        $depositOptions = collect($store->deposit_percentages)->map(function($percentage) use ($option) {

                            //  Return the deposit option
                            return $option("Deposit ($percentage%)", 'deposit', $percentage);

                        })->toArray();

                        //  Add deposit options
                        array_push($existingOptions, ...$depositOptions);

                    }

                    return $existingOptions;

                };

                //  Get installment options
                $getInstallmentPaymentOptions = function($existingOptions) use ($remainingPercentage, $store, $option) {

                    //  Add remaining balance payment option
                    $options[] = $option("Remaining Balance ($remainingPercentage%)", 'remaining_balance', $remainingPercentage);

                    //  If the store supports installment payments
                    if($store->allow_installment_payments) {

                        //  Get the installment options
                        $installmentOptions = collect($store->installment_percentages)->reject(function($percentage) use ($remainingPercentage) {

                            //  Reject installment percentages that are higher than or equal to the remaining percentage
                            return $percentage > $remainingPercentage || $percentage == $remainingPercentage;

                        })->map(function($percentage) use ($option) {

                            //  Return the installment option
                            return $option("$percentage% Installment", 'installment', $percentage);

                        })->toArray();

                        //  Add installment options
                        array_push($existingOptions, ...$installmentOptions);

                    }

                    return $existingOptions;
                };

                if($amountPaidPercentage < 100) {

                    if($amountPaidPercentage == 0) {

                        //  Add full payment option
                        $options[] = $option('Full Payment', 'full_payment', 100);

                        //  If the store does not support deposit payments
                        if($store->allow_deposit_payments) {

                            //  Add deposit payment options
                            $options = $getDepositPaymentOptions($options);

                        }else{

                            //  Add installment payment options
                            $options = $getInstallmentPaymentOptions($options);

                        }

                    }else{

                        //  If the amount outstanding is the amount pending payment
                        if($amountPendingPercentage == $amountOutstandingPercentage) {

                            //  Then we cannot have payment options
                            return [];

                        }else{

                            //  Add remaining balance option
                            $options[] = $option("Remaining Balance ($remainingPercentage%)", 'remaining_balance', $remainingPercentage);

                            //  Add installment payment options
                            $options = $getInstallmentPaymentOptions($options);

                        }

                    }

                }

            }

        }

        return $options;
    }

    public function getIsPaidAttribute()
    {
        return $this->isPaid();
    }

    public function getIsUnpaidAttribute()
    {
        return $this->isUnpaid();
    }

    public function getIsPartiallyPaidAttribute()
    {
        return $this->isPartiallyPaid();
    }

    public function getIsPendingPaymentAttribute()
    {
        return $this->isPendingPayment();
    }

    public function getIsWaitingAttribute()
    {
        return $this->isWaiting();
    }

    public function getIsOnItsWayAttribute()
    {
        return $this->isOnItsWay();
    }

    public function getIsReadyForPickupAttribute()
    {
        return $this->isReadyForPickup();
    }

    public function getIsCancelledAttribute()
    {
        return $this->isCancelled();
    }

    public function getIsCompletedAttribute()
    {
        return $this->isCompleted();
    }
}
