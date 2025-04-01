<?php

namespace App\Enums;

enum PaymentMethodType:string {
    case DPO = 'dpo';                                   //  Automated
    case PIX = 'pix';                                   //  Local
    case UPI = 'upi';                                   //  Local
    case OXXO = 'oxxo';                                 //  Local
    case YOCO = 'yoco';                                 //  Local
    case QRIS = 'qris';                                 //  Local
    case WISE = 'wise';                                 //  Local
    case LYNK = 'lynk';                                 //  Local
    case WAVE = 'wave';                                 //  Local
    case OTHER = 'other';                               //  Automated
    case UNAYO = 'unayo';                               //  Local
    case GCASH = 'gcash';                               //  Local
    case ESEWA = 'esewa';                               //  Local
    case VENMO = 'venmo';                               //  Local
    case ZELLE = 'zelle';                               //  Local
    case ZIINA = 'ziina';                               //  Local
    case BKASH = 'bkash';                               //  Local
    case KASPI = 'kaspi';                               //  Local
    case MPESA = 'm-pesa';                              //  Local
    case MBWAY = 'mb way';                              //  Local
    case MYZAKA = 'myzaka';                             //  Local
    case STRIPE = 'stripe';                             //  Automated
    case PAYPAL = 'paypal';                             //  Automated
    case XENDIT = 'xendit';                             //  Automated
    case POCKET = 'pocket';                             //  Automated
    case PAYNOW = 'paynow';                             //  Local
    case WIGWAG = 'wigwag';                             //  Local
    case TIKKIE = 'tikkie';                             //  Local
    case AIRTEL = 'airtel';                             //  Local
    case ECOCASH = 'ecocash';                           //  Local
    case IKHOKHA = 'ikhokha';                           //  Local
    case REVOLUT = 'revolut';                           //  Local
    case PESAPAL = 'pesapal';                           //  Local
    case PAYHERE = 'payhere';                           //  Automated
    case PAYFAST = 'payfast';                           //  Automated
    case DUITNOW = 'duitnow';                           //  Local
    case MONCASH = 'moncash';                           //  Local
    case Instapay = 'instapay';                         //  Local
    case MTN_MOMO = 'mtn momo';                         //  Local
    case CELLMONI = 'cellmoni';                         //  Local
    case TIGOPESA = 'tigopesa';                         //  Local
    case INNBUCKS = 'innbucks';                         //  Local
    case CASH_APP = 'cash app';                         //  Local
    case PAYSTACK = 'paystack';                         //  Automated
    case RAZORPAY = 'razorpay';                         //  Automated
    case SNAPSCAN = 'snapscan';                         //  Local
    case MCBJUICE = 'mcb juice';                        //  Local
    case PAYPAL_ME = 'paypal me';                       //  Manual
    case PROMPTPAY = 'promptpay';                       //  Local
    case TOUCH_N_GO = 'touch n go';                     //  Local
    case FNB_EWALLET = 'fnb ewallet';                   //  Local
    case MERCADO_PAGO = 'mercado pago';                 //  Local
    case FNB_PAY2CELL = 'fnb pay2cell';                 //  Local
    case SEPA = 'sepa credit transfer';                 //  Local
    case ORANGE_MONEY = 'orange money';                 //  Local
    case STORE_CREDIT = 'store credit';                 //  Manual
    case BANK_TRANSFER = 'bank transfer';               //  Manual
    case ORANGE_AIRTIME = 'orange airtime';             //  Automated
    case CASH_ON_DELIVERY = 'cash on delivery';         //  Manual
    case SMEGA_MOBILE_MONEY = 'smega mobile money';     //  Local
}
