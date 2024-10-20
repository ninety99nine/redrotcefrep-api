<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Order;
use App\Models\Store;
use App\Models\SmsAlert;
use App\Models\AiAssistant;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\PaymentMethod;
use App\Enums\CanSaveChanges;
use App\Traits\Base\BaseTrait;
use App\Models\SubscriptionPlan;
use App\Services\AWS\AWSService;
use App\Repositories\BaseRepository;
use App\Enums\UserVerfiedTransaction;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\OrderFullyPaidException;
use Illuminate\Validation\ValidationException;
use App\Exceptions\CannotDeleteTransactionException;
use App\Exceptions\OrderHasNoAmountOutstandingException;
use App\Exceptions\TransactionCannotBeUnCancelledException;
use App\Exceptions\OrderProhibitsTransactionsWhenCancelledException;
use App\Exceptions\OrderProhibitsMultiplePendingPaymentByUserException;
use App\Services\DirectPayOnline\DirectPayOnlineService;
use App\Services\OrangeMoney\OrangeMoneyService;

class TransactionRepository extends BaseRepository
{
    use BaseTrait;

    protected $requiresConfirmationBeforeDelete = false;

    /**
     *  Return the OrderRepository instance
     *
     *  @return OrderRepository
     */
    public function orderRepository()
    {
        return resolve(OrderRepository::class);
    }

    /**
     *  Return the ShortcodeRepository instance
     *
     *  @return ShortcodeRepository
     */
    public function shortcodeRepository()
    {
        return resolve(ShortcodeRepository::class);
    }

    /**
     *  Return the SubscriptionPlanRepository instance
     *
     *  @return SubscriptionPlanRepository
     */
    public function subscriptionPlanRepository()
    {
        return resolve(SubscriptionPlanRepository::class);
    }

    /**
     *  Eager load relationships on the given model
     *
     *  @param \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder $model
     *  @return TransactionRepository
     */
    public function eagerLoadTransactionRelationships($model) {

        $relationships = [];
        $countableRelationships = [];

        //  Check if we want to eager load the paying user on this transaction
        if( request()->input('with_paying_user') ) {

            //  Additionally we can eager load the paying user on this transaction
            array_push($relationships, 'paidByUser');

        }

        //  Check if we want to eager load the requesting user on this transaction
        if( request()->input('with_requesting_user') ) {

            //  Additionally we can eager load the requesting user on this transaction
            array_push($relationships, 'requestedByUser');

        }

        //  Check if we want to eager load the verifying user on this transaction
        if( request()->input('with_verifying_user') ) {

            //  Additionally we can eager load the verifying user on this transaction
            array_push($relationships, 'verifiedByUser');

        }

        //  Check if we want to eager load the payment method on this transaction
        if( request()->input('with_payment_method') ) {

            //  Additionally we can eager load the payment method on this transaction
            array_push($relationships, 'paymentMethod');

        }

        //  Check if we want to eager load the owner on this transaction
        if( request()->input('with_owner') ) {

            //  Additionally we can eager load the owner on this transaction
            array_push($relationships, 'owner');

        }

        if( !empty($relationships) ) {

            $model = ($model instanceof Transaction)
                ? $model->load($relationships)->loadCount($countableRelationships)
                : $model->with($relationships)->withCount($countableRelationships);

        }

        return $this->setModel($model);
    }

