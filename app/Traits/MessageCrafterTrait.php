<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Traits\Base\BaseTrait;

trait MessageCrafterTrait
{
    use BaseTrait;

    /**
     *  Craft the new order sms messsage to send to the seller
     *
     *  @param Order $order
     *  @return string
     */
    public function craftNewOrderForSellerMessage(Order $order) {

        $store = $order->store;

        $message = 'Order #'.$order->number.' ';

        $message .= $order->summary;

        if($order->customer_first_name && $order->customer_mobile_number) {

            $message .= ' from ' . $order->customer_first_name .' '. $order->customer_mobile_number->formatNational();

        }else if($order->customer_first_name || $order->customer_mobile_number) {

            if($order->customer_first_name) {
                $message .= ' from ' . $order->customer_first_name;
            }else{
                $message .= ' from ' . $order->customer_mobile_number->formatNational();
            }

        }

        if(empty($store->sms_sender_name)) {
            $message .= '. ' . $store->name;
        }

        return $message;

    }

    /**
     *  Craft the new order sms messsage to send to the customer
     *
     *  @param Order $order
     *  @return string
     */
    public function craftNewOrderForCustomerMessage(Order $order) {

        $store = $order->store;

        if(empty($store->sms_sender_name)) {
            return $store->name.', you ordered '.$order->summary.'. Reach us on '.$store->mobile_number?->formatNational().'. Order #'.$order->number;
        }else{
            return 'You ordered '.$order->summary.'. Reach us on '.$store->mobile_number?->formatNational().'. Order #'.$order->number;
        }
    }

    /**
     *  Craft the order collection code messsage
     *
     *  @param Order $order
     *  @return string
     */
    public function craftOrderCollectionCodeMessage(Order $order) {

        $store = $order->store;

        if(empty($store->sms_sender_name)) {
            return $store->name.', your collection code for Order #'.$order->number.' is ' .$order->collection_code;
        }else{
            return 'Your collection code for Order #'.$order->number.' is ' .$order->collection_code;
        }
    }

    /**
     *  Craft the order updated sms messsage
     *
     *  @param Order $order
     *  @param User $updatedByUser
     *  @return string
     */
    public function craftOrderUpdatedMessage(Order $order, User $updatedByUser) {

        $store = $order->store;

        if(empty($store->sms_sender_name)) {
            return $store->name.', '.'Order #'.$order->number.' updated by '.$updatedByUser->name.' ('.$updatedByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }else{
            return 'Order #'.$order->number.' updated by '.$updatedByUser->name.' ('.$updatedByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }
    }

    /**
     *  Craft the order status updated sms messsage
     *
     *  @param Order $order
     *  @param User $updatedByUser
     *  @return string
     */
    public function craftOrderStatusUpdatedMessage(Order $order, User $updatedByUser) {

        $store = $order->store;

        if(empty($store->sms_sender_name)) {
            return $store->name.', '.'Order #'.$order->number.' is '.$order->statusRawOriginalLowercase().', updated by '.$updatedByUser->name.' ('.$updatedByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }else{
            return 'Order #'.$order->number.' is '.$order->statusRawOriginalLowercase().', updated by '.$updatedByUser->name.' ('.$updatedByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }
    }

    /**
     *  Craft the order seen sms messsage
     *
     *  @param Order $order
     *  @param User $seenByUser
     *  @return string
     */
    public function craftOrderSeenMessage(Order $order, User $seenByUser) {

        $store = $order->store;

        if(empty($store->sms_sender_name)) {
            return $store->name.', '.'Order #'.$order->number.' has been seen by '.$seenByUser->name.' ('.$seenByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }else{
            return 'Order #'.$order->number.' has been seen by '.$seenByUser->name.' ('.$seenByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }
    }

    /**
     *  Craft the order collected sms messsage
     *
     *  @param Order $order
     *  @param User $manuallyVerifiedByUser
     *  @return string
     */
    public function craftOrderCollectedMessage(Order $order, User $manuallyVerifiedByUser) {

        $store = $order->store;

        if(empty($store->sms_sender_name)) {
            return $store->name.', '.'Order #'.$order->number.' completed and collected. Verified by '.$manuallyVerifiedByUser->name.' ('.$manuallyVerifiedByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }else{
            return 'Order #'.$order->number.' completed and collected. Verified by '.$manuallyVerifiedByUser->name.' ('.$manuallyVerifiedByUser->mobile_number->formatNational().') Items: '.$order->summary;
        }
    }

