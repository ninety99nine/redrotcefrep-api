<?php

namespace App\Services\Billing\Airtime;

use Throwable;
use GuzzleHttp\Client;
use App\Enums\CacheName;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Helpers\CacheManager;
use Illuminate\Support\Facades\Log;
use App\Enums\TransactionFailureType;
use App\Enums\TransactionPaymentStatus;

class OrangeAirtimeService
{
    /**
     *  Bill user on their airtime
     *
     *  @param string $msisdn - The MSISDN (mobile number) of the subscriber to be billed e.g +26772000001
     *  @param Transaction $transaction - The Transaction Model
     *
     *  @return Transaction
     */
    public static function billUsingAirtime(string $msisdn, Transaction $transaction): Transaction
    {
        $referenceCode = Str::uuid();
        $msisdn = ltrim($msisdn, '+');
        $clientCorrelator = Str::uuid();
        $pricingPlan = $transaction->owner;
        $description = $transaction->description;
        $amount = (float) $transaction->amount->amount;

        $productId = $pricingPlan->type;
        $purchaseCategoryCode = $pricingPlan->name;

        $clientId = config('app.ORANGE_AIRTIME_BILLING_CLIENT_ID');
        $billingEnabled = config('app.ORANGE_AIRTIME_BILLING_ENABLED');
        $onBehalfOf = config('app.ORANGE_AIRTIME_BILLING_ON_BEHALF_OF');
        $clientSecret = config('app.ORANGE_AIRTIME_BILLING_CLIENT_SECRET');

        try {

            $ratingType = null;
            $failureType = null;
            $failureReason = null;
            $failedAttempts = null;
            $fundsAfterDeduction = null;
            $fundsBeforeDeduction = null;

            if($billingEnabled) {

                /**
                 *  ------------------------
                 *  Request the access token
                 *  ------------------------
                 *
                 *  On Success, the response payload is as follows:
                 *
                 *  [
                 *      "status" => true
                 *      "body" => [
                 *          "access_token" => "c0352550-14c4-3a74-b82e-31bd8d09a556",
                 *          "scope" => "am_application_scope default",
                 *          "token_type" => "Bearer",
                 *          "expires_in" => 3600
                 *      ]
                 *  ]
                 *
                 *  On Fail, the response payload is as follows =>
                 *
                 *  [
                 *      "status" => false
                 *      "body" => [
                 *          "error_description" => "Oauth application is not in active state.",
                 *          "error" => "invalid_client"
                 *      ]
                 *  ]
                 */
                $response = self::requestNewAirtimeBillingAccessToken($clientId, $clientSecret);

                if($status = $response['status']) {

                    $accessToken = $response['body']['access_token'];

                    /**
                     *  -----------------------------
                     *  Request the product inventory
                     *  -----------------------------
                     *
                     *  On Success, the response payload is as follows:
                     *
                     *  [
                     *      "status" => true
                     *      "body" => [
                     *          [
                     *              "id" => "8037c89b-f204-428e-9336-d3a4bca1b3fe",
                     *              "ratingType" => "Postpaid",
                     *              "status" => "Active",
                     *              "isBundle" => true,
                     *              "startDate" => "2020-09-17T00 =>00 =>00+0000",
                     *              "productOffering" => [
                     *                  "id" => "Orange_Postpaid",
                     *                  "name" => "MySim"
                     *              ]
                     *          ]
                     *      ]
                     *  ]
                     *
                     *  On Fail, the response payload is as follows =>
                     *
                     *  [
                     *      "status" => false
                     *      "body" => [
                     *          "code" => 4001,
                     *          "message" => "Missing parameter",
                     *          "description" => "Parameter publicKey is missing, null or empty"
                     *      ]
                     *  ]
                     */
                    $response = self::requestAirtimeBillingProductInventory($msisdn, $accessToken);

                    if($status = $response['status']) {

                        //  Get the first item of the product inventory array
                        $productInventory = $response['body'][0];

                        //  Determine if this is an active account
                        $isAnActiveAccount = $productInventory['status'] == 'Active';

                        //  If this is an active account
                        if( $status = $isAnActiveAccount ) {

                            //  Get the account rating type
                            $ratingType = $productInventory['ratingType'];

                            //  Determine if this is a hybrid account
                            $isHybridAccount = ($ratingType == 'Hybrid');

                            //  Determine if this is a prepaid account
                            $isPrepaidAccount = ($ratingType == 'Prepaid');

                            //  Determine if this is a postpaid account
                            $isPostpaidAccount = ($ratingType == 'Postpaid');

                            //  If this is a postpaid account, we assume to always have enough funds
                            $hasEnoughFunds = $isPostpaidAccount;

                            /**
                             *  If this is any other account such except a postpaid account, then we need
                             *  to check the account balance to know if we have enough funds.
                             */
                            if( !$isPostpaidAccount ) {

                                /**
                                 *  -----------------------------
                                 *  Request the usage consumption
                                 *  -----------------------------
                                 *
                                 *  On Success, the response payload is as follows:
                                 *
                                 *  [
                                 *      "status" => true
                                 *      "body" => [
                                 *          "id" => "2b778311-ab1b-4f9b-bdb7-e8f3632a6ca9",
                                 *          "effectiveDate" => "2022-01-21T13:24:33+0000",
                                 *          "bucket" => [
                                 *              ...,
                                 *              [
                                 *                  "id" => "OCS-0",
                                 *                  "name" => "Main Balance",
                                 *                  "usageType" => "accountBalance",
                                 *                  "bucketBalance" => [
                                 *                      [
                                 *                          "unit" => "BWP",
                                 *                          "remainingValue" => 0,
                                 *                          "validFor" => [
                                 *                              "startDateTime" => "2019-04-04T00:00:00+0000",
                                 *                              "endDateTime" => "2023-01-06T00:00:00+0000"
                                 *                          ]
                                 *                      ]
                                 *                  ]
                                 *              ],
                                 *              ...
                                 *          ]
                                 *      ]
                                 *  ]
                                 *
                                 *  On Fail, the response payload is as follows =>
                                 *
                                 *  [
                                 *      "status" => false
                                 *      "body" => [
                                 *          "code" => 4001,
                                 *          "message" => "Missing parameter",
                                 *          "description" => "Parameter publicKey is missing, null or empty"
                                 *      ]
                                 *  ]
                                 */
                                $response = self::requestAirtimeBillingUsageConsumption($msisdn, $accessToken);

                                if($status = $response['status']) {

                                    //  Get the bucket with the id of "OCS-0" as it holds information about the "Main Balance"
                                    $accountMainBalanceBucket = collect($response['body']['bucket'])->firstWhere('id', 'OCS-0');

                                    //  If the bucket with the id of "OCS-0" was extracted successfully
                                    if( $status = !empty($accountMainBalanceBucket) ) {

                                        //  Get the remaining value (The Airtime left that we can bill from the bucket balance)
                                        $fundsBeforeDeduction = $accountMainBalanceBucket['bucketBalance'][0]['remainingValue'];

                                        //  Determine if we have enough funds
                                        $status = $hasEnoughFunds = ($fundsBeforeDeduction >= $amount);

                                        //  If we do not have enough funds
                                        if( !$hasEnoughFunds ) {

                                            $failureType = TransactionFailureType::INSUFFICIENT_FUNDS->value;
                                            $failureReason = 'You do not have enough funds to complete this transaction';

                                        }

                                    }else{

                                        $failureType = TransactionFailureType::MISSING_MAIN_BALANCE_INFORMATION->value;
                                        $failureReason = 'Could not process this transaction because of missing information on your account';

                                    }

                                }else{

                                    $failureType = TransactionFailureType::USAGE_CONSUMPTION_RETRIEVAL_FAILED->value;
                                    $failureReason = 'Could not process this transaction, please try again';
                                    $failedAttempts = json_encode($response['body']['failed_attempts']);

                                }

                            }

                            if( $status ) {

                                /**
                                 *  --------------------------
                                 *  Request to bill subscriber
                                 *  --------------------------
                                 *
                                 *  On Success, the response payload is as follows:
                                 *
                                 *  [
                                 *      "status" => true
                                 *      "body" => [
                                 *          "amountTransaction" => [
                                 *              "endUserId" => "tel:+ [MSISDN_WITH_COUNTRYCODE]",
                                 *              "paymentAmount" => [
                                 *                  "chargingInformation" => [
                                 *                      "amount" => 5 ,
                                 *                      "currency" => "XOF",
                                 *                      "description" => [
                                 *                          "Short description of the charge"
                                 *                      ]
                                 *                  ],
                                 *                  "totalAmountCharged" => 5 ,
                                 *                  "chargingMetaData" => [
                                 *                      "productId" => "Daily_subscription",
                                 *                      "serviceId" => "Football_results",
                                 *                      "purchaseCategoryCode" => "Daily_autorenew_pack "
                                 *                  ]
                                 *              ],
                                 *              "clientCorrelator" => "unique-technical-id",
                                 *              "referenceCode" => "Service_provider_payment_reference",
                                 *              "transactionOperationStatus" => "Charged",
                                 *              "serverReferenceCode" => "5b9bb0235c2dbe6d16d6b5b2",
                                 *              "resourceURL" => "/payment/v1/tel%3A%2B [MSISDN_WITH_COUNTRYCODE] /transactions/amount/5b9bb0235c2dbe6d16d6b5b2",
                                 *              "link" => []
                                 *          ]
                                 *      ]
                                 *  ]
                                 *
                                 *  On Fail, the response payload is as follows =>
                                 *
                                 *  Policy error example:
                                 *
                                 *  [
                                 *      "status" => false
                                 *      "body" => [
                                 *          "requestError" => [
                                 *              "policyException" => [
                                 *                  "messageId" => "POL2206",
                                 *                  "text" => "User forbidden."
                                 *              ]
                                 *          ]
                                 *      ]
                                 *  ]
                                 *
                                 *  or
                                 *
                                 *  Server error example:
                                 *
                                 *  [
                                 *      "status" => false
                                 *      "body" => [
                                 *          "requestError" => [
                                 *              "serviceException" => [
                                 *                  "messageId": "SVC0005",
                                 *                  "text": "duplicate correlatorId cc1d2d34",
                                 *                  "variables": [
                                 *                      "cc1d2d34"
                                 *                  ]
                                 *              ]
                                 *          ]
                                 *      ]
                                 *  ]
                                 */
                                $response = self::requestAirtimeBillingDeductFee($msisdn, $amount, $onBehalfOf, $productId, $purchaseCategoryCode, $description, $accessToken, $clientCorrelator, $referenceCode);

                                if($status = $response['status']) {

                                    //  The billing is successful at this point

                                }else{

                                    $failureType = TransactionFailureType::DEDUCT_FEE_FAILED->value;
                                    $failureReason = 'Could not process this transaction, please try again';
                                    $failedAttempts = json_encode($response['body']['failed_attempts']);
                                }

                            }

                        }else{
                            $failureType = TransactionFailureType::INACTIVE_ACCOUNT->value;
                            $failureReason = 'This account is currently inactive. Please contact customer support';
                        }

                    }else{
                        $failureType = TransactionFailureType::PRODUCT_INVENTORY_RETRIEVAL_FAILED->value;
                        $failureReason = 'Could not process this transaction, please try again';
                        $failedAttempts = json_encode($response['body']['failed_attempts']);
                    }

                }else{
                    $failureType = TransactionFailureType::TOKEN_GENERATION_FAILED->value;
                    $failureReason = 'Could not process this transaction, please try again';
                    $failedAttempts = json_encode($response['body']['failed_attempts']);
                }

            }else{

                $status = true;
                $failureType = null;
                $failureReason = null;
                $ratingType = 'Prepaid';
                $fundsBeforeDeduction = 100;

            }

            if($status) {
                $fundsAfterDeduction = $fundsBeforeDeduction - $amount;
            }

            $metadata = [
                'rating_type' => $ratingType,
                'reference_code' => $referenceCode,
                'client_correlator' => $clientCorrelator,
                'funds_after_deduction' => $fundsAfterDeduction,
                'funds_before_deduction' => $fundsBeforeDeduction
            ];

            if(!empty($failedAttempts)) {
                $metadata['failed_attempts'] = $failedAttempts;
            }

            //  Update billing transaction
            $transaction->update([
                'metadata' => $metadata,
                'failure_type' => $failureType,
                'failure_reason' => $failureReason,
                'payment_status' => $status ? TransactionPaymentStatus::PAID->value : TransactionPaymentStatus::FAILED_PAYMENT->value
            ]);

            //  Return the transaction
            return $transaction;

        } catch (Throwable $e) {

            $failureType = TransactionFailureType::INTERNAL_FAILURE->value;
            $failureReason = 'Could not process this transaction, please try again';

            Log::error('Airtime Billing Fatal Error (Stage 0)', [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'msisdn' => $msisdn,
                'pricing_plan' => $pricingPlan->id,
                'transaction_id' => $transaction->id,
            ]);

            $metadata = [
                'failed_attempts' => [
                    [
                        'attempts' => 1,
                        'error_code' => $e->getCode() ?: null,
                        'error_description' => $e->getMessage()
                    ]
                ]
            ];

            $transaction->update([
                'metadata' => $metadata,
                'failure_type' => $failureType,
                'failure_reason' => $failureReason,
                'payment_status' => TransactionPaymentStatus::FAILED_PAYMENT->value
            ]);

            //  Return the transaction
            return $transaction;

        }

    }