    /**
     *  Show the transactions
     *
     *  @return TransactionRepository
     */
    public function showTransactions()
    {
        $requestedByUser = $this->model->separateWordsThenLowercase(request()->input('requested_by_user'));
        $verifiedByUser = $this->model->separateWordsThenLowercase(request()->input('verified_by_user'));
        $paymentStatus = $this->model->separateWordsThenLowercase(request()->input('payment_status'));
        $paidByUser = $this->model->separateWordsThenLowercase(request()->input('paid_by_user'));
        $verifiedBy = $this->model->separateWordsThenLowercase(request()->input('verified_by'));
        $ownerType = $this->model->separateWordsThenLowercase(request()->input('owner_type'));
        $paymentMethod = request()->input('payment_method');
        $percentage = request()->filled('percentage');
        $amount = request()->filled('amount');

        //  Query the latest transactions first
        $transactions = $this->model->latest();

        if(!empty($paymentStatus)) {
            $transactions = $transactions->where('payment_status', $paymentStatus);
        }

        if(!empty($ownerType)) {
            $transactions = $transactions->where('owner_type', $ownerType);
        }

        if(request()->filled('has_proof_of_payment_photo')) {

            $hasProofOfPaymentPhoto = $this->isTruthy(request()->input('has_proof_of_payment_photo'));

            if($hasProofOfPaymentPhoto == true) {
                $transactions = $transactions->whereNotNull('proof_of_payment_photo');
            }elseif($hasProofOfPaymentPhoto == false) {
                $transactions = $transactions->whereNull('proof_of_payment_photo');
            }

        }

        if(request()->filled('cancelled')) {

            $cancelled = $this->isTruthy(request()->input('cancelled'));

            if($cancelled == true) {

                $transactions = $transactions->cancelled();

            }else if($cancelled == false) {

                $transactions = $transactions->notCancelled();

            }

        }

        if(!empty($amount)) {
            [$operator, $amount] = $this->extractOperatorAndValue($amount);
            $transactions = $transactions->where('amount', $operator, $amount);
        }

        if(!empty($percentage)) {
            [$operator, $percentage] = $this->extractOperatorAndValue($percentage);
            $transactions = $transactions->where('percentage', $operator, $percentage);
        }

        if(!empty($paymentMethod)) {
            $transactions = $transactions->whereHas('paymentMethod', function ($paymentMethodQuery) use ($paymentMethod) {
                $paymentMethodQuery->search($paymentMethod);
            });
        }

        if(!empty($paidByUser)) {
            $transactions = $transactions->whereHas('paidByUser', function ($paidByUserQuery) use ($paidByUser) {
                $paidByUserQuery->search($paidByUser);
            });
        }

        if(!empty($verifiedBy)) {
            $transactions = $transactions->where('verified_by', $verifiedBy);
        }

        if(!empty($verifiedByUser)) {
            $transactions = $transactions->whereHas('verifiedByUser', function ($verifiedByUserQuery) use ($verifiedByUser) {
                $verifiedByUserQuery->search($verifiedByUser);
            });
        }

        if(!empty($requestedByUser)) {
            $transactions = $transactions->whereHas('requestedByUser', function ($requestedByUserQuery) use ($requestedByUser) {
                $requestedByUserQuery->search($requestedByUser);
            });
        }

        //  Eager load the transaction relationships based on request inputs
        return $this->eagerLoadTransactionRelationships($transactions)->get();
    }

    /**
     *  Show the transaction while eager loading any required relationships
     *
     *  @return TransactionRepository
     */
    public function show()
    {
        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        //  Eager load the transaction relationships based on request inputs
        return $this->eagerLoadTransactionRelationships($transaction);
    }

