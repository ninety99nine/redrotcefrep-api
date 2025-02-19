<?php

namespace App\Enums;

enum DeliveryMethodFeeType:string {
    case FLAT_FEE = 'flat fee';
    case PERCENTAGE_FEE = 'percentage fee';
    case FEE_BY_WEIGHT = 'fee by weight';
    case FEE_BY_DISTANCE = 'fee by distance';
    case FEE_BY_POSTAL_CODE = 'fee by postal code';
}