    /**
     * Requests a new airtime billing access token.
     *
     * @param string $clientId - The billing account client ID provided by the Mobile Network Operator.
     * @param string $clientSecret - The billing account client secret provided by the Mobile Network Operator.
     *
     * @return array - Contains the response status and body.
     *
     * On Success, the response payload is as follows:
     *
     * {
     *     "access_token": "c0352550-14c4-3a74-b82e-31bd8d09a556",
     *     "scope": "am_application_scope default",
     *     "token_type": "Bearer",
     *     "expires_in": 3600
     * }
     *
     * On Failure, the response payload is as follows:
     *
     * {
     *     "error_description": "Oauth application is not in active state.",
     *     "error": "invalid_client"
     * }
     */
    public static function requestNewAirtimeBillingAccessToken($clientId, $clientSecret): array
    {
        $cacheManager = new CacheManager(CacheName::AIRTIME_BILLING_ACCESS_TOKEN_RESPONSE);

        // Check cached token
        if ($cacheManager->has()) {
            $cache = $cacheManager->get();
            if (isset($cache['expires_at']) && now()->lt($cache['expires_at'])) {
                return $cache['data'];
            }
        }

        $retries = 0;
        $maxRetries = 2;
        $failedAttempts = [];

        while ($retries <= $maxRetries) {

            $attempts = $retries + 1;

            try {

                // Set the request endpoint
                $endpoint = config('app.ORANGE_AIRTIME_BILLING_URL') . '/token';

                // Set the request options
                $options = [
                    'headers' => [
                        'Content-type' => 'application/x-www-form-urlencoded',
                        'Accept' => 'application/json',
                    ],
                    'form_params' => [
                        "client_id" => trim($clientId),
                        "grant_type" => "client_credentials",
                        "client_secret" => trim($clientSecret),
                    ],
                    'verify' => false,
                ];

                // Create a new HTTP Guzzle Client
                $httpClient = new Client();

                // Perform the HTTP request
                $response = $httpClient->request('POST', $endpoint, $options);

                // Parse the response body
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                // Get the response status code
                $statusCode = $response->getStatusCode();

                // Prepare the response data
                $data = [
                    'status' => ($statusCode == 200),
                    'attempts' => $attempts,
                    'body' => $bodyAsArray
                ];

                // Handle successful response
                if ($statusCode === 200) {

                    // Calculate expiry timestamp
                    $expiresAt = now()->addSeconds($bodyAsArray['expires_in'])->subSeconds(120);

                    // Cache the token
                    $cacheManager->put([
                        'data' => $data,
                        'expires_at' => $expiresAt,
                    ], $expiresAt);

                    if($retries > 0) $data['attempts'] = $attempts;

                    return $data;

                }else{

                    Log::error('Airtime Billing Token Generation API Error (Stage 1)', [
                        'endpoint' => $endpoint,
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ]);

                    $failedAttempts[] = [
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ];

                }

            } catch (\GuzzleHttp\Exception\BadResponseException $e) {

                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                Log::error('Airtime Billing Token Generation API Error (Stage 2)', [
                    'endpoint' => $endpoint,
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'message' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'error_description' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson,
                ];

            } catch (Throwable $e) {

                Log::error('Airtime Billing Token Generation API Fatal Error (Stage 3)', [
                    'attempt' => $attempts,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'error_code' => $e->getCode() ?: null,
                    'error_description' => $e->getMessage(),
                ];

                break;

            }

            $retries++;
        }

        return [
            'status' => false,
            'body' => [
                'failed_attempts' => $failedAttempts
            ],
        ];
    }

    /**
     * Request the airtime billing product inventory data.
     * Retrieves account details, such as whether the account is active and whether it is Prepaid or Postpaid.
     *
     * @param string $msisdn The MSISDN (mobile number) of the subscriber.
     * @param string $accessToken The access token for authentication.
     *
     * @return array Response containing the status and the body.
     *
     * On Success, the response payload is as follows:
     * [
     *     {
     *         "id": "8037c89b-f204-428e-9336-d3a4bca1b3fe",
     *         "ratingType": "Postpaid",
     *         "status": "Active",
     *         "isBundle": true,
     *         "startDate": "2020-09-17T00:00:00+0000",
     *         "productOffering": {
     *             "id": "Orange_Postpaid",
     *             "name": "MySim"
     *         }
     *     }
     * ]
     *
     * On Failure, the response payload is as follows:
     * {
     *     "code": 4001,
     *     "message": "Missing parameter",
     *     "description": "Parameter publicKey is missing, null or empty"
     * }
     */
    public static function requestAirtimeBillingProductInventory(string $msisdn, string $accessToken): array
    {
        $endpoint = config('app.ORANGE_AIRTIME_BILLING_URL') . "/customer/productInventory/v1/product?publicKey=$msisdn";

        $options = [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'verify' => false, // Disable SSL verification (useful for testing environments)
        ];

        $retries = 0;
        $maxRetries = 2;
        $failedAttempts = [];

        while ($retries <= $maxRetries) {

            $attempts = $retries + 1;

            try {

                // Create a new HTTP Guzzle Client
                $httpClient = new Client();

                // Perform the HTTP request
                $response = $httpClient->request('GET', $endpoint, $options);

                // Parse the response body
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                // Get the response status code
                $statusCode = $response->getStatusCode();

                // Prepare the response data
                $data = [
                    'status' => ($statusCode == 200),
                    'attempts' => $attempts,
                    'body' => $bodyAsArray
                ];

                // Handle successful response
                if ($statusCode === 200) {

                    return $data;

                }else{

                    Log::error('Airtime Billing Product Inventory API Error (Stage 1)', [
                        'msisdn' => $msisdn,
                        'endpoint' => $endpoint,
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ]);

                    $failedAttempts[] = [
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ];

                }

            } catch (\GuzzleHttp\Exception\BadResponseException $e) {

                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                Log::error('Airtime Billing Product Inventory API Error (Stage 2)', [
                    'msisdn' => $msisdn,
                    'endpoint' => $endpoint,
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'message' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'error_description' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ];

            } catch (Throwable $e) {

                Log::error('Airtime Billing Product Inventory API Fatal Error (Stage 3)', [
                    'msisdn' => $msisdn,
                    'attempt' => $attempts,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'error_code' => $e->getCode() ?: null,
                    'error_description' => $e->getMessage(),
                ];

                break;

            }

            $retries++;
        }

        return [
            'status' => false,
            'body' => [
                'failed_attempts' => $failedAttempts
            ],
        ];
    }

    /**
     * Request the airtime billing usage consumption data.
     * Retrieves service consumption details, such as the available airtime balance, SMS, and mobile data left.
     *
     * @param string $msisdn The MSISDN (mobile number) of the subscriber.
     * @param string $accessToken The access token for authentication.
     *
     * @return array Response containing the status and the body.
     *
     * On Success, the response payload is as follows:
     * {
     *     "id": "2b778311-ab1b-4f9b-bdb7-e8f3632a6ca9",
     *     "effectiveDate": "2022-01-21T13:24:33+0000",
     *     "bucket": [
     *         {
     *             "id": "OCS-0",
     *             "name": "Main Balance",
     *             "usageType": "accountBalance",
     *             "bucketBalance": [
     *                 {
     *                     "unit": "BWP",
     *                     "remainingValue": 0,
     *                     "validFor": {
     *                         "startDateTime": "2019-04-04T00:00:00+0000",
     *                         "endDateTime": "2023-01-06T00:00:00+0000"
     *                     }
     *                 }
     *             ]
     *         },
     *         {
     *             "id": "OCS-2",
     *             "name": "On-Net",
     *             "usageType": "accountBalance",
     *             "bucketBalance": [
     *                 {
     *                     "unit": "BWP",
     *                     "remainingValue": 0,
     *                     "validFor": {
     *                         "startDateTime": "2022-01-02T12:54:34+0000",
     *                         "endDateTime": "2022-01-20T17:51:06+0000"
     *                     }
     *                 }
     *             ]
     *         },
     *         {
     *             "id": "OCS-5",
     *             "name": "National SMS",
     *             "usageType": "sms",
     *             "bucketBalance": [
     *                 {
     *                     "unit": "SMS",
     *                     "remainingValue": 11,
     *                     "validFor": {
     *                         "startDateTime": "2019-04-07T00:00:00+0000",
     *                         "endDateTime": "2032-01-04T00:00:00+0000"
     *                     }
     *                 }
     *             ]
     *         }
     *     ]
     * }
     *
     * On Failure, the response payload is as follows:
     * {
     *     "code": 4001,
     *     "message": "Missing parameter",
     *     "description": "Parameter publicKey is missing, null or empty"
     * }
     */
    public static function requestAirtimeBillingUsageConsumption(string $msisdn, string $accessToken): array
    {
        $endpoint = config('app.ORANGE_AIRTIME_BILLING_URL') . "/customer/usageConsumption/v1/usageConsumptionReport?publicKey=$msisdn";

        $options = [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'verify' => false, // Disable SSL verification (useful for testing environments)
        ];

        $retries = 0;
        $maxRetries = 2;
        $failedAttempts = [];

        while ($retries <= $maxRetries) {
            $attempts = $retries + 1;

            try {
                // Create a new HTTP Guzzle Client
                $httpClient = new Client();

                // Perform the HTTP request
                $response = $httpClient->request('GET', $endpoint, $options);

                // Parse the response body
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                // Get the response status code
                $statusCode = $response->getStatusCode();

                // Prepare the response data
                $data = [
                    'status' => ($statusCode == 200),
                    'attempts' => $attempts,
                    'body' => $bodyAsArray
                ];

                // Handle successful response
                if ($statusCode === 200) {

                    return $data;

                }else{

                    Log::error('Airtime Billing Usage Consumption API Error (Stage 1)', [
                        'msisdn' => $msisdn,
                        'endpoint' => $endpoint,
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ]);

                    $failedAttempts[] = [
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ];

                }

            } catch (\GuzzleHttp\Exception\BadResponseException $e) {

                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                Log::error('Airtime Billing Usage Consumption API Error (Stage 2)', [
                    'msisdn' => $msisdn,
                    'endpoint' => $endpoint,
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'message' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'error_description' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ];

            } catch (Throwable $e) {

                Log::error('Airtime Billing Usage Consumption API Fatal Error (Stage 3)', [
                    'msisdn' => $msisdn,
                    'attempt' => $attempts,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'error_code' => $e->getCode() ?: null,
                    'error_description' => $e->getMessage(),
                ];

                break;

            }

            $retries++;
        }

        return [
            'status' => false,
            'body' => [
                'failed_attempts' => $failedAttempts
            ],
        ];
    }


    /**
     * Request to bill the subscriber on the given amount.
     *
     * @param BillingTransaction $billingTransaction The billing transaction Model.
     * @param string $msisdn The MSISDN (mobile number) of the subscriber.
     * @param string $amount The amount to be billed (e.g., 10.00).
     * @param string $onBehalfOf Entity name to allow aggregator or acquiring partners to specify the actual payee.
     * @param string $productId Combines with the onBehalfOf to uniquely identify the product being purchased.
     * @param string $purchaseCategoryCode A category defining the content type validated by the AAS integration team.
     * @param string $description A description of the transaction.
     * @param string $accessToken The access token for authentication.
     *
     * @return array Response containing the status and the body.
     *
     * On Success, the response payload is as follows:
     * {
     *     "amountTransaction": {
     *         "endUserId": "tel:+26712345678",
     *         "paymentAmount": {
     *             "chargingInformation": {
     *                 "amount": 10.00,
     *                 "currency": "BWP",
     *                 "description": [
     *                     "Monthly subscription fee"
     *                 ]
     *             },
     *             "chargingMetaData": {
     *                 "productId": "Daily_subscription",
     *                 "serviceId": "Streaming_service",
     *                 "purchaseCategoryCode": "Subscription_fee"
     *             }
     *         },
     *         "clientCorrelator": "unique-technical-id",
     *         "referenceCode": "Service_provider_payment_reference",
     *         "transactionOperationStatus": "Charged",
     *         "serverReferenceCode": "12345abcde",
     *         "resourceURL": "/payment/v1/tel:+26712345678/transactions/amount/12345abcde"
     *     }
     * }
     *
     * On Failure, the response payload is as follows:
     * 403 Status (Policy error example):
     * {
     *     "requestError": {
     *         "policyException": {
     *             "messageId": "POL2206",
     *             "text": "User forbidden."
     *         }
     *     }
     * }
     *
     * 409 Status (Service error example):
     * {
     *     "requestError": {
     *         "serviceException": {
     *             "messageId": "SVC0005",
     *             "text": "Duplicate correlatorId cc1d2d34",
     *             "variables": ["cc1d2d34"]
     *         }
     *     }
     * }
     */
    public static function requestAirtimeBillingDeductFee(
        string $msisdn,
        string $amount,
        string $onBehalfOf,
        string $productId,
        string $purchaseCategoryCode,
        string $description,
        string $accessToken,
        string $clientCorrelator,
        string $referenceCode
    ): array
    {
        $endpoint = config('app.ORANGE_AIRTIME_BILLING_URL').'/payment/v1/tel%3A%2B'.$msisdn.'/transactions/amount';

        $chargingMetaData = array_filter([
            'productId' => $productId,
            'onBehalfOf' => $onBehalfOf,
            'purchaseCategoryCode' => $purchaseCategoryCode,
        ]);

        $options = [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'amountTransaction' => [
                    'endUserId' => "tel:+$msisdn",
                    'paymentAmount' => [
                        'chargingInformation' => [
                            'amount' => (float) $amount,
                            'currency' => config('app.CURRENCY', 'BWP'),
                            'description' => [$description],
                        ],
                        'chargingMetaData' => $chargingMetaData,
                    ],
                    'clientCorrelator' => $clientCorrelator,
                    'referenceCode' => $referenceCode,
                    'transactionOperationStatus' => 'Charged',
                ],
            ],
            'verify' => false, // Disable SSL verification (useful for testing environments)
        ];

        $retries = 0;
        $maxRetries = 2;
        $failedAttempts = [];

        while ($retries <= $maxRetries) {

            $attempts = $retries + 1;

            try {

                // Create a new HTTP Guzzle Client
                $httpClient = new Client();

                // Perform the HTTP request
                $response = $httpClient->request('POST', $endpoint, $options);

                // Parse the response body
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                // Get the response status code
                $statusCode = $response->getStatusCode();

                // Prepare the response data
                $data = [
                    'status' => ($statusCode == 201),
                    'attempts' => $attempts,
                    'body' => $bodyAsArray
                ];

                // Handle successful response
                if ($statusCode === 201) {

                    return $data;

                }else{

                    Log::error('Airtime Billing Deduct Fee API Error (Stage 1)', [
                        'msisdn' => $msisdn,
                        'endpoint' => $endpoint,
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ]);

                    $failedAttempts[] = [
                        'attempts' => $attempts,
                        'status_code' => $statusCode,
                        'response' => $bodyAsArray ?? $bodyAsJson
                    ];

                }

            } catch (\GuzzleHttp\Exception\BadResponseException $e) {

                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                Log::error('Airtime Billing Deduct Fee API Error (Stage 2)', [
                    'msisdn' => $msisdn,
                    'endpoint' => $endpoint,
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'message' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'error_description' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ];

            } catch (Throwable $e) {

                Log::error('Airtime Billing Deduct Fee Fatal API Error (Stage 3)', [
                    'msisdn' => $msisdn,
                    'attempt' => $attempts,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'error_code' => $e->getCode() ?: null,
                    'error_description' => $e->getMessage(),
                ];

                break;

            }

            $retries++;
        }

        return [
            'status' => false,
            'body' => [
                'failed_attempts' => $failedAttempts
            ],
        ];
    }
}