    /**
     *  Create an order transaction
     *
     *  @return TransactionRepository
     */
    public function createOrderTransaction(Order $order, Request $request, UserVerfiedTransaction $userVerifiedTransaction)
    {
        //  Avoid creating transactions on a cancelled order
        $this->avoidInitiatingTransactionsOnCancelledOrder(
            $order, $userVerifiedTransaction == UserVerfiedTransaction::YES
                ? 'This order cannot be marked as paid because it has been cancelled'
                : 'This order cannot request payment because it has been cancelled'
        );

        /**
         *  @var Cart $cart
         */
        $cart = $order->cart;

        //  If the order has already been paid in full
        if( $order->isPaid() ) throw new OrderFullyPaidException();

        //  If the order cannot be paid due to no amount left to pay (possibly a free order)
        if( $order->amount_outstanding_percentage === 0 ) throw new OrderHasNoAmountOutstandingException;

        //  Check if the amount is provided
        if( $request->filled('amount') ) {

            //  Get the amount
            $amount = $request->input('amount');

            //  Check if the amount exceeds the remaining outstanding amount after deducting the pending amount
            if( $amount > ($outstandingAmountRemaining = $order->amount_outstanding->amount - $order->amount_pending->amount) ) {

                //  Convert to money format
                $amountSpecified = $order->convertToMoneyFormat($amount, $cart->currency);

                //  Convert to money format
                $outstandingAmountRemaining = $order->convertToMoneyFormat($outstandingAmountRemaining, $order->currency);

                //  Throw an Exception - Amount exceeded
                throw ValidationException::withMessages(['amount' => 'The amount specified '.$amountSpecified->amountWithCurrency.' is more than the remaining payable amount '.$outstandingAmountRemaining->amountWithCurrency.' for this order']);

            }

            //  Determine if this is a full payment (non-partial payment)
            $fullPayment = $amount == $order->amount_outstanding->amount;

            //  Calculate the percentage paid of the total cart grand total
            $percentage = $fullPayment ? $order->amount_outstanding_percentage : ($amount / $cart->grand_total->amount * 100);

        //  Check if the percentage is provided
        }elseif( $request->filled('percentage') ) {

            //  Get the percentage
            $percentage = $request->input('percentage');

            //  Check if the amount exceeds the remaining outstanding percentage after deducting the pending percentage
            if( $percentage > ($outstandingPercentageRemaining = $order->amount_outstanding_percentage - $order->amount_pending_percentage) ) {

                //  Throw an Exception - Percentage exceeded
                throw ValidationException::withMessages(['percentage' => 'The percentage specified '.$percentage.'% is more than the remaining payable percentage '.$outstandingPercentageRemaining.'% for this order']);

            }

            //  Determine if this is a full payment (non-partial payment)
            $fullPayment = $percentage == $order->amount_outstanding_percentage;

            //  Calculate the amount paid of the total cart grand total
            $amount = $fullPayment ? $order->amount_outstanding : ($percentage / 100 * $cart->grand_total->amount);

        }

        //  Set the transaction description
        $description = ($fullPayment ? 'Full' : 'Partial') . ' payment for order #'.$order->number . ($userVerifiedTransaction == UserVerfiedTransaction::YES ? ' confirmed by ' : ' requested by ') . request()->auth_user->name;

        //  Determine the payer of this amount (If the mobile number is provided then this payer is not the customer)
        if( $mobileNumber = $request->input('mobile_number') ) {

            //  Get the user matching the given mobile number (This user is the payer)
            $payerUserId = User::searchMobileNumber($mobileNumber)->first()->id;

        }else{

            //  Get the payer by user id (if provided)
            $paidByUserId = $request->input('paid_by_user_id');

            //  If the payer by user id is not specified or does not match the users associated with this order
            if(empty($paidByUserId) || $order->users()->where('id', $paidByUserId)->exists() == false) {

                //  The payer is the customer by default
                $payerUserId = $order->customer_user_id;

            }

        }

        //  Check if this transaction is a system verified transaction
        if($userVerifiedTransaction == UserVerfiedTransaction::NO) {

            //  Avoid requesting multiple pending payment for the same payer
            $this->avoidRequestingMultiplePendingPaymentsPerUser($order, $payerUserId);

        }

        /**
         *  If the transaction is a system verified transaction, then this is a requested transaction
         *  that will be later confirmed after the payment is successful e.g Paying online using a
         *  Credit/Debit card. Requested transactions are verified by the system after the payer
         *  makes payment using a generated payment link or shortcode.
         */
        $requestedByUserId = ($userVerifiedTransaction == UserVerfiedTransaction::NO) ? request()->auth_user->id : null;

        /**
         *  If the transaction is a user verified transaction, then this is verified transaction that
         *  was not verified by the system. Verified transactions are verified by the store management
         *  after the payer makes payment using other payment methods such as cash, cheque or any
         *  other payment that cannot be verified by the system.
         */
        $verifiedByUserId = ($userVerifiedTransaction == UserVerfiedTransaction::YES) ? request()->auth_user->id : null;

        //  Set the verified by (System / User)
        $verifiedBy = $verifiedByUserId == null ? 'System' : 'User';

        //  If verified by the user
        if($userVerifiedTransaction == UserVerfiedTransaction::YES) {

            //  Then this transaction is paid
            $paymentStatus = 'Paid';

        //  If verified by the system
        }else{

            //  Then this transaction is pending payment to be later verified as paid
            $paymentStatus = 'Pending Payment';

        }

        //  Set the payment method (if provided)
        $paymentMethodId = $request->input('payment_method_id');

        //  Create a new transaction
        $this->create([
            'payment_status' => $paymentStatus,
            'description' => $description,

            'amount' => $amount,
            'percentage' => $percentage,
            'currency' => $cart->currency,
            'payment_method_id' => isset($paymentMethodId) ? $paymentMethodId : null,

            'paid_by_user_id' => $payerUserId,

            /**
             *  If the verified_by_user_id is set, then the transaction is verified by
             *  the store management. If the requested_by_user_id is set then the
             *  transaction is verified by the system. They cannot have a valu at
             *  the same time. One must have a value while the other is NULL.
             *
             *  If both the requested_by_user_id and the verified_by_user_id are
             *  set, then it causes confusion as to who verified this transaction
             */
            'requested_by_user_id' => $requestedByUserId,
            'verified_by_user_id' => $verifiedByUserId,
            'verified_by' => $verifiedBy,

            'store_id' => $order->store_id,

            'owner_id' => $order->id,
            'owner_type' => $order->getResourceName()
        ]);

        //  Update the order amount balance
        $this->orderRepository()->setModel($order)->updateOrderAmountBalance();

        //  Return this transaction repository
        return $this;

    }

