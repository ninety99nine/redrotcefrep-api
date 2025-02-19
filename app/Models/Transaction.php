<?php

namespace App\Models;

use Carbon\Carbon;
use App\Casts\Money;
use App\Casts\Currency;
use App\Casts\Percentage;
use App\Models\Base\BaseModel;
use App\Enums\RequestFileName;
use App\Traits\TransactionTrait;
use App\Enums\TransactionFailureType;
use App\Enums\TransactionPaymentStatus;
use App\Enums\TransactionFailureReason;
use App\Enums\TransactionVerificationType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Casts\TransactionPaymentStatus as TransactionPaymentStatusCast;

class Transaction extends BaseModel
{
    use HasFactory, TransactionTrait;

    public static function PAYMENT_STATUSES(): array
    {
        return array_map(fn($status) => $status->value, TransactionPaymentStatus::cases());
    }

    public static function FAILURE_TYPES(): array
    {
        return array_map(fn($failureType) => $failureType->value, TransactionFailureType::cases());
    }

    public static function VERIFICATION_TYPES(): array
    {
        return array_map(fn($verificationType) => $verificationType->value, TransactionVerificationType::cases());
    }

    protected $casts = [
        'amount' => Money::class
    ];

    protected $tranformableCasts = [
        'currency' => Currency::class,
        'percentage' => Percentage::class,
        'payment_status' => TransactionPaymentStatusCast::class,
    ];

    protected $fillable = [

        /*  Basic Information  */
        'payment_status', 'failure_type', 'failure_reason', 'description',

        /*  Amount Information  */
        'currency', 'amount', 'percentage',

        /*  Metadata Information  */
        'metadata',

        /*  Requester Information  */
        'requested_by_user_id',

        /*  Verification Information  */
        'verification_type', 'manually_verified_by_user_id',

        /*  Payment Method Information  */
        'payment_method_id',

        /*  Customer Information  */
        'customer_id',

        /*  Store Information  */
        'store_id',

        /*  AI Assistant Information  */
        'ai_assistant_id',

        /*  Owenership Details  */
        'owner_id', 'owner_type'

    ];

    /****************************
     *  SCOPES                  *
     ***************************/

    /*
     *  Scope: Return stores that are being searched
     */
    public function scopeSearch($query, $searchWord)
    {
        return $query
            ->where('owner_type', 'like', '%' . $searchWord . '%')
            ->orWhere('description', 'like', '%' . $searchWord . '%')
            ->orWhereHas('customer', function ($customer) use ($searchWord) {
                $customer->search($searchWord);
            });
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', TransactionPaymentStatus::PAID->value);
    }

    public function scopeFailedPayment($query)
    {
        return $query->where('payment_status', TransactionPaymentStatus::FAILED_PAYMENT->value);
    }

    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', TransactionPaymentStatus::PENDING_PAYMENT->value);
    }

    public function scopeSubjectToManualVerification($query)
    {
        return $query->where('verification_type', TransactionVerificationType::MANUAL->value);
    }

    public function scopeSubjectToAutomaticVerification($query)
    {
        return $query->where('verification_type', TransactionVerificationType::AUTOMATIC->value);
    }

    /****************************
     *  RELATIONSHIPS           *
     ***************************/

    public function owner()
    {
        return $this->morphTo();
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function aiAssistant()
    {
        return $this->belongsTo(AiAssistant::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function proofOfPayment()
    {
        return $this->morphOne(MediaFile::class, 'mediable')->where('type', RequestFileName::TRANSACTION_PROOF_OF_PAYMENT_PHOTO->value);
    }

    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function manuallyVerifiedByUser()
    {
        return $this->belongsTo(User::class, 'manually_verified_by_user_id');
    }

    /****************************
     *  ACCESSORS               *
     ***************************/

    protected $appends = [
        'number', 'is_paid', 'is_failed_payment', 'is_pending_payment',
        'is_subject_to_manual_verification', 'is_subject_to_automatic_verification'
    ];

    /**
     *  Transaction number
     *
     *  @return bool
     */
    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn () => str_pad($this->id, 5, 0, STR_PAD_LEFT)
        );
    }

    /**
     *  Check if transaction has been paid
     *
     *  @return bool
     */
    protected function getIsPaidAttribute()
    {
        return $this->isPaid();
    }

    /**
     *  Check if transaction has failed payment
     *
     *  @return bool
     */
    protected function getIsFailedPaymentAttribute()
    {
        return $this->isFailedPayment();
    }

    /**
     *  Check if transaction is pending payment
     *
     *  @return bool
     */
    protected function getIsPendingPaymentAttribute()
    {
        return $this->isPendingPayment();
    }

    /**
     *  Check if transaction is subject to manual verification
     *
     *  @return bool
     */
    protected function getIsSubjectToManualVerificationAttribute()
    {
        return $this->isSubjectToManualVerification();
    }

    /**
     *  Check if transaction is subject to automatic verification
     *
     *  @return bool
     */
    protected function getIsSubjectToAutomaticVerificationAttribute()
    {
        return $this->isSubjectToAutomaticVerification();
    }

    /**
     * Expound failure reason
     *
     * @return Attribute
     */
    protected function failureReason(): Attribute
    {
        return Attribute::make(
            get: function($value) {

                if($this->failure_type == TransactionFailureType::INACTIVE_ACCOUNT->value) {

                    return TransactionFailureReason::INACTIVE_ACCOUNT->value;

                }else if($this->failure_type == TransactionFailureType::INSUFFICIENT_FUNDS->value) {

                    return TransactionFailureReason::INSUFFICIENT_FUNDS->value;

                }else if($this->failure_type == TransactionFailureType::USAGE_CONSUMPTION_MAIN_BALANCE_NOT_FOUND->value) {

                    return TransactionFailureReason::USAGE_CONSUMPTION_MAIN_BALANCE_NOT_FOUND->value;

                }

                return $value;
            }
        );
    }

    /**
     *  Expound transaction metadata
     *
     *  @return Attribute
     */
    protected function metadata(): Attribute
    {
        return Attribute::make(
            get: function($value) {

                if($value == null) return null;
                $value = is_string($value) ? json_decode($value, true) : $value;

                if(isset($value['dpo_payment_url'])) {
                    $value['dpo_payment_url_expires_at'] = Carbon::parse($value['dpo_payment_url_expires_at']);
                    $value['dpo_payment_link_has_expired'] = $value['dpo_payment_url_expires_at']->isBefore(now());
                    $value['can_pay_using_dpo'] = $this->is_pending_payment && !$value['dpo_payment_link_has_expired'];
                }

                return $value;
            }
        );
    }

}
