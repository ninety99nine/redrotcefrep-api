<?php

namespace Database\Seeders;

use App\Models\PricingPlan;
use App\Enums\PlatformType;
use App\Enums\PricingPlanType;
use Illuminate\Database\Seeder;
use App\Enums\PricingPlanBillingType;
use Database\Seeders\Traits\SeederHelper;

class PricingPlanSeeder extends Seeder
{
    use SeederHelper;

    /**
     *  Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //  Foreach subscription plan
        foreach($this->getPricingPlans() as $subscriptionPlan) {

            //  Create subscription plan
            PricingPlan::create($subscriptionPlan);

        }
    }

    /**
     *  Return the subscription plans
     *
     *  @return array
     */
    public function getPricingPlans() {
        $pricingPlans = [
            ...$this->getUssdPricingPlans(),
            ...$this->getWebAndMobilePricingPlans()
        ];

        foreach($pricingPlans as $key => $pricingPlan) {
            $pricingPlans[$key]['active'] = 1;
            $pricingPlans[$key]['created_at'] = now();
            $pricingPlans[$key]['updated_at'] = now();
            $pricingPlans[$key]['position'] = $key + 1;
        }

        return $pricingPlans;
    }

    public function getUssdPricingPlans(): array
    {
        return [
            //  Store Plans
            [
                'price' => 1.00,
                'currency' => 'BWP',
                'name' => 'Daily @ P1',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 3,
                'type' => PricingPlanType::STORE_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::RECURRING,
                'auto_billing_disabled_sms_message' => 'You have been successfully unsubscribed from {{ store.name }} {{ pricingPlan.name }}. Dial *250# to subscribe.',
                'description' => '1 day subscription for store access',
                'supports_ussd' => true,
                'metadata' => [
                    'store_subscription' => [
                        'duration' => 1,
                        'frequency' => 'day'
                    ],
                    'sms_credits' => 5
                ],
                'features' => null
            ],
            [
                'price' => 7.00,
                'currency' => 'BWP',
                'name' => 'Weekly @ P7',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 3,
                'type' => PricingPlanType::STORE_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::RECURRING,
                'auto_billing_disabled_sms_message' => 'You have been successfully unsubscribed from {{ store.name }} {{ pricingPlan.name }}. Dial *250# to subscribe.',
                'description' => '1 week subscription for store access',
                'supports_ussd' => true,
                'metadata' => [
                    'store_subscription' => [
                        'duration' => 7,
                        'frequency' => 'day'
                    ],
                    'sms_credits' => 15
                ],
                'features' => null
            ],
            [
                'price' => 30.00,
                'currency' => 'BWP',
                'name' => 'Monthly @ P30',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 3,
                'type' => PricingPlanType::STORE_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::RECURRING,
                'auto_billing_disabled_sms_message' => 'You have been successfully unsubscribed from {{ store.name }} ({{ pricingPlan.name }}). Dial *250# to subscribe.',
                'description' => '1 month subscription for store access',
                'supports_ussd' => true,
                'metadata' => [
                    'store_subscription' => [
                        'duration' => 1,
                        'frequency' => 'month'
                    ],
                    'sms_credits' => 30
                ],
                'features' => null
            ],

            //  AI Assistant Plans
            [
                'price' => 1.00,
                'currency' => 'BWP',
                'name' => 'Daily @ P1',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 3,
                'type' => PricingPlanType::AI_ASSISTANT_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::RECURRING,
                'auto_billing_disabled_sms_message' => 'You have been successfully unsubscribed from AI Assist ({{ pricingPlan.name }}). Dial *250# to subscribe.',
                'description' => '1 day subscription for AI Assistant',
                'supports_ussd' => true,
                'metadata' => [
                    'ai_assistant_subscription' => [
                        'duration' => 1,
                        'frequency' => 'day',
                        'credits' => 7500
                    ]
                ],
                'features' => null
            ],

            //  AI Assistant (Top-Up) Plans
            [
                'price' => 2.00,
                'currency' => 'BWP',
                'name' => 'Top up - P2',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::AI_ASSISTANT_TOP_UP_CREDITS,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => 'Top up credits',
                'supports_ussd' => true,
                'metadata' => [
                    'ai_assistant_top_up_credits' => 7500
                ],
                'features' => null
            ],

            //  SMS Credit Plans
            [
                'price' => 5.00,
                'currency' => 'BWP',
                'name' => '10 sms alerts - P5',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::SMS_CREDITS,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '10 sms alerts',
                'supports_ussd' => true,
                'metadata' => [
                    'sms_credits' => 10
                ],
                'features' => null
            ],

            //  Email Credit Plans
            [
                'price' => 5.00,
                'currency' => 'BWP',
                'name' => '50 email alerts - P5',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::EMAIL_CREDITS,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '50 email alerts',
                'supports_ussd' => true,
                'metadata' => [
                    'email_credits' => 50
                ],
                'features' => null
            ],

            //  Whatsapp Credit Plans
            [
                'price' => 5.00,
                'currency' => 'BWP',
                'name' => '5 whatsapp alerts - P5',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::WHATSAPP_CREDITS,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '5 whatsapp alerts',
                'supports_ussd' => true,
                'metadata' => [
                    'whatsapp_credits' => 5
                ],
                'features' => null
            ],

        ];
    }