    /**
     *  Avoid transactions on a cancelled order
     */
    public function avoidInitiatingTransactionsOnCancelledOrder($order, $exceptionMessage = null)
    {
        //  If the order is cancelled
        if( $order->isCancelled() ) {

            /**
             *  Note that the Exception class does not accept NULL values,
             *  therefore we must implement custom conditional checks to
             *  determine whether to include a custom exception message
             *  or fallback to the default message.
             */
            if($exceptionMessage) {
                throw new OrderProhibitsTransactionsWhenCancelledException($exceptionMessage);
            }else{
                throw new OrderProhibitsTransactionsWhenCancelledException();
            }

        }

    }

    /**
     *  Avoid requesting multiple pending payment for the same payer
     */
    public function avoidRequestingMultiplePendingPaymentsPerUser($order, $payerUserId, $exceptionMessage = null)
    {
        //  Avoid requesting payment multiple times for the same payer
        if( $order->transactions()->notCancelled()->where(['payment_status' => 'Pending Payment', 'paid_by_user_id' => $payerUserId])->exists() ) {

            /**
             *  Note that the Exception class does not accept NULL values,
             *  therefore we must implement custom conditional checks to
             *  determine whether to include a custom exception message
             *  or fallback to the default message.
             */
            if($exceptionMessage) {
                throw new OrderProhibitsMultiplePendingPaymentByUserException($exceptionMessage);
            }else{
                throw new OrderProhibitsMultiplePendingPaymentByUserException();
            }

        }

    }

    /**
     *  Show the transactions
     *
     *  @param Store $store
     *  @return TransactionRepository
     */
    public function showStoreTransactions(Store $store)
    {
        $filter = $this->separateWordsThenLowercase(request()->input('filter'));
        $transactions = $this->queryStoreTransactionsByFilter($store, $filter)->orderBy('updated_at', 'desc');
        return $this->eagerLoadTransactionRelationships($transactions);
    }

