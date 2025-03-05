<?php

namespace App\Models;

use App\Traits\UserTrait;
use App\Enums\RequestFileName;
use Laravel\Sanctum\HasApiTokens;
use App\Services\Ussd\UssdService;
use App\Casts\E164PhoneNumberCast;
use Illuminate\Notifications\Notifiable;
use App\Models\Base\BaseAuthenticatable;
use App\Traits\UserStoreAssociationTrait;
use App\Notifications\Orders\OrderCreated;
use App\Notifications\Orders\OrderUpdated;
use App\Notifications\Stores\StoreCreated;
use Illuminate\Notifications\Notification;
use Propaganistas\LaravelPhone\PhoneNumber;
use App\Models\Pivots\UserStoreAssociation;
use App\Traits\UserOrderViewAssociationTrait;
use App\Traits\FriendGroupUserAssociationTrait;
use App\Models\Pivots\FriendGroupUserAssociation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends BaseAuthenticatable /* Authenticatable */
{
    use HasApiTokens, HasFactory, Notifiable, UserTrait, UserStoreAssociationTrait,
    UserOrderViewAssociationTrait, FriendGroupUserAssociationTrait;

    /**
     *  Magic Numbers
     */
    const FIRST_NAME_MIN_CHARACTERS = 3;
    const FIRST_NAME_MAX_CHARACTERS = 20;
    const LAST_NAME_MIN_CHARACTERS = 3;
    const LAST_NAME_MAX_CHARACTERS = 20;
    const PASSWORD_MIN_CHARACTERS = 6;
    const ABOUT_ME_MAX_CHARACTERS = 200;
    const ABOUT_ME_MIN_CHARACTERS = 3;

    protected $casts = [
        'is_guest' => 'boolean',
        'last_seen_at' => 'datetime',
        'is_super_admin' => 'boolean',
        'email_verified_at' => 'datetime',
        'mobile_number_verified_at' => 'datetime',
        'mobile_number' => E164PhoneNumberCast::class,
    ];

    protected $fillable = [
        'first_name', 'last_name', 'about_me', 'password', 'email', 'email_verified_at', 'mobile_number', 'mobile_number_verified_at',
        'google_id', 'facebook_id', 'linkedin_id', 'last_seen_at', 'registered_by_user_id', 'is_guest', 'is_super_admin'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];


    /**
     * Route notifications for the OneSignal channel.
     *
     * Reference: https://github.com/laravel-notification-channels/onesignal
     */
    public function routeNotificationForOneSignal(Notification $notification): mixed
    {
        return ['include_external_user_ids' => ["$this->id"]];
    }

    /**
     * Route notifications for the Slack channel.
     */
    public function routeNotificationForSlack(Notification $notification): mixed
    {
        if($notification instanceof OrderCreated || $notification instanceof OrderUpdated) {

            return config('app.ORDERS_SLACK_WEBHOOK_URL');

        }elseif($notification instanceof StoreCreated) {

            return config('app.STORES_SLACK_WEBHOOK_URL');

        }
    }

    /**
     *  The channels the user receives notification broadcasts on.
     *
     *  Reference: https://laravel.com/docs/10.x/notifications#customizing-the-notification-channel
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'user.notifications.'.$this->id;
    }

    /*
     *  Scope: Return users that are being searched
     */
    public function scopeSearch($query, $searchWord)
    {
        $mobileNumber = $searchWord[0] === '+' ? $searchWord : '+' . $searchWord;
        $isMobileNumber = (new PhoneNumber($searchWord))->isValid();

        if($isMobileNumber) {
            return $query->where('users.mobile_number', $mobileNumber);
        }else{
            return $query->whereRaw('concat(first_name," ",last_name) like ?', "%{$searchWord}%");

        }
    }

    /*
     *  Scope: Return users that are being searched using the mobile number
     */
    public function scopeSearchMobileNumber($query, $mobileNumber)
    {
        return $query->where('users.mobile_number', $mobileNumber);
    }

    /*
     *  Scope: Return users that are being searched using the mobile number
     */
    public function scopeNotGuest($query)
    {
        return $query->where('is_guest', '0');
    }

    /****************************
     *  RELATIONSHIPS           *
     ***************************/

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'user_store_association', 'user_id', 'store_id')
                    ->withPivot(UserStoreAssociation::VISIBLE_COLUMNS)
                    ->using(UserStoreAssociation::class)
                    ->as('user_store_association');
    }

    public function storesAsFollower()
    {
        return $this->stores()->whereNotNull('follower_status');
    }

    public function storesAsCustomer()
    {
        return $this->stores()->where('is_associated_as_customer', '1');
    }

    public function storesAsTeamMember()
    {
        return $this->stores()->whereNotNull('team_member_status');
    }

    public function storesAsRecentVisitor()
    {
        return $this->stores()->whereNotNull('last_seen_at');
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'owner');
    }

    public function aiAssistant()
    {
        return $this->hasOne(AiAssistant::class);
    }

    public function profilePhoto()
    {
        return $this->morphOne(MediaFile::class, 'mediable')->where('type', RequestFileName::PROFILE_PHOTO->value);
    }

    public function placedOrders()
    {
        return $this->hasMany(Order::class, 'placed_by_user_id');
    }

    public function createdOrders()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedOrders()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function friends()
    {
        return $this->hasMany(Friend::class);
    }

    public function aiMessages()
    {
        return $this->hasOneThrough(AiMessage::class, AiAssistant::class);
    }

    public function friendGroups()
    {
        return $this->belongsToMany(FriendGroup::class, 'friend_group_user_association', 'user_id', 'friend_group_id')
                    ->withPivot(FriendGroupUserAssociation::VISIBLE_COLUMNS)
                    ->using(FriendGroupUserAssociation::class)
                    ->as('friend_group_user_association');
    }

    /*  ???
    public function smsAlert()
    {
        return $this->hasOne(SmsAlert::class);
    }
    */

    /****************************
     *  ACCESSORS               *
     ***************************/

    protected $appends = [
        'name', 'requires_password', 'mobile_number_shortcode'
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->getRawOriginal('first_name').' '.$this->getRawOriginal('last_name'))
        );
    }

    protected function requiresPassword(): Attribute
    {
        return Attribute::make(
            get: fn () => empty($this->password)
        );
    }

    protected function mobileNumberShortcode(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->mobile_number == null ? null : UssdService::appendToMainShortcode($this->mobile_number->formatNational(), $this->mobile_number->getCountry())
        );
    }
}
