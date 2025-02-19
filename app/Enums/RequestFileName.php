<?php

namespace App\Enums;

enum RequestFileName:string {
    case STORE_LOGO = 'store_logo';
    case MODULE_FILE = 'module_file';
    case STORE_ADVERT = 'store_advert';
    case PROFILE_PHOTO = 'profile_photo';
    case PRODUCT_PHOTO = 'product_photo';
    case STORE_COVER_PHOTO = 'cover_photo';
    case STORE_PAYMENT_METHOD_LOGO = 'store_payment_method_logo';
    case STORE_PAYMENT_METHOD_PHOTO = 'store_payment_method_photo';
    case TRANSACTION_PROOF_OF_PAYMENT_PHOTO = 'transaction_proof_of_payment_photo';
}