    /**
     *  Query the store transactions by the specified filter
     *
     *  @param Store $store
     *  @param string $filter - The filter to query the transactions e.g Paid, Pending Payment, e.t.c
     *  @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function queryStoreTransactionsByFilter(Store $store, $filter)
    {
        //  Get the store transaction
        $transactions = $store->transactions();

        //  Normalize the filter
        $filter = $this->separateWordsThenLowercase($filter);

        //  Get the payer user id
        $payerUserId = request()->input('paid_by_user_id');

        if($payerUserId) {

            //  Query the store transactions mathcing the payer user id
            $transactions = $transactions->where('paid_by_user_id', $payerUserId);

        }

        //  Check if this filter is a type of transaction payment status
        if(collect(array_map('strtolower', Transaction::STATUSES))->contains($filter)) {

            //  Filter by transaction payment status
            $transactions = $transactions->where('payment_status', $filter);

        }

        //  Return the transactions query
        return $transactions;
    }

    /**
     *  Show the order transaction filters
     *
     *  @param Order $order
     *  @return array
     */
    public function showOrderTransactionFilters(Order $order)
    {
        $filters = collect(Transaction::FILTERS);

        /**
         *  $result = [
         *      [
         *          'name' => 'All',
         *          'total' => 6000,
         *          'total_summarized' => '6k'
         *      ],
         *      [
         *          'name' => 'Paid',
         *          'total' => 2000,
         *          'total_summarized' => '2k'
         *      ],
         *      [
         *          'name' => 'Pending Payment',
         *          'total' => 1000,
         *          'total_summarized' => '1k'
         *      ],
         *      ...
         *  ];
         */
        return $filters->map(function($filter) use ($order) {

            //  Query the transactions by the filter
            $total = $this->queryOrderTransactionsByFilter($order, $filter)->count();

            return [
                'name' => ucwords($filter),
                'total' => $total,
                'total_summarized' => $this->convertNumberToShortenedPrefix($total)
            ];

        })->toArray();
    }

    /**
     *  Show the transactions
     *
     *  @param Order $order
     *  @return TransactionRepository
     */
    public function showOrderTransactions(Order $order)
    {
        $filter = $this->separateWordsThenLowercase(request()->input('filter'));
        $transactions = $this->queryOrderTransactionsByFilter($order, $filter)->orderBy('updated_at', 'desc');
        return $this->eagerLoadTransactionRelationships($transactions);
    }

