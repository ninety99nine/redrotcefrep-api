<?php

namespace App\Enums;

enum TransactionFailureType:string {

    /**
     *  Airtime Billing Failure Types:
     */
    case INTERNAL_FAILURE = 'internal failure';
    case INACTIVE_ACCOUNT = 'inactive account';
    case DEDUCT_FEE_FAILED = 'deduct fee failed';
    case INSUFFICIENT_FUNDS = 'insufficient funds';
    case TOKEN_GENERATION_FAILED = 'token generation failed';
    case MISSING_MAIN_BALANCE_INFORMATION = 'missing main balance information';
    case PRODUCT_INVENTORY_RETRIEVAL_FAILED = 'product inventory retrieval failed';
    case USAGE_CONSUMPTION_RETRIEVAL_FAILED = 'usage consumption retrieval failed';

    /**
     *  DPO Billing Failure Types:
     */
    case PAYMENT_REQUEST_FAILED = 'payment Request Failed';
    case PAYMENT_VERIFICATION_FAILED = 'payment verification failed';
}

