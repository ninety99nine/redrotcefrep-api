<?php

namespace App\Enums;

enum SmsStatus:string {
    case SENT = 'sent';
    case PENDING = 'pending';
    case FAILED_SENDING = 'failed sending';
    case DELIVERED_VERIFIED = 'delivery verified';
    case FAILED_DELIVERY_VERIFICATION = 'failed delivery verification';
}

