<?php

namespace App\Services\Sms;

use Throwable;
use GuzzleHttp\Client;
use App\Enums\CacheName;
use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use Illuminate\Support\Str;
use App\Helpers\CacheManager;
use App\Enums\SmsFailureType;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     *  Send the Orange SMS
     *
     *  @param string $content - The message content to send
     *  @param string $recipientMobileNumber - The number of the recipient to receive the message e.g 26772000001
     *  @param Store|null $store - The store sending the message
     *  @return SmsMessage
     */
    public static function sendOrangeSms($content, $recipientMobileNumber, $store = null): SmsMessage
    {
        $failureType = null;
        $failureReason = null;
        $clientCorrelator = Str::uuid();
        $senderName = config('app.SMS_SENDER_NAME');
        $clientCredentials = config('app.SMS_CREDENTIALS');
        $senderMobileNumber = config('app.SMS_SENDER_MOBILE_NUMBER');

        if($store && !empty($store->sms_sender_name)) {

            $senderName = $store->sms_sender_name;
            //  $senderMobileNumber = $store->mobile_number->formatE164();

        }

        $metadata = [
            'client_correlator' => $clientCorrelator
        ];

        $smsMessage = SmsMessage::create([
            'content' => $content,
            'metadata' => $metadata,
            'store_id' => $store?->id,
            'sender_name' => $senderName,
            'status' => SmsStatus::PENDING->value,
            'sender_mobile_number' => $senderMobileNumber,
            'recipient_mobile_number' => $recipientMobileNumber
        ]);

        try {

            $senderMobileNumber = ltrim($senderMobileNumber, '+');
            $recipientMobileNumber = ltrim($recipientMobileNumber, '+');

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
             *          "access_token" => "eyJ4NXQiOiJOalUzWWpJeE5qRTVObU0wWVRkbE1XRmhNVFEyWWpkaU1tUXdNemMwTmpreFkyTmlaRE0xTlRrMk9EaGxaVFkwT0RFNU9EZzBNREkwWlRreU9HRmxOZyIsImtpZCI6Ik5qVTNZakl4TmpFNU5tTTBZVGRsTVdGaE1UUTJZamRpTW1Rd016YzBOamt4WTJOaVpETTFOVGsyT0RobFpUWTBPREU1T0RnME1ESTBaVGt5T0dGbE5nX1JTMjU2IiwiYWxnIjoiUlMyNTYifQ.eyJzdWIiOiJPQldfSU5URUdSQVRJT05AY2FyYm9uLnN1cGVyIiwiYXV0IjoiQVBQTElDQVRJT04iLCJhdWQiOiJST2VHNFUxMXBhOUI4ZWludGVPUk5Mcjh1RWdhIiwibmJmIjoxNzEwNzA0OTk0LCJhenAiOiJST2VHNFUxMXBhOUI4ZWludGVPUk5Mcjh1RWdhIiwic2NvcGUiOiJhbV9hcHBsaWNhdGlvbl9zY29wZSBkZWZhdWx0IiwiaXNzIjoiaHR0cHM6XC9cL2Fhcy1idy1ndy5jb20uaW50cmFvcmFuZ2U6NDQzXC9vYXV0aDJcL3Rva2VuIiwiZXhwIjoxNzEwNzA4NTk0LCJpYXQiOjE3MTA3MDQ5OTQsImp0aSI6IjkzYzQ0OGRjLWQ5MzQtNDBhYi1iMDFjLWJhNWUxNDFjN2FjNyJ9.FRgZ1g5hLvj1hFra3DO4W_dnkdLHBy08Whc_Rh0vmouG27MmNoPWSwQrhkSr9n3ekyy7kyLXasi04-egx7xoQq_Dbxuml-PsOevPk0Jrt6INeZiNQoXkKcaZisHWKLFeuue_2m-8urXxEVYCs2GbKH0bEXx9FmrOgOjCbFv0z1hmIuWRaqdSdXFah8Ud4_u-McXI7y9RTL5pd-SUKJ9V9Ml0-7-P7XTGaJ-NJKEbbcX0X-AoMlxWkM-CAJ1aDlxLJGfdhteDr0WsRDSRqoqbaBcRKrnou4vXC7l13iRYpHtfn0cFTff2ZFx1DUiFA25bEpo9HrR21dt6Vxq4GH18wQ",
             *          "scope" => "am_application_scope default",
             *          "token_type" => "Bearer",
             *          "expires_in" => 3600
             *      ]
             *  ]
             */
            $response = self::requestSmsAccessToken($clientCredentials);

            if($status = $response['status']) {

                $accessToken = $response['body']['access_token'];

                /**
                 *  -----------------------
                 *  Request to send the SMS
                 *  -----------------------
                 *
                 *  On Success, the response payload is as follows:
                 *
                 *  [
                 *      "outboundSMSMessageRequest" => [
                 *          "address" => [
                 *              "tel: "+26772882239"
                 *          ],
                 *          "senderAddress" => "tel:+26771234567",
                 *          "senderName" => "Testing",
                 *          "outboundSMSTextMessage" => [
                 *              "message" => "This is a test SMS"
                 *          ],
                 *          "clientCorrelator" => "cf9d467d-2131-4280-b996-dddc5eb70eb2",
                 *          "resourceURL" => "/smsmessaging/v1/outbound/tel:+26771234567/requests/req65f74d24d046b122fc077f8c",
                 *          "link" => [
                 *              [
                 *                  "rel" => "Date",
                 *                  "href": "2024-03-17T20:05:56.566Z"
                 *              ]
                 *          ],
                 *          "deliveryInfoList" => [
                 *              "resourceURL" => "/smsmessaging/v1/outbound/tel:+26771234567/requests/req65f74d24d046b122fc077f8c/deliveryInfos",
                 *              "link" => [],
                 *              "deliveryInfo" => [
                 *                  [
                 *                      "address" => "tel:+26772882239",
                 *                      "deliveryStatus" => "MessageWaiting",
                 *                      "link" => []
                 *                  ]
                 *              ]
                 *          ]
                 *      ]
                 *  ]
                 *
                 *  On Fail, the response payload is as follows:
                 *
                 *  403 status:
                 *
                 *  {
                 *      "requestError": {
                 *          "serviceException": {
                 *              "messageId": "SVC0280",
                 *              "text": "Message too long. Maximum length is %1 characters",
                 *              "variables": [
                 *                  "1024"
                 *              ]
                 *          }
                 *      }
                 *  }
                 *
                 *  409 status:
                 *
                 *  {
                 *      "requestError": {
                 *          "serviceException": {
                 *              "messageId": "SVC0005",
                 *              "text": "duplicate correlatorId 1"
                 *          }
                 *      }
                 *  }
                 */
                $response = self::requestSendSms($senderName, $senderMobileNumber, $recipientMobileNumber, $content, $clientCorrelator, $accessToken);

                if($status = $response['status']) {

                    //  The SMS sending is successful at this point

                }else{

                    $failureType = SmsFailureType::MessageSendingFailed->value;
                    $failureReason = 'Failed to send sms message';
                    $failedAttempts = json_encode($response['body']['failed_attempts']);

                }

            }else{
                $failureType = SmsFailureType::TokenGenerationFailed->value;
                $failureReason = 'Failed to generate access token to send sms';
                $failedAttempts = json_encode($response['body']['failed_attempts']);
            }

            if($status) {

                $metadata = array_merge($metadata, [
                    'delivery_status_endpoint' => $response['body']['outboundSMSMessageRequest']['deliveryInfoList']['resourceURL'],
                    'delivery_status' => $response['body']['outboundSMSMessageRequest']['deliveryInfoList']['deliveryInfo'][0]['deliveryStatus']
                ]);

            }

            if(!empty($failedAttempts)) {

                $metadata = array_merge($metadata, [
                    'failed_attempts' => $failedAttempts
                ]);

            }

            //  Update the subscriber message record
            $smsMessage->update([
                'metadata' => $metadata,
                'failure_type' => $failureType,
                'failure_reason' => $failureReason,
                'status' => $status ? SmsStatus::SENT->value : SmsStatus::FAILED_SENDING->value
            ]);

            return $smsMessage;

        } catch (\Throwable $e) {

            $failureType = SmsFailureType::InternalFailure->value;
            $failureReason = 'Could not send sms due to fatal error';

            $metadata = [
                'failed_attempts' => [
                    [
                        'attempts' => 1,
                        'error_code' => $e->getCode() ?: null,
                        'error_description' => $e->getMessage()
                    ]
                ]
            ];

            $smsMessage->update([
                'metadata' => $metadata,
                'failure_type' => $failureType,
                'failure_reason' => $failureReason,
                'status' => SmsStatus::FAILED_SENDING->value
            ]);

            return $smsMessage;

        }
    }

    /**
     * Request the Orange SMS Access Token
     *
     * @param string $clientCredentials - The client credentials provided by the Mobile Network Operator
     *
     * @return array - Contains the response status and body.
     *
     * On Success, the response payload is as follows:
     *
     * {
     *      "access_token": "eyJ4NXQiOiJOalUzWWpJeE5qRTVObU0wWVRkbE1XRmhNVFEyWWpkaU1tUXdNemMwTmpreFkyTmlaRE0xTlRrMk9EaGxaVFkwT0RFNU9EZzBNREkwWlRreU9HRmxOZyIsImtpZCI6Ik5qVTNZakl4TmpFNU5tTTBZVGRsTVdGaE1UUTJZamRpTW1Rd016YzBOamt4WTJOaVpETTFOVGsyT0RobFpUWTBPREU1T0RnME1ESTBaVGt5T0dGbE5nX1JTMjU2IiwiYWxnIjoiUlMyNTYifQ.eyJzdWIiOiJPQldfSU5URUdSQVRJT05AY2FyYm9uLnN1cGVyIiwiYXV0IjoiQVBQTElDQVRJT04iLCJhdWQiOiJST2VHNFUxMXBhOUI4ZWludGVPUk5Mcjh1RWdhIiwibmJmIjoxNjkwNDY1MzY5LCJhenAiOiJST2VHNFUxMXBhOUI4ZWludGVPUk5Mcjh1RWdhIiwic2NvcGUiOiJhbV9hcHBsaWNhdGlvbl9zY29wZSBkZWZhdWx0IiwiaXNzIjoiaHR0cHM6XC9cL2Fhcy1idy1ndy5jb20uaW50cmFvcmFuZ2U6NDQzXC9vYXV0aDJcL3Rva2VuIiwiZXhwIjoxNjkwNDY4OTY5LCJpYXQiOjE2OTA0NjUzNjksImp0aSI6Ijg1ZDk2ZGJmLTNjYTAtNGEyMS05NzAwLWFlNGNlMTYzMDRjNiJ9.fFSjVkPWfxdLpYAmF86tGZInSI65Wtwz1sDYuQ_9QxHilqU2hUi5bJHB6Iw3cQepayJeY4899RLQ10H27YV9-P1zcVO_DJsiKA1itMZqcdwI5zMjmtOyJ7hbbACWLNXui4wYkuhWP2PhV3YgenB3wcNHIHtt-6dz4p4OIEkL22dmr_g5d6T-eBR3JLqGtP2ijyAfxxuS0brF6clEF04m2XzzE_RH4YoFzLvQPA56cuD45uMsNodhsK7D4f4xLOKyDiLjzXxwrnPuEgzsLp8LrZYmFgNRasLvdbazJFeOmZY9DrPk0vtYD93Bjb3nEmH5Mdgv4PsxoN_medTJdJ6Efw",
     *      "scope": "am_application_scope default",
     *      "token_type": "Bearer",
     *      "expires_in": 3600
     * }
     */
    public static function requestSmsAccessToken($clientCredentials): array
    {
        $cacheManager = new CacheManager(CacheName::SMS_ACCESS_TOKEN_RESPONSE);

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
                $endpoint = config('app.SMS_URL').'/token';

                // Set the request options
                $options = [
                    'headers' => [
                        'Authorization' => 'Basic '.$clientCredentials,
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'form_params' => [
                        'grant_type' => 'client_credentials'
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

                Log::warning('SMS Token Generation API Error', [
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

                Log::error('SMS Token Generation API Fatal Error', [
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
            ]
        ];
    }

    /**
     *  Request to send the Orange SMS
     *
     *  @param string $senderName - The sender name
     *  @param string $senderMobileNumber - The sender mobile number
     *  @param string $recipientMobileNumber - The recipient mobile number
     *  @param string $message - The message content to be sent
     *  @param string $clientCorrelator - Unique ID to identify this SMS message
     *  @param string $accessToken - The access token
     *
     *  @return array
     */
    public static function requestSendSms($senderName, $senderMobileNumber, $recipientMobileNumber, $message, $clientCorrelator, $accessToken): array
    {
        $endpoint = config('app.SMS_URL').'/smsmessaging/v1/outbound/tel%3A%2B'.$senderMobileNumber.'/requests';

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
                'accept' => 'application/json'
            ],
            'json' => [
                'outboundSMSMessageRequest' => [
                    'address' => ['tel:+'.$recipientMobileNumber],      //  Recepient number to send the SMS message
                    'senderAddress' => 'tel:+'.$senderMobileNumber,     //  Sender number that will be displayed if senderName is not included
                    'senderName' => $senderName,                        //  Sender name e.g "Company XYZ" - Maximum 11 characters
                    'outboundSMSTextMessage' => [
                        'message' => $message                           //  Message to be sent
                    ],
                    'clientCorrelator' => $clientCorrelator             // A unique id to identify this SMS message
                ],
            ],
            'verify' => false,  // Disable SSL verification (useful for testing environments)
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

                // Handle successful response
                if ($statusCode === 201) {

                    return [
                        'status' => ($statusCode == 201),
                        'attempts' => $attempts,
                        'body' => $bodyAsArray
                    ];

                }else{

                    Log::warning('SMS Sending API Error', [
                        'message' => $message,
                        'senderName' => $senderName,
                        'senderMobileNumber' => $senderMobileNumber,
                        'recipientMobileNumber' => $recipientMobileNumber,
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

                Log::warning('SMS Sending API Error', [
                    'message' => $message,
                    'senderName' => $senderName,
                    'senderMobileNumber' => $senderMobileNumber,
                    'recipientMobileNumber' => $recipientMobileNumber,
                    'endpoint' => $endpoint,
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'response' => $bodyAsArray ?? $bodyAsJson
                ]);

                $failedAttempts[] = [
                    'attempts' => $attempts,
                    'status_code' => $statusCode,
                    'error_description' => $e->getMessage(),
                    'response' => $bodyAsArray ?? $bodyAsJson
                ];

            } catch (Throwable $e) {

                Log::error('SMS Sending Fatal API Error', [
                    'message' => $message,
                    'senderName' => $senderName,
                    'senderMobileNumber' => $senderMobileNumber,
                    'recipientMobileNumber' => $recipientMobileNumber,
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
     *  Update the SMS Message delivery status
     *
     *  @param SmsMessage $smsMessage - The SmsMessage Model
     *  @return SmsMessage
     */
    public static function updateSmsDeliveryStatus($smsMessage): SmsMessage
    {
        try {

            $failureType = null;
            $failureReason = null;
            $metadata = $smsMessage->metadata;
            $clientCredentials = config('app.SMS_CREDENTIALS');

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
             *          "access_token" => "eyJ4NXQiOiJOalUzWWpJeE5qRTVObU0wWVRkbE1XRmhNVFEyWWpkaU1tUXdNemMwTmpreFkyTmlaRE0xTlRrMk9EaGxaVFkwT0RFNU9EZzBNREkwWlRreU9HRmxOZyIsImtpZCI6Ik5qVTNZakl4TmpFNU5tTTBZVGRsTVdGaE1UUTJZamRpTW1Rd016YzBOamt4WTJOaVpETTFOVGsyT0RobFpUWTBPREU1T0RnME1ESTBaVGt5T0dGbE5nX1JTMjU2IiwiYWxnIjoiUlMyNTYifQ.eyJzdWIiOiJPQldfSU5URUdSQVRJT05AY2FyYm9uLnN1cGVyIiwiYXV0IjoiQVBQTElDQVRJT04iLCJhdWQiOiJST2VHNFUxMXBhOUI4ZWludGVPUk5Mcjh1RWdhIiwibmJmIjoxNzEwNzA0OTk0LCJhenAiOiJST2VHNFUxMXBhOUI4ZWludGVPUk5Mcjh1RWdhIiwic2NvcGUiOiJhbV9hcHBsaWNhdGlvbl9zY29wZSBkZWZhdWx0IiwiaXNzIjoiaHR0cHM6XC9cL2Fhcy1idy1ndy5jb20uaW50cmFvcmFuZ2U6NDQzXC9vYXV0aDJcL3Rva2VuIiwiZXhwIjoxNzEwNzA4NTk0LCJpYXQiOjE3MTA3MDQ5OTQsImp0aSI6IjkzYzQ0OGRjLWQ5MzQtNDBhYi1iMDFjLWJhNWUxNDFjN2FjNyJ9.FRgZ1g5hLvj1hFra3DO4W_dnkdLHBy08Whc_Rh0vmouG27MmNoPWSwQrhkSr9n3ekyy7kyLXasi04-egx7xoQq_Dbxuml-PsOevPk0Jrt6INeZiNQoXkKcaZisHWKLFeuue_2m-8urXxEVYCs2GbKH0bEXx9FmrOgOjCbFv0z1hmIuWRaqdSdXFah8Ud4_u-McXI7y9RTL5pd-SUKJ9V9Ml0-7-P7XTGaJ-NJKEbbcX0X-AoMlxWkM-CAJ1aDlxLJGfdhteDr0WsRDSRqoqbaBcRKrnou4vXC7l13iRYpHtfn0cFTff2ZFx1DUiFA25bEpo9HrR21dt6Vxq4GH18wQ",
             *          "scope" => "am_application_scope default",
             *          "token_type" => "Bearer",
             *          "expires_in" => 3600
             *      ]
             *  ]
             */
            $response = self::requestSmsAccessToken($clientCredentials);

            if($status = $response['status']) {

                $accessToken = $response['body']['access_token'];

                /**
                 *  -----------------------------------------
                 *  Request to verify the SMS delivery status
                 *  -----------------------------------------
                 *
                 *  On Success, the response payload is as follows:
                 *
                 *  [
                 *      "status" => true
                 *      "body" => [
                 *          "resourceURL" => "/smsmessaging/v1/outbound/tel:+26771234567/requests/req65f7eb8dd046b122fc0783da/deliveryInfos",
                 *          "link": [],
                 *          "deliveryInfo": [
                 *              [
                 *                  "address" => "tel:+26772882239",
                 *                  "deliveryStatus" => "DeliveredToTerminal",
                 *                  "link": [
                 *                      [
                 *                          "rel" => "OutboundSMSMessageRequest",
                 *                          "href" => "/smsmessaging/v1/outbound/tel:+26771234567/requests/req65f7eb8dd046b122fc0783da"
                 *                      ],
                 *                      [
                 *                          "rel" => "Date",
                 *                          "href" => "2024-03-18T07:21:49.842282981Z"
                 *                      ]
                 *                  ]
                 *              ]
                 *          ]
                 *      ]
                 *  ]
                 */
                $response = self::requestSmsDeliveryStatus($smsMessage, $accessToken);

                if($status = $response['status']) {

                    //  The SMS delivery status check is successful at this point

                }else{

                    $failureType = SmsFailureType::MessageDeliveryVerificationFailed->value;
                    $failureReason = 'Failed to verify sms delivery status';
                    $failedAttempts = json_encode($response['body']['failed_attempts']);

                }

            }else{

                $failureType = SmsFailureType::TokenGenerationFailed->value;
                $failureReason = 'Failed to generate access token to verify sms delivery status';
                $failedAttempts = json_encode($response['body']['failed_attempts']);

            }

            if($status) {

                $metadata = array_merge($metadata, [
                    'delivery_status' => $response['body']['deliveryInfo'][0]['deliveryStatus']
                ]);

            }

            if(!empty($failedAttempts)) {

                $metadata = array_merge($metadata, [
                    'failed_attempts' => $failedAttempts
                ]);

            }

            //  Update the sms message record
            $smsMessage->update([
                'metadata' => $metadata,
                'failure_type' => $failureType,
                'failure_reason' => $failureReason,
                'status' => $status ? SmsStatus::DELIVERED_VERIFIED->value : SmsStatus::FAILED_DELIVERY_VERIFICATION->value
            ]);

            return $smsMessage;

        } catch (\Throwable $e) {

            $failureType = SmsFailureType::InternalFailure->value;
            $failureReason = 'Could not verify sms delivery due to fatal error';

            $metadata = array_merge($smsMessage->metadata, [
                'failed_attempts' => [
                    [
                        'attempts' => 1,
                        'error_code' => $e->getCode() ?: null,
                        'error_description' => $e->getMessage()
                    ]
                ]
            ]);

            $smsMessage->update([
                'metadata' => $metadata,
                'failure_type' => $failureType,
                'failure_reason' => $failureReason,
                'status' => SmsStatus::FAILED_DELIVERY_VERIFICATION->value
            ]);

            return $smsMessage;

        }
    }

    /**
     *  Check the Orange SMS delivery status
     *
     *  @param smsMessage $smsMessage - The smsMessage Model
     *  @param string $accessToken - The access token
     *
     *  @return array
     */
    public static function requestSmsDeliveryStatus($smsMessage, $accessToken): array
    {
        $retries = 0;
        $maxRetries = 2;
        $failedAttempts = [];

        while ($retries <= $maxRetries) {

            $attempts = $retries + 1;

            try {

                // Set the request endpoint
                $endpoint = config('app.SMS_URL'). str_replace('tel:+','tel%3A%2B', $smsMessage->delivery_status_endpoint);

                // Set the request options
                $options = [
                    'headers' => [
                        'Authorization' => 'Bearer '.$accessToken,
                        'Content-type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'verify' => false,  // Disable SSL certificate verification
                ];

                // Create a new HTTP Guzzle Client
                $httpClient = new Client();

                // Perform the HTTP request
                $response = $httpClient->request('GET', $endpoint, $options);

                // Parse the response body
                $bodyAsJson = $response->getBody()->getContents();
                $bodyAsArray = json_decode($bodyAsJson, true);

                // Get the response status code
                $statusCode = $response->getStatusCode();

                // Handle successful response
                if ($statusCode === 200) {

                    return [
                        'status' => ($statusCode == 200),
                        'attempts' => $attempts,
                        'body' => $bodyAsArray
                    ];

                }else{

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

                Log::warning('SMS Delivery Status API Error', [
                    'smsMessage' => $smsMessage,
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

                Log::warning('SMS Delivery Status API Fatal Error', [
                    'smsMessage' => $smsMessage,
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
            ]
        ];
    }
}