    public function getWebAndMobilePricingPlans(): array
    {
        return [

            //  Store Plans
            [
                'name' => 'Basic',
                'price' => 5.00,
                'currency' => 'USD',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::STORE_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '1 month basic subscription for store access',
                'supports_web' => true,
                'supports_mobile' => true,
                'metadata' => [
                    'store_subscription' => [
                        'duration' => 1,
                        'frequency' => 'month',
                    ],
                    'sms_credits' => 60
                ],
                'features' => [
                    'Unlimited WhatsApp orders',
                    'No commissions',
                    'Manual payments'
                ]
            ],
            [
                'name' => 'Basic',
                'price' => 60.00,
                'currency' => 'USD',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::STORE_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '1 year basic subscription for store access',
                'supports_web' => true,
                'supports_mobile' => true,
                'metadata' => [
                    'store_subscription' => [
                        'duration' => 1,
                        'frequency' => 'year',
                    ],
                    'sms_credits' => 720
                ],
                'features' => [
                    'Unlimited WhatsApp orders',
                    'No commissions',
                    'Manual payments'
                ]
            ],
            [
                'name' => 'Premium',
                'price' => 15.00,
                'currency' => 'USD',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::STORE_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '1 month premium subscription for store access',
                'supports_web' => true,
                'supports_mobile' => true,
                'metadata' => [
                    'store_subscription' => [
                        'duration' => 1,
                        'frequency' => 'month'
                    ],
                    'sms_credits' => 60
                ],
                'features' => [
                    'Everything in Basic, plus:',
                    'Card payments (Stripe, PayPal)',
                    'Loyalty and store credits',
                    'Payment proof upload',
                    'Workflow automation',
                    'Delivery distance',
                    'CSV export/import',
                    'Analytics',
                ]
            ],
            [
                'name' => 'Premium',
                'price' => 180.00,
                'currency' => 'USD',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::STORE_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '1 year premium subscription for store access',
                'supports_web' => true,
                'supports_mobile' => true,
                'metadata' => [
                    'store_subscription' => [
                        'duration' => 1,
                        'frequency' => 'year'
                    ],
                    'sms_credits' => 720
                ],
                'features' => [
                    'Everything in Basic, plus:',
                    'Card payments (Stripe, PayPal)',
                    'Loyalty and store credits',
                    'Payment proof upload',
                    'Workflow automation',
                    'Delivery distance',
                    'CSV export/import',
                    'Analytics',
                ]
            ],

            //  AI Assistant Plans
            [
                'name' => 'Basic',
                'price' => 5.00,
                'currency' => 'USD',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::AI_ASSISTANT_SUBSCRIPTION,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '1 month basic subscription for AI Assistant',
                'supports_web' => true,
                'supports_mobile' => true,
                'metadata' => [
                    'ai_assistant_subscription' => [
                        'duration' => 1,
                        'frequency' => 'month',
                        'credits' => 225000
                    ]
                ],
                'features' => null
            ],

            //  SMS Credit Plans
            [
                'price' => 5.00,
                'currency' => 'USD',
                'name' => '100 sms alerts',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::SMS_CREDITS,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '100 sms alerts',
                'supports_ussd' => true,
                'metadata' => [
                    'sms_credits' => 100
                ],
                'features' => null
            ],

            //  Email Credit Plans
            [
                'price' => 5.00,
                'currency' => 'USD',
                'name' => '500 email alerts',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::EMAIL_CREDITS,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '500 email alerts',
                'supports_ussd' => true,
                'metadata' => [
                    'email_credits' => 500
                ],
                'features' => null
            ],

            //  Whatsapp Credit Plans
            [
                'price' => 5.00,
                'currency' => 'USD',
                'name' => '50 whatsapp alerts',
                'discount_percentage_rate' => 0,
                'max_auto_billing_attempts' => 1,
                'type' => PricingPlanType::WHATSAPP_CREDITS,
                'billing_type' => PricingPlanBillingType::ONE_TIME,
                'description' => '50 whatsapp alerts',
                'supports_ussd' => true,
                'metadata' => [
                    'whatsapp_credits' => 50
                ],
                'features' => null
            ],

        ];
    }
}