    /**
     *  Craft the order payment request sms messsage
     *
     *  @param Order $order
     *  @param Transaction $transaction
     *  @return string
     */
    public function craftOrderPaymentRequestMessage(Order $order, Transaction $transaction) {

        $store = $order->store;
        $paymentMethod = $transaction->paymentMethod;
        $requestedByUser = $transaction->requestedByUser;

        if($paymentMethod->isDpo()) {

            if(empty($store->sms_sender_name)) {
                return $store->name.', Pay for Order #'.$order->number.' using this payment link '.$transaction->metadata['dpo_payment_url'].'. Valid till '.Carbon::parse($transaction->metadata['dpo_payment_url_expires_at'])->format('d M Y H:i').'. Requested by '.$requestedByUser->name.' ('.$requestedByUser->mobile_number->formatNational().') Items: '.$order->summary;
            }else{
                return 'Pay for Order #'.$order->number.' using this payment link '.$transaction->metadata['dpo_payment_url'].'. Valid till '.Carbon::parse($transaction->metadata['dpo_payment_url_expires_at'])->format('d M Y H:i').'. Requested by '.$requestedByUser->name.' ('.$requestedByUser->mobile_number->formatNational().') Items: '.$order->summary;
            }

        }else if($paymentMethod->isOrangeMoney()) {

            if(empty($store->sms_sender_name)) {
                return $store->name.', You are paying for Order #'.$order->number.' using Orange Money. Requested by '.$requestedByUser->name.' ('.$requestedByUser->mobile_number->formatNational().') Items: '.$order->summary;
            }else{
                return 'You are paying for Order #'.$order->number.' using Orange Money. Requested by '.$requestedByUser->name.' ('.$requestedByUser->mobile_number->formatNational().') Items: '.$order->summary;
            }

        }
    }

    /**
     *  Craft the order paid sms messsage
     *
     *  @param Order $order
     *  @param Transaction $transaction
     *  @return string
     */
    public function craftOrderPaidMessage(Order $order, Transaction $transaction) {

        $store = $order->store;

        if($transaction->paymentMethod->isDpo()) {
            if(empty($store->sms_sender_name)) {
                return $store->name.', '.$transaction->amount->amountWithCurrency.' paid successfully for Order #'.$order->number.' by '.$transaction->metadata['dpo_payment_response']['onVerifyPaymentResponse']['customerName'].' using '.$transaction->paymentMethod->name.' on '.Carbon::parse($transaction->updated_at)->format('d M Y H:i');
            }else{
                return $transaction->amount->amountWithCurrency.' paid successfully for Order #'.$order->number.' by '.$transaction->metadata['dpo_payment_response']['onVerifyPaymentResponse']['customerName'].' using '.$transaction->paymentMethod->name.' on '.Carbon::parse($transaction->updated_at)->format('d M Y H:i');
            }
        }else if($transaction->paymentMethod->isOrangeMoney()) {
            if(empty($store->sms_sender_name)) {
                return $store->name.', '.$transaction->amount->amountWithCurrency.' paid successfully for Order #'.$order->number.' by '.$transaction->customer->name.' using '.$transaction->paymentMethod->name.' on '.Carbon::parse($transaction->updated_at)->format('d M Y H:i');
            }else{
                return $transaction->amount->amountWithCurrency.' paid successfully for Order #'.$order->number.' by '.$transaction->customer->name.' using '.$transaction->paymentMethod->name.' on '.Carbon::parse($transaction->updated_at)->format('d M Y H:i');
            }
        }
    }

    /**
     *  Craft the order marked as paid sms messsage
     *
     *  @param Order $order
     *  @param Transaction $transaction
     *  @param User $manuallyVerifiedByUser
     *  @return string
     */
    public function craftOrderMarkedAsPaidMessage(Order $order, Transaction $transaction, User $manuallyVerifiedByUser) {

        $store = $order->store;

        if(empty($store->sms_sender_name)) {
            return $store->name.', '.$transaction->amount->amountWithCurrency.' marked as paid'.($transaction->paymentMethod ? ' using '.$transaction->paymentMethod->name : '').' for Order #'.$order->number.' on '.Carbon::parse($transaction->updated_at)->format('d M Y H:i').'. Payment verified by '.$manuallyVerifiedByUser->name.' ('.$manuallyVerifiedByUser->mobile_number->formatNational().')';
        }else{
            return $transaction->amount->amountWithCurrency.' marked as paid'.($transaction->paymentMethod ? ' using '.$transaction->paymentMethod->name : '').' for Order #'.$order->number.' on '.Carbon::parse($transaction->updated_at)->format('d M Y H:i').'. Payment verified by '.$manuallyVerifiedByUser->name.' ('.$manuallyVerifiedByUser->mobile_number->formatNational().')';
        }
    }

    /**
     *  Craft the store subscription paid messsage
     *
     *  @param Store $store
     *  @param Transaction $transaction
     *  @param Subscription $subscription
     *  @return string
     */
    public function craftStoreSubscriptionPaidMessage(Store $store, Transaction $transaction, Subscription $subscription) {
        return $transaction->amount->amountWithCurrency.' subscription successfully paid for '.$store->name.'. Valid till '.Carbon::parse($subscription->end_date)->format('d M Y H:i'). '. Enjoy ;)';
    }

    /**
     *  Craft the store marketing messsage
     *
     *  @param Store $store
     *  @return string
     */
    public function craftStoreMarketingMessage(Store $store) {
        //  return 'Your store '.$store->name.' is live! Customers can order via '.$store->ussd_shortcode.' or ' . $store->web_link . '. Share on WhatsApp, Facebook & flyers!';
        return 'Your store '.$store->name.' is live! Customers can order via '.$store->ussd_shortcode.'. Share on WhatsApp, Facebook & flyers!';
    }

    /**
     *  Craft the AI Assistant subscription paid messsage
     *
     *  @param Transaction $transaction
     *  @param Subscription $subscription
     *
     *  @return string
     */
    public function craftAIAssistantSubscriptionPaidMessage(Transaction $transaction, Subscription $subscription) {
        return $transaction->amount->amountWithCurrency.' paid for AI Assistant. Valid till '.Carbon::parse($subscription->end_date)->format('d M Y H:i');
    }
}
