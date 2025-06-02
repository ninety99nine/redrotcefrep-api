<?php

namespace App\Enums;

enum SmsFailureType:string {
    case InternalFailure = 'internal failure';
    case MessageSendingFailed = 'message sending failed';
    case TokenGenerationFailed = 'token generation failed';
    case MessageDeliveryVerificationFailed = 'message delivery verification failed';
}