    /**
     *  Query the order transactions by the specified filter
     *
     *  @param Order $order
     *  @param string $filter - The filter to query the transactions e.g Paid, Pending Payment, e.t.c
     *  @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function queryOrderTransactionsByFilter(Order $order, $filter)
    {
        //  Get the order transaction
        $transactions = $order->transactions();

        //  Normalize the filter
        $filter = $this->separateWordsThenLowercase($filter);

        //  Get the payer user id
        $payerUserId = request()->input('paid_by_user_id');

        if($payerUserId) {

            //  Query the order transactions mathcing the payer user id
            $transactions = $transactions->where('paid_by_user_id', $payerUserId);

        }

        //  Check if this filter is a type of transaction payment status
        if(collect(array_map('strtolower', Transaction::STATUSES))->contains($filter)) {

            //  Filter by transaction payment status
            $transactions = $transactions->where('payment_status', $filter);

        }

        //  Return the transactions query
        return $transactions;
    }

    /**
     *  Create a store subscription transaction
     *
     *  @param Model $model - The resource this transaction is for
     *  @param Subscription $subscription The subscription created
     *  @param SubscriptionPlan $subscriptionPlan The subscription plan used
     *  @param Request $request The HTTP request
     *
     *  @return TransactionRepository
     */
    public function createTransaction(Model $model, SubscriptionPlan $subscriptionPlan, $request)
    {
        $currency = $subscriptionPlan->currency;
        $paymentMethodId = $request->input('payment_method_id');

        //  If this is a store subscription
        if(($subscription = $model) instanceof Subscription) {

            //  Get the subscription plan duration
            $duration = $this->subscriptionPlanRepository()->setModel($subscriptionPlan)->getSubscriptionPlanDuration($request);

            //  Calculate the subscription plan amount against the duration
            $amount = $this->convertToMoneyFormat($this->subscriptionPlanRepository()->setModel($subscriptionPlan)->calculateSubscriptionPlanAmountAgainstSubscriptionDuration($request), 'BWP');

            if(($store = $subscription->owner) instanceof Store) {

                //  Subscription to access Heavenly Fruits for 15 days priced at P30.00
                $description = 'Subscription to access ' . $store->name . ' for ' . $duration . ($duration == 1 ? ' day' : ' days') . ' priced at ' . $amount->amountWithCurrency;

            }else if($subscription->owner instanceof AiAssistant) {

                //  Subscription to access AI Assistant for 15 days priced at P30.00
                $description = 'Subscription to access AI Assistant for ' . $duration . ($duration == 1 ? ' day' : ' days') . ' priced at ' . $amount->amountWithCurrency;

            }

        }else if(($subscription = $model) instanceof SmsAlert) {

            //  Get the subscription plan sms credits
            $smsCredits = $this->subscriptionPlanRepository()->setModel($subscriptionPlan)->getSubscriptionPlanSmsCredits($request);

            //  Calculate the subscription plan amount against the sms credits
            $amount = $this->convertToMoneyFormat($this->subscriptionPlanRepository()->setModel($subscriptionPlan)->calculateSubscriptionPlanAmountAgainstSmsCredits($request), 'BWP');

            //  Payment for 10 sms alerts priced at P5.00
            $description = 'Payment for ' . $smsCredits . ($smsCredits == 1 ? ' sms alert' : ' sms alerts') . ' priced at ' . $amount->amountWithCurrency;

        }

        if(($order = $model) instanceof Order) {

            $storeId = $order->store_id;

        }

        //  Create a new transaction
        return $this->create([
            'percentage' => 100,
            'currency' => $currency,
            'payment_status' => 'Paid',
            'amount' => $amount->amount,
            'description' => $description,

            /**
             *  The requested_by_user_id is set to indicate that the transaction
             *  is verified by the system but the transaction being requested by
             *  the specified user. While the requested_by_user_id is set, the
             *  verified_by_user_id must be NULL. They cannot both have values
             *  at the same time.
             *
             *  If both the requested_by_user_id and the verified_by_user_id are
             *  set, then it causes confusion as to who verified this transaction
             */
            'verified_by_user_id' => null,
            'payment_method_id' => $paymentMethodId,
            'paid_by_user_id' => $this->chooseUser()->id,
            'requested_by_user_id' => request()->auth_user->id,

            'store_id' => $storeId ?? null,

            'owner_id' => $model->id,
            'owner_type' => $model->getResourceName()
        ]);

    }

    /**
     *  Renew transaction payment link
     *
     *  @return TransactionRepository
     */
    public function renewPaymentLink()
    {
        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        /**
         *  @var PaymentMethod $paymentMethod
         */
        $paymentMethod = $transaction->paymentMethod;

        //  Check if this is a card payment method
        if($paymentMethod->isDpo()) {

            //  Cancel the current payment link
            $transaction = DirectPayOnlineService::cancelPaymentLink($transaction);

            //  Create a new order payment link and attach it to this transaction
            $transaction = DirectPayOnlineService::createOrderPaymentLink($transaction);

        //  Check if this is a mobile wallet method
        }else if($paymentMethod->isOrangeMoney()) {

            //  Create a new order payment link and attach it to this transaction
            $transaction = OrangeMoneyService::createOrderPaymentLink($transaction);

        }

        return $this->setModel($transaction);
    }

    /**
     *  Cancel the transaction
     *
     *  @return TransactionRepository
     */
    public function cancel(Request $request)
    {
        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        /**
         *  Determine if this is an order transaction
         *
         *  @var Order $order
         */
        $isAnOrderTransaction = ($order = $transaction->owner) instanceof Order;

        //  If this transaction belongs to an order
        if( $isAnOrderTransaction ) {

            //  Avoid transaction modifications on a cancelled order
            $this->avoidInitiatingTransactionsOnCancelledOrder($order, 'Transaction changes are restricted while the order is cancelled');

        }

        //  Cancel the transaction
        parent::cancel($request);

        //  Check if this is an order transaction
        if( $isAnOrderTransaction ) {

            //  Update the order amount balance after cancelling this transaction
            $this->orderRepository()->setModel($order)->updateOrderAmountBalance();

        }

        //  Return this transaction repository
        return $this;
    }

