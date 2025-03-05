<?php

namespace App\Models;

use App\Casts\Money;
use App\Casts\Currency;
use App\Enums\TaxMethod;
use App\Casts\Percentage;
use App\Traits\AuthTrait;
use App\Enums\WeightUnit;
use App\Traits\StoreTrait;
use App\Casts\JsonToArray;
use App\Enums\DistanceUnit;
use App\Enums\InsightPeriod;
use App\Models\Base\BaseModel;
use App\Enums\InsightCategory;
use App\Enums\CheckoutFeeType;
use App\Enums\RequestFileName;
use App\Casts\E164PhoneNumberCast;
use App\Casts\DeliveryDestinations;
use App\Traits\UserStoreAssociationTrait;
use App\Models\Pivots\StorePaymentMethod;
use App\Models\Pivots\UserStoreAssociation;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Pivots\FriendGroupStoreAssociation;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends BaseModel
{
    use HasFactory, StoreTrait, UserStoreAssociationTrait, AuthTrait;

    const DEFAULT_OFFLINE_MESSAGE = 'We are currently offline';

    const PERMISSIONS = [
        [
            'name' => 'Manage everything',
            'grant' => '*',
            'description' => 'Permission to manage everything'
        ],
        [
            'name' => 'Manage orders',
            'grant' => 'manage orders',
            'description' => 'Permission to manage orders'
        ],
        [
            'name' => 'Manage promotions',
            'grant' => 'manage promotions',
            'description' => 'Permission to manage promotions'
        ],
        [
            'name' => 'Manage products',
            'grant' => 'manage products',
            'description' => 'Permission to manage products'
        ],
        [
            'name' => 'Manage customers',
            'grant' => 'manage customers',
            'description' => 'Permission to manage customers'
        ],
        [
            'name' => 'Manage team members',
            'grant' => 'manage team members',
            'description' => 'Permission to manage team members'
        ],
        [
            'name' => 'Manage settings',
            'grant' => 'manage settings',
            'description' => 'Permission to manage store settings including updating or deleting store'
        ],
    ];

    const USER_STORE_FILTERS = [
        'All', 'Team Member', 'Team Member Left', 'Team Member Joined', 'Team Member Invited', 'Team Member Declined',
        'Team Member Joined As Creator', 'Team Member Joined As Non Creator', 'Follower', 'Unfollower', 'Invited To Follow',
        'Friend Group Member', 'Customer', 'Assigned', 'Recent Visitor', 'Associated', 'Active Subscription'
    ];

    public static function CHECKOUT_FEE_TYPES(): array
    {
        return array_map(fn($type) => $type->value, CheckoutFeeType::cases());
    }

    public static function INSIGHT_PERIODS(): array
    {
        return array_map(fn($period) => $period->value, InsightPeriod::cases());
    }

    public static function INSIGHT_CATEGORIES(): array
    {
        return array_map(fn($category) => $category->value, InsightCategory::cases());
    }

    public static function TAX_METHOD_OPTIONS(): array
    {
        return array_map(fn($option) => $option->value, TaxMethod::cases());
    }

    public static function DISTANCE_UNIT_OPTIONS(): array
    {
        return array_map(fn($option) => $option->value, DistanceUnit::cases());
    }

    public static function WEIGHT_UNIT_OPTIONS(): array
    {
        return array_map(fn($option) => $option->value, WeightUnit::cases());
    }

    /**
     *  Magic Numbers
     */
    const MAXIMUM_ADVERTS = 5;
    const MAXIMUM_PRODUCTS = 50;
    const MAXIMUM_PROMOTIONS = 50;
    const NAME_MIN_CHARACTERS = 3;
    const NAME_MAX_CHARACTERS = 25;
    const ALIAS_MIN_CHARACTERS = 3;
    const ALIAS_MAX_CHARACTERS = 25;
    const TAX_ID_MIN_CHARACTERS = 2;
    const TAX_ID_MAX_CHARACTERS = 50;
    const COMPANY_UIN_CHARACTERS = 13;
    const MAXIMUM_VISIBLE_PRODUCTS = 5;
    const DESCRIPTION_MIN_CHARACTERS = 10;
    const DESCRIPTION_MAX_CHARACTERS = 120;
    const PICKUP_NOTE_MIN_CHARACTERS = 10;
    const PICKUP_NOTE_MAX_CHARACTERS = 120;
    const DELIVERY_NOTE_MIN_CHARACTERS = 10;
    const DELIVERY_NOTE_MAX_CHARACTERS = 120;
    const CALL_TO_ACTION_MIN_CHARACTERS = 3;
    const CALL_TO_ACTION_MAX_CHARACTERS = 20;
    const SMS_SENDER_NAME_MIN_CHARACTERS = 3;
    const SMS_SENDER_NAME_MAX_CHARACTERS = 11;
    const OFFLINE_MESSAGE_MIN_CHARACTERS = 3;
    const OFFLINE_MESSAGE_MAX_CHARACTERS = 120;
    const SOCIAL_LINK_NAME_MIN_CHARACTERS = 1;
    const CHECKOUT_FEE_NAME_MIN_CHARACTERS = 3;
    const CHECKOUT_FEE_NAME_MAX_CHARACTERS = 25;
    const SOCIAL_LINK_NAME_MAX_CHARACTERS = 255;
    CONST NUMBER_OF_EMPLOYEES_MIN_CHARACTERS = 1;
    const PICKUP_DESTINATION_NAME_MIN_CHARACTERS = 3;
    const PICKUP_DESTINATION_NAME_MAX_CHARACTERS = 25;
    const DELIVERY_DESTINATION_NAME_MIN_CHARACTERS = 3;
    const DELIVERY_DESTINATION_NAME_MAX_CHARACTERS = 25;
    const PICKUP_DESTINATION_ADDRESS_MIN_CHARACTERS = 3;
    const ORANGE_MONEY_MERCHANT_CODE_MIN_CHARACTERS = 3;
    const ORANGE_MONEY_MERCHANT_CODE_MAX_CHARACTERS = 255;
    const PICKUP_DESTINATION_ADDRESS_MAX_CHARACTERS = 100;
    const SUPPORTED_PAYMENT_METHOD_NAME_MIN_CHARACTERS = 3;
    const SUPPORTED_PAYMENT_METHOD_NAME_MAX_CHARACTERS = 20;
    const NUMBER_OF_EMPLOYEES_MAX_CHARACTERS = 65535;   //  since we use unsignedSmallInteger() table schema

    protected $casts = [
        'online' => 'boolean',
        'verified' => 'boolean',
        'allow_pickup' => 'boolean',
        'tips' => JsonToArray::class,
        'offer_rewards' => 'boolean',
        'allow_delivery' => 'boolean',
        'identified_orders' => 'boolean',
        'show_opening_hours' => 'boolean',
        'allow_free_delivery' => 'boolean',
        'delivery_flat_fee' => Money::class,
        'social_links' => JsonToArray::class,
        'allow_deposit_payments' => 'boolean',
        'opening_hours' => JsonToArray::class,
        'checkout_fees' => JsonToArray::class,
        'allow_installment_payments' => 'boolean',
        'deposit_percentages' => JsonToArray::class,
        'pickup_destinations' => JsonToArray::class,
        'has_automated_payment_methods' => 'boolean',
        'allow_checkout_on_closed_hours' => 'boolean',
        'installment_percentages' => JsonToArray::class,
        'ussd_mobile_number' => E164PhoneNumberCast::class,
        'contact_mobile_number' => E164PhoneNumberCast::class,
        'whatsapp_mobile_number' => E164PhoneNumberCast::class,
        'delivery_destinations' => DeliveryDestinations::class,
    ];

    protected $tranformableCasts = [
        'rating' => 'decimal:1',            //  Eager loaded using the withAvg() method
        'currency' => Currency::class,
        'tax_percentage_rate' => Percentage::class,
        'reward_percentage_rate' => Percentage::class
    ];

    protected $fillable = [
        'emoji', 'name', 'alias', 'email', 'ussd_mobile_number', 'contact_mobile_number', 'whatsapp_mobile_number', 'call_to_action',
        'description', 'qr_code_file_path', 'verified', 'online', 'offline_message', 'social_links', 'identified_orders', 'user_id',
        'allow_delivery', 'allow_free_delivery', 'pickup_note', 'delivery_note', 'delivery_fee', 'delivery_flat_fee',
        'delivery_destinations', 'allow_pickup', 'pickup_note', 'pickup_destinations', 'allow_deposit_payments',
        'deposit_percentages', 'allow_installment_payments', 'installment_percentages', 'sms_sender_name',
        'has_automated_payment_methods', 'country', 'language', 'currency', 'distance_unit', 'weight_unit',
        'tax_percentage_rate', 'tax_method', 'tax_id', 'show_opening_hours', 'opening_hours', 'checkout_fees',
         'allow_checkout_on_closed_hours', 'tips', 'offer_rewards', 'reward_percentage_rate'
    ];

    /************
     *  SCOPES  *
     ***********/

    public function scopeSearch($query, $searchWord)
    {
        $mobileNumber = $searchWord[0] === '+' ? $searchWord : '+' . $searchWord;
        $isMobileNumber = (new PhoneNumber($searchWord))->isValid();

        if($isMobileNumber) {

            $query->where('stores.ussd_mobile_number', $mobileNumber)
                 ->orWhere('stores.contact_mobile_number', $mobileNumber)
                 ->orWhere('stores.whatsapp_mobile_number', $mobileNumber);

        }else{

            return $query->searchName($searchWord);

        }
    }

    /*
     *  Scope: Return stores searched by name
     */
    public function scopeSearchName($query, $searchWord)
    {
        return $query->where('stores.name', 'like', "%$searchWord%");
    }

    /*
     *  Scope: Return stores searched by alias
     */
    public function scopeSearchAlias($query, $searchWord)
    {
        return $query->where('stores.alias', $searchWord);
    }

    /*
     *  Scope: Return stores searched by USSD mobile number
     */
    public function scopeSearchUssdMobileNumber($query, $mobileNumber)
    {
        return $query->where('stores.ussd_mobile_number', $mobileNumber);
    }

    /*
     *  Scope: Return stores searched by contact mobile number
     */
    public function scopeSearchContactMobileNumber($query, $mobileNumber)
    {
        return $query->where('stores.contact_mobile_number', $mobileNumber);
    }

    /*
     *  Scope: Return stores searched by whatsapp mobile number
     */
    public function scopeSearchWhatsappMobileNumber($query, $mobileNumber)
    {
        return $query->where('stores.whatsapp_mobile_number', $mobileNumber);
    }

    /*
     *  Scope: Return stores that have an active subscription
     */
    public function scopeHasActiveSubscription($query)
    {
        return $query->whereHas('subscriptions', function ($query) {
            $query->active();
        });
    }

    /********************
     *  RELATIONSHIPS   *
     *******************/

    public function logo()
    {
        return $this->morphOne(MediaFile::class, 'mediable')->where('type', RequestFileName::STORE_LOGO->value);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function adverts()
    {
        return $this->morphMany(MediaFile::class, 'mediable')->where('type', RequestFileName::STORE_ADVERT->value);
    }

    public function coverPhoto()
    {
        return $this->morphOne(MediaFile::class, 'mediable')->where('type', RequestFileName::STORE_COVER_PHOTO->value);
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'owner');
    }

    public function sections()
    {
        return $this->morphMany(Section::class, 'owner');
    }

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }

    public function storeQuota()
    {
        return $this->hasOne(StoreQuota::class);
    }

    public function paymentMethods()
    {
        return $this->belongsToMany(PaymentMethod::class, 'store_payment_method', 'store_id', 'payment_method_id')
                    ->withPivot(StorePaymentMethod::VISIBLE_COLUMNS)
                    ->using(StorePaymentMethod::class)
                    ->as('store_payment_method');
    }

    public function deliveryMethods()
    {
        return $this->hasMany(DeliveryMethod::class);
    }

    public function storeRollingNumbers()
    {
        return $this->hasMany(StoreRollingNumber::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function hiddenProducts()
    {
        return $this->products()->hidden();
    }

    public function visibleProducts()
    {
        return $this->products()->Visible();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function placedOrders()
    {
        return $this->orders()->where('placed_by_user_id', $this->hasAuthUser() ? $this->getAuthUser()->id : 0);
    }

    public function createdOrders()
    {
        return $this->orders()->where('created_by_user_id', $this->hasAuthUser() ? $this->getAuthUser()->id : 0);
    }

    public function assignedOrders()
    {
        return $this->orders()->where('assigned_to_user_id', $this->hasAuthUser() ? $this->getAuthUser()->id : 0);
    }

    public function userStoreAssociation()
    {
        return $this->hasOne(UserStoreAssociation::class, 'store_id')->where('user_id', $this->hasAuthUser() ? $this->getAuthUser()->id : 0);
    }

    /**
     *  Returns the associated users that have been assigned to this store
     *
     *  @return Illuminate\Database\Eloquent\Concerns\HasRelationships::belongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_store_association', 'store_id', 'user_id')
                    ->withPivot(UserStoreAssociation::VISIBLE_COLUMNS)
                    ->using(UserStoreAssociation::class)
                    ->as('user_store_association');
    }

    public function teamMembers()
    {
        return $this->users()->whereNotNull('team_member_status');
    }

    public function teamMembersWhoLeft()
    {
        return $this->teamMembers()->leftTeam();
    }

    public function teamMembersWhoJoined()
    {
        return $this->teamMembers()->joinedTeam();
    }

    public function teamMemberAsCreator()
    {
        return $this->teamMembers()->joinedTeamAsCreator();
    }

    /**
     *  Returns the associated users that have been assigned to this store as a follower
     *
     *  @return Illuminate\Database\Eloquent\Concerns\HasRelationships::belongsToMany
     */
    public function followers()
    {
        return $this->users()->whereNotNull('follower_status');
    }

    /**
     *  Returns the subscriptions to this store
     */
    public function subscriptions()
    {
        return $this->morphMany(Subscription::class, 'owner')->latest();
    }

    /**
     *  Returns the non-expired subscriptions to this store
     */
    public function activeSubscriptions()
    {
        return $this->subscriptions()->active();
    }

    /**
     *  Returns the non-expired subscription to this store
     */
    public function activeSubscription()
    {
        return $this->morphOne(Subscription::class, 'owner')->latest()->active();
    }

    /**
     *  Returns the transactions to this store
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class)->latest();
    }

    /**
     *  Get the Friend Groups of this Store
     *
     *  @return Illuminate\Database\Eloquent\Concerns\HasRelationships::belongsToMany
     */
    public function friendGroups()
    {
        return $this->belongsToMany(FriendGroup::class, 'friend_group_store_association', 'store_id', 'friend_group_id')
                    ->withPivot(FriendGroupStoreAssociation::VISIBLE_COLUMNS)
                    ->using(FriendGroupStoreAssociation::class)
                    ->as('friend_group_store_association');
    }

    /**
     *  Returns the friend group and store association
     *
     *  @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function friendGroupStoreAssociation()
    {
        return $this->hasOne(FriendGroupStoreAssociation::class, 'store_id');
    }

    /**
     *  Get the sms messages associated with this store
     *
     *  @return Illuminate\Database\Eloquent\Concerns\HasRelationships::belongsToMany
     */
    public function smsMessages()
    {
        return $this->hasMany(SmsMessage::class);
    }

    /****************************
     *  ACCESSORS               *
     ***************************/

    protected $appends = [
        'name_with_emoji', 'web_link'
    ];

    public function nameWithEmoji(): Attribute
    {
        return new Attribute(
            get: fn() => empty($this->emoji) ? $this->name : $this->emoji.' '.$this->name
        );
    }

    public function webLink(): Attribute
    {
        return new Attribute(
            get: fn() => $this->alias ? config('app.FRONTEND_URI').'/'.$this->alias : null
        );
    }
}
