<?php

namespace App\Models;

use App\Enums\SmsStatus;
use App\Enums\SmsFailureType;
use App\Models\Base\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsMessage extends BaseModel
{
    use HasFactory;

    public static function STATUSES(): array
    {
        return array_map(fn($status) => $status->value, SmsStatus::cases());
    }

    public static function FAILURE_TYPES(): array
    {
        return array_map(fn($status) => $status->value, SmsFailureType::cases());
    }

    /**
     *  Magic Numbers
     */
    const CONTENT_MIN_CHARACTERS = 3;
    const CONTENT_MAX_CHARACTERS = 500;

    protected $casts = [
        'metadata' => 'array'
    ];

    protected $tranformableCasts = [];

    protected $fillable = [
        'status', 'content', 'metadata', 'store_id', 'sender_name', 'sender_mobile_number', 'recipient_mobile_number', 'failure_type', 'failure_reason'
    ];

    /**
     *  Get the store associated with this sms message
     *
     *  @return Illuminate\Database\Eloquent\Concerns\HasRelationships::belongsTo
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