    /**
     *  Uncancel the transaction
     *
     *  @return TransactionRepository
     */
    public function uncancel()
    {
        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        /**
         *  Determine if this is an order transaction
         *
         *  @var Order $order
         */
        $isAnOrderTransaction = ($order = $transaction->owner) instanceof Order;

        //  If this transaction belongs to an order
        if( $isAnOrderTransaction ) {

            //  Avoid transaction modifications on a cancelled order
            $this->avoidInitiatingTransactionsOnCancelledOrder($order, 'Transaction changes are restricted while the order is cancelled');

            //  If the transaction amount is more than the amount outstanding
            if( $transaction->amount->amount > ($outstandingAmountRemaining = $order->amount_outstanding->amount - $order->amount_pending->amount)  ) {

                //  Convert to money format
                $outstandingAmountRemaining = $transaction->convertToMoneyFormat($outstandingAmountRemaining, $order->currency);

                //  Throw an Exception - Amount exceeded
                throw new TransactionCannotBeUnCancelledException(
                    'The transaction cannot be uncancelled because the transaction amount '.$transaction->amount->amountWithCurrency.' is more than the remaining payable amount '.$outstandingAmountRemaining->amountWithCurrency.' for this order'
                );

            }

            //  If this transaction is pending payment
            if( $transaction->isPendingPayment() ) {

                //  Get the transaction payer's User ID
                $payerUserId = $transaction->paid_by_user_id;

                //  Avoid requesting multiple pending payment for the same payer
                $this->avoidRequestingMultiplePendingPaymentsPerUser($order, $payerUserId);

            }

        }

        //  Uncancel the transaction
        parent::uncancel();

        //  If this transaction belongs to an order
        if( $isAnOrderTransaction ) {

            //  Update the order amount balance after uncancelling this transaction
            $this->orderRepository()->setModel($order)->updateOrderAmountBalance();

        }

        //  Return this transaction repository
        return $this;
    }

    /**
     *  Create a payment shortcode for this transaction
     *
     *  This will allow the user to dial the shortcode pay via USSD
     *
     *  @return TransactionRepository
     */
    public function generatePaymentShortcode()
    {
        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        //  Get the User ID that this shortcode is reserved for
        $reservedForUserId = $transaction->paid_by_user_id;

        //  Request a payment shortcode for this pending transaction
        $this->shortcodeRepository()->generatePaymentShortcode($transaction, $reservedForUserId);

        //  Set the transaction as the repository model with the active payment shortcode
        $this->setModel(

            //  Load the active payment shortcode on this transaction
            $transaction->load('activePaymentShortcode')

        );

        return $this;
    }

    /**
     *  Remove a payment shortcode from this transaction
     *
     *  @return TransactionRepository
     */
    public function expirePaymentShortcode()
    {
        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        //  Get the transaction active payment shortcode
        $activePaymentShortcode = $transaction->activePaymentShortcode;

        //  If the transaction has an active payment shortcode
        if( $activePaymentShortcode ) {

            //  Expire the store payment shortcode.
            $this->shortcodeRepository()->setModel($activePaymentShortcode)->expireShortcode();

        }

        return $this;
    }

    /**
     *  Show the transaction proof of payment photo
     *
     *  @return array
     */
    public function showProofOfPaymentPhoto() {
        return [
            'proof_of_payment_photo' => $this->model->proof_of_payment_photo
        ];
    }

    /**
     *  Update the transaction proof of payment photo
     *
     *  @param \Illuminate\Http\Request $request
     *
     *  @return TransactionRepository
     */
    public function updateProofOfPaymentPhoto(Request $request) {

        //  Remove the exiting proof of payment photo (if any) and save the new proof of payment photo (if any)
        return $this->removeExistingProofOfPaymentPhoto(CanSaveChanges::NO)->storeProofOfPaymentPhoto($request);

    }

