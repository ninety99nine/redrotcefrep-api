<?php

namespace App\Services\DirectPayOnline;

use Exception;
use App\Models\Store;
use App\Models\Order;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Services\Sms\SmsService;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Orders\OrderPaidUsingDPO;
use App\Exceptions\DPOCompanyTokenNotProvidedException;
use App\Jobs\SendSms;

class DirectPayOnlineService
{
    public static $paymentTimeLimitInHours = 24;

    /**
     *  Return the OrderRepository instance
     *
     *  @return OrderRepository
     */
    public static function orderRepository()
    {
        return resolve(OrderRepository::class);
    }

    /**
     *  Create a new order payment link and attach it to this transaction
     *
     * @param Transaction $transaction
     * @return Transaction
     */
    public static function createOrderPaymentLink(Transaction $transaction)
    {
        $relationships = [];

        if($transaction->relationLoaded('paidByUser') == false) {
            array_push($relationships, 'paidByUser');
        }

        if($transaction->relationLoaded('owner') == false) {
            if(strtolower($transaction->owner_type) == 'order') {
                array_push($relationships, 'owner.store', 'owner.cart.productLines');
            }
        }

        if(count($relationships)) {
            $transaction = $transaction->load($relationships);
        }

        /**
         *  @var Order $order
         */
        $order = $transaction->owner;

        /**
         *  @var Cart $cart
         */
        $cart = $order->cart;

        /**
         *  @var Store $store
         */
        $store = $order->store;
        $companyToken = $store->dpo_company_token;

        if(empty($companyToken)) {
            throw new DPOCompanyTokenNotProvidedException();
        }

        $transactionId = $transaction->id;
        $transactionCurrency = $transaction->currency;
        $transactionAmount = $transaction->amount->amount;

        $paidByUser = $transaction->paidByUser;
        $lastName = $paidByUser->last_name ?? null;
        $firstName = $paidByUser->first_name ?? null;
        $mobileNumber = $paidByUser->mobile_number ?? null;

        $metaData = 'Store ID: '.$store->id.'\n'.
                    'Store Name: '.$store->name.'\n'.
                    'Order ID: '.$order->id.'\n'.
                    'Order Number: '.$order->number.'\n'.
                    'Transaction ID: '.$transaction->id.'\n'.
                    'Transaction Number: '.$transaction->number.'\n'.
                    'Transaction Description: '.$transaction->description;

        /*
        $services = collect($cart->productLines)->map(function($productLine) use ($order) {

            // ServiceType is provided by DPO: (Use code only)
            // 3854 - Test Product
            // 3854 - Test Service
            return '<Service>
                        <ServiceType>3854</ServiceType>
                        <ServiceDescription>'.$productLine->quantity .'x('. $productLine->name.')'.'</ServiceDescription>
                        <ServiceDate>'.$order->created_at.'</ServiceDate>
                    </Service>';

        })->join('');
        */

        $services = '<Service>
                        <ServiceType>3854</ServiceType>
                        <ServiceDescription>'.$transaction->description.' - Total '.$transaction->amount->amountWithCurrency.'</ServiceDescription>
                        <ServiceDate>'.$order->created_at.'</ServiceDate>
                    </Service>';

        //  Construct Direct Pay Online (DPO) XML request
        $xmlRequest = '
            <?xml version="1.0" encoding="utf-8"?>
            <API3G>
                <CompanyToken>'.$companyToken.'</CompanyToken>
                <Request>createToken</Request>
                <Transaction>
                    <CompanyRefUnique>1</CompanyRefUnique>
                    <CompanyRef>'.$transactionId.'</CompanyRef>
                    <PaymentAmount>'.$transactionAmount.'</PaymentAmount>
                    <PaymentCurrency>'.$transactionCurrency.'</PaymentCurrency>
                    <CompanyAccRef>'.$order->number.'</CompanyAccRef>
                    <PTL>'.self::$paymentTimeLimitInHours.'</PTL>'.
                    ($lastName == null ? '' : '<customerLastName>'.$lastName.'</customerLastName>').
                    ($firstName == null ? '' : '<customerFirstName>'.$firstName.'</customerFirstName>').
                    ($mobileNumber == null ? '' : '<customerPhone>'.$mobileNumber->withExtension.'</customerPhone>').
                    '<MetaData>'.$metaData.'</MetaData>
                    <customerCity>Gabarone</customerCity>
                    <customerDialCode>'.config('app.DPO_COUNTRY_CODE').'</customerDialCode>
                    <customerCountry>'.config('app.DPO_COUNTRY_CODE').'</customerCountry>
                    <CustomerZip>0000</CustomerZip>
                    '//<RedirectURL>'.route('payment.success.page', ['transaction' => $transaction->id]).'</RedirectURL>
                     //<BackURL>'.route('perfect.pay.advertisement.page').'</BackURL>
                    .'<TransactionType>Pending Payment</TransactionType>
                    <TransactionSource>Marketplace</TransactionSource>
                    <DefaultPayment>CC</DefaultPayment>
                </Transaction>
                <Services>'.$services.'</Services>
            </API3G>';

        try {

            $client = new Client();

            $url = config('app.DPO_CREATE_TOKEN_URL');

            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/xml',
                ],
                'body' => $xmlRequest,
            ]);

            // Parse the XML response
            $xmlResponse = simplexml_load_string($response->getBody());

            // Extract the necessary information from the response
            $result = (string) $xmlResponse->Result;
            $resultExplanation = (string) $xmlResponse->ResultExplanation;
            $transToken = (string) $xmlResponse->TransToken;
            $transRef = (string) $xmlResponse->TransRef;

            //  If the token was created successfully
            if($result === '000') {

                $paymentUrl = config('app.DPO_PAYMENT_URL').'?ID='.$transToken;

                $transaction->update([
                    'dpo_payment_url' => $paymentUrl,
                    'dpo_payment_url_expires_at' => now()->addHours(self::$paymentTimeLimitInHours)
                ]);

                return $transaction->fresh();

            }else{

                // Handle any exceptions or errors that occurred during the API request
                throw new Exception($resultExplanation);

            }

            return $transaction;

        } catch (Exception $e) {

            // Handle any exceptions or errors that occurred during the API request
            throw $e;

        }

    }

    /**
     *  Verify the payment and capture the response information on the transaction
     *
     * @param Transaction $transaction
     * @param Request $request
     * @return Transaction
     */
    public static function verifyPayment(Transaction $transaction, Request $request)
    {
        $client = new Client();

        //  Get the request information
        $pnrID = $request->input('PnrID');
        $transID = $request->input('TransID');
        $companyRef = $request->input('CompanyRef');
        $ccdApproval = $request->input('CCDapproval');
        $transactionToken = $request->input('TransactionToken');

        /**
         *  @var Order $order
         */
        $order = $transaction->owner;

        /**
         *  @var Store $store
         */
        $store = $order->store;
        $companyToken = $store->dpo_company_token;

        //  Construct Direct Pay Online (DPO) XML request
        $xmlRequest = '
            <?xml version="1.0" encoding="utf-8"?>
            <API3G>
                <Request>verifyToken</Request>
                <CompanyToken>'.$companyToken.'</CompanyToken>
                <TransactionToken>'.$transactionToken.'</TransactionToken>
            </API3G>';

        try {

            /**
             *  DPO throws an error when the verify token URL does not end with a suffixed "/" e.g
             *
             *  https://secure.3gdirectpay.com/API/v6/ (works fine)
             *  https://secure.3gdirectpay.com/API/v6  (throws an error)
             *
             *  We need to always make sure that we have checked for this issue before
             *  consuming the DPO API to verify the transaction.
             */
            $originalUrl = config('app.DPO_VERIFY_TOKEN_URL');

            // Check if the URL ends with "/"
            $url = Str::endsWith($originalUrl, '/') ? $originalUrl : Str::finish($originalUrl, '/');

            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/xml',
                ],
                'body' => $xmlRequest
            ]);

            // Parse the XML response
            $xmlResponse = simplexml_load_string($response->getBody());

            // Extract the necessary information from the response
            $result = (string) $xmlResponse->Result;
            $accRef = (string) $xmlResponse->AccRef;
            $fraudAlert = (string) $xmlResponse->FraudAlert;
            $customerZip = (string) $xmlResponse->CustomerZip;
            $customerName = (string) $xmlResponse->CustomerName;
            $customerCity = (string) $xmlResponse->CustomerCity;
            $customerPhone = (string) $xmlResponse->CustomerPhone;
            $customerCredit = (string) $xmlResponse->CustomerCredit;
            $fraudExplnation = (string) $xmlResponse->FraudExplnation;
            $customerCountry = (string) $xmlResponse->CustomerCountry;
            $customerAddress = (string) $xmlResponse->CustomerAddress;
            $resultExplanation = (string) $xmlResponse->ResultExplanation;
            $transactionAmount = (string) $xmlResponse->TransactionAmount;
            $customerCreditType = (string) $xmlResponse->CustomerCreditType;
            $transactionApproval = (string) $xmlResponse->TransactionApproval;
            $transactionCurrency = (string) $xmlResponse->TransactionCurrency;
            $transactionNetAmount = (string) $xmlResponse->TransactionNetAmount;
            $mobilePaymentRequest = (string) $xmlResponse->MobilePaymentRequest;
            $transactionFinalAmount = (string) $xmlResponse->TransactionFinalAmount;
            $transactionFinalCurrency = (string) $xmlResponse->TransactionFinalCurrency;
            $transactionSettlementDate = (string) $xmlResponse->TransactionSettlementDate;
            $transactionRollingReserveDate = (string) $xmlResponse->TransactionRollingReserveDate;
            $transactionRollingReserveAmount = (string) $xmlResponse->TransactionRollingReserveAmount;

            //  If the payment was verified successfully
            if($result === '000') {

                $dpoPaymentResponse = [
                    'onProcessPaymentResponse' => [
                        'pnrID' => $pnrID,
                        'transID' => $transID,
                        'companyRef' => $companyRef,
                        'ccdApproval' => $ccdApproval,
                        'transactionToken' => $transactionToken,
                    ],
                    'onVerifyPaymentResponse' => [
                        'result' => $result,
                        'accRef' => $accRef,
                        'fraudAlert' => $fraudAlert,
                        'customerZip' => $customerZip,
                        'customerName' => $customerName,
                        'customerCity' => $customerCity,
                        'customerPhone' => $customerPhone,
                        'customerCredit' => $customerCredit,
                        'fraudExplnation' => $fraudExplnation,
                        'customerCountry' => $customerCountry,
                        'customerAddress' => $customerAddress,
                        'resultExplanation' => $resultExplanation,
                        'transactionAmount' => $transactionAmount,
                        'customerCreditType' => $customerCreditType,
                        'transactionApproval' => $transactionApproval,
                        'transactionCurrency' => $transactionCurrency,
                        'transactionNetAmount' => $transactionNetAmount,
                        'mobilePaymentRequest' => $mobilePaymentRequest,
                        'transactionFinalAmount' => $transactionFinalAmount,
                        'transactionFinalCurrency' => $transactionFinalCurrency,
                        'transactionSettlementDate' => $transactionSettlementDate,
                        'transactionRollingReserveDate' => $transactionRollingReserveDate,
                        'transactionRollingReserveAmount' => $transactionRollingReserveAmount,
                    ],
                ];

                //  Capture the response information on this transaction
                $transaction->update([
                    'payment_status' => 'Paid',
                    'dpo_payment_response' => $dpoPaymentResponse
                ]);

                //  Update the order amount balance
                self::orderRepository()->setModel($order)->updateOrderAmountBalance();

                //  Refresh the transaction
                $transaction = $transaction->fresh();

                /**
                 *  Get the users associated with this order as a customer or friend
                 *
                 *  @var Collection<User> $users
                 */
                $users = $order->users()->get();

                /**
                 *  Get the store team members (exclude the users associated with this order as a customer or friend)
                 *
                 *  @var Collection<User> $teamMembers
                 */
                $teamMembers = $store->teamMembers()->whereNotIn('users.id', $users->pluck('id'))->joinedTeam()->get();

                //  Send order payment notification to the customer, friends and team members
                Notification::send(
                    //  Send notifications to the team members who joined
                    collect($teamMembers)->merge(
                        //  As well as the customer and friends who were tagged on this order
                        $users
                    ),
                    new OrderPaidUsingDPO($order, $store, $transaction)
                );

                foreach($users->concat($teamMembers) as $user) {

                    // Send order mark as verified payment sms to user
                    SendSms::dispatch(
                        $order->craftOrderMarkAsVerifiedPaymentMessage($store, $transaction),
                        $user->mobile_number->withExtension,
                        $store, null, null
                    );

                }

            }else{

                // Handle any exceptions or errors that occurred during the API request
                throw new Exception($resultExplanation);

            }

            return $transaction;

        } catch (Exception $e) {

            // Handle any exceptions or errors that occurred during the API request
            throw $e;

        }

    }

    /**
     *  Cancel the payment link
     *
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public static function cancelPaymentLink(Transaction $transaction)
    {
        $client = new Client();

        /**
         *  @var Order $order
         */
        $order = $transaction->owner;

        /**
         *  @var Store $store
         */
        $store = $order->store;
        $companyToken = $store->dpo_company_token;

        $transactionToken = $transaction->dpo_transaction_token;

        if(!empty($transactionToken)) {

            //  Construct Direct Pay Online (DPO) XML request
            $xmlRequest = '
                <?xml version="1.0" encoding="utf-8"?>
                <API3G>
                    <Request>cancelToken</Request>
                    <CompanyToken>'.$companyToken.'</CompanyToken>
                    <TransactionToken>'.$transactionToken.'</TransactionToken>
                </API3G>';

            try {

                $url = config('app.DPO_CANCEL_TOKEN_URL');

                $response = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body' => $xmlRequest
                ]);

                // Parse the XML response
                $xmlResponse = simplexml_load_string($response->getBody());

                // Extract the necessary information from the response
                $result = (string) $xmlResponse->Result;
                $resultExplanation = (string) $xmlResponse->ResultExplanation;

                //  If the payment was verified successfully
                if($result === '000') {

                }else{

                    // Handle any exceptions or errors that occurred during the API request
                    throw new Exception($resultExplanation);

                }

                return $transaction;

            } catch (Exception $e) {

                // Handle any exceptions or errors that occurred during the API request
                throw $e;

            }

        }else{

            return $transaction;

        }
    }
}