    /**
     *  Remove the existing transaction proof of payment photo
     *
     *  @param CanSaveChanges $canSaveChanges - Whether to save the transaction changes after deleting the proof of payment photo
     *  @return array | TransactionRepository
     */
    public function removeExistingProofOfPaymentPhoto($canSaveChanges = CanSaveChanges::YES) {

        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        //  Check if we have an existing proof of payment photo stored
        $hasExistingProofOfPaymentPhoto = !empty($transaction->proof_of_payment_photo);

        //  If the transaction has an existing proof of payment photo stored
        if( $hasExistingProofOfPaymentPhoto ) {

            //  Delete the proof of payment photo file
            AWSService::delete($transaction->proof_of_payment_photo);

        }

        //  If we should save these changes on the database
        if($canSaveChanges == CanSaveChanges::YES) {

            //  Save the transaction changes
            parent::update(['proof_of_payment_photo' => null]);

            return [
                'message' => 'Profile photo deleted successfully'
            ];

        //  If we should not save these changes on the database
        }else{

            //  Remove the proof of payment photo url reference from the transaction
            $transaction->proof_of_payment_photo = null;

            //  Set the modified transaction
            $this->setModel($transaction);

        }

        return $this;

    }

    /**
     *  Store the transaction proof of payment photo
     *
     *  @param \Illuminate\Http\Request $request
     *  @param CanSaveChanges $canSaveChanges - Whether to save the transaction changes after storing the proof of payment photo
     *  @param boolean $save
     *
     *  @return TransactionRepository|array
     */
    public function storeProofOfPaymentPhoto(Request $request, $canSaveChanges = CanSaveChanges::YES) {

        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        //  Check if we have a new proof of payment photo provided
        $hasNewProofOfPaymentPhoto = $request->hasFile('proof_of_payment_photo');

        /**
         *  Save the new proof of payment photo when the following condition is satisfied:
         *
         *  1) The proof of payment photo is provided when we are updating the proof of payment photo only
         *
         *  If the proof of payment photo is provided while creating or updating the transaction as
         *  a whole, then the proof of payment photo will be updated with the rest of the
         *  transaction details as a single query.
         *
         *  Refer to the saving() method of the TransactionObserver::class
         */
        $updatingTheTransactionProofOfPaymentPhotoOnly = $request->routeIs('transaction.proof.of.payment.photo.update');

        //  If we have a new proof of payment photo provided
        if( $hasNewProofOfPaymentPhoto ) {

            //  Save the proof of payment photo on AWS and update the transaction with the proof of payment photo url
            $transaction->proof_of_payment_photo = AWSService::store('proof_of_payment_photos', $request->proof_of_payment_photo);

            //  Set the modified transaction
            $this->setModel($transaction);

            if( $canSaveChanges == CanSaveChanges::YES || $updatingTheTransactionProofOfPaymentPhotoOnly ) {

                //  Save the transaction changes
                $transaction->save();

            }

        }

        if( $updatingTheTransactionProofOfPaymentPhotoOnly ) {

            //  Return the proof of payment photo image url
            return ['proof of payment photo' => $transaction->proof_of_payment_photo];

        }

        return $this;

    }

    /**
     *  Delete an existing transaction
     *
     *  @return TransactionRepository
     *  @throws CannotDeleteTransactionException
     */
    public function deleteTransaction()
    {
        /**
         *  @var Transaction $transaction
         */
        $transaction = $this->model;

        /**
         *  @var PaymentMethod $paymentMethod
         */
        $paymentMethod = $transaction->paymentMethod;

        //  Check if this is a card payment method
        if($paymentMethod->isDpo()) {

            //  Cancel the current payment link
            $transaction = DirectPayOnlineService::cancelPaymentLink($transaction);
        }

        /**
         *  Determine if this is an order transaction
         *
         *  @var Order $order
         */
        $isAnOrderTransaction = ($order = $transaction->owner) instanceof Order;

        //  If this transaction is associated with an order
        if($isAnOrderTransaction) {

            //  Delete the transaction
            $transactionRepository = parent::delete();

            //  Update the order amount balance
            $this->orderRepository()->setModel($order)->updateOrderAmountBalance();

            //  Return the transaction repository
            return $transactionRepository;

        }else{

            throw new CannotDeleteTransactionException;

        }
    }
}
