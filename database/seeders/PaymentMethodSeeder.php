<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentMethodCategory;
use Database\Seeders\Traits\SeederHelper;
use Illuminate\Support\Facades\Log;

class PaymentMethodSeeder extends Seeder
{
    use SeederHelper;

    public function run()
    {
        $sortedPaymentMethods = collect($this->getPaymentMethodTemplates())
            ->sortBy(fn($paymentMethod) => $this->getPopularityScore($paymentMethod))
            ->values()
            ->toArray();

        foreach ($sortedPaymentMethods as $key => $paymentMethod) {
            PaymentMethod::create(array_merge(
                $paymentMethod,
                [
                    'type' => $paymentMethod['type']->value,
                    'position' => $key + 1
                ]
            ));
        }
    }

    /**
     * Define payment method popularity ranking in a structured and easy-to-maintain format.
     *
     * @param string $type
     * @return int
     */
    private function getPopularityScore($paymentMethod)
    {
        $rankedPaymentMethods = [

            // 🌍 **Global Payment Gateways (Fully Automated, Instant)**
            PaymentMethodType::STRIPE,         // 🥇 Best for online businesses globally
            PaymentMethodType::PAYPAL,         // 🥈 Most widely used worldwide
            PaymentMethodType::RAZORPAY,       // 🚀 Dominant in India, growing fast
            PaymentMethodType::PAYSTACK,       // 🌍 Leading in Africa
            PaymentMethodType::DPO,            // 🌍 Strong presence in Africa
            PaymentMethodType::XENDIT,         // 📈 Leading in Southeast Asia
            PaymentMethodType::PAYFAST,        // 🇿🇦 Strong adoption in South Africa
            PaymentMethodType::PAYHERE,        // 🇱🇰 Sri Lanka’s main payment gateway
            PaymentMethodType::POCKET,         // 📲 Niche digital wallet
            PaymentMethodType::ORANGE_AIRTIME, // 📡 Mobile top-ups & airtime payments

            // 👥 **Peer-to-Peer (P2P) Payment Apps (User-Controlled, Often Manual Verification)**
            PaymentMethodType::PAYPAL_ME,      // 🌍 PayPal’s P2P version
            PaymentMethodType::CASH_APP,       // 🇺🇸 Widely used in the U.S.
            PaymentMethodType::VENMO,          // 🇺🇸 Social payment app in the U.S.
            PaymentMethodType::ZELLE,          // 🇺🇸 Instant bank payments in the U.S.
            PaymentMethodType::ZIINA,          // 🇦🇪 UAE’s mobile P2P transactions

            // 📱 **Digital Wallets (Semi-Automated, Merchant-Driven)**
            PaymentMethodType::GCASH,          // 🇵🇭 Philippines' largest mobile wallet
            PaymentMethodType::MERCADO_PAGO,   // 🇦🇷🇧🇷 Popular in Latin America
            PaymentMethodType::REVOLUT,        // 🌍 International neobank & digital payments
            PaymentMethodType::WISE,           // 🌍 Best for cross-border transactions
            PaymentMethodType::YOCO,           // 🇿🇦 South Africa’s merchant payments
            PaymentMethodType::IKHOKHA,        // 🇿🇦 Similar to Yoco, South Africa
            PaymentMethodType::PESAPAL,        // 🌍 Used in East Africa
            PaymentMethodType::LYNK,           // 🇯🇲 Jamaica’s primary digital wallet
            PaymentMethodType::WAVE,           // 🌍 Digital wallet in West Africa
            PaymentMethodType::SNAPSCAN,       // 🇿🇦 QR payments in South Africa
            PaymentMethodType::WIGWAG,         // 🇳🇬 Nigeria’s digital wallet
            PaymentMethodType::TIKKIE,         // 🇳🇱 Netherlands’ payment request service
            PaymentMethodType::MBWAY,          // 🇵🇹 Portugal’s leading mobile wallet
            PaymentMethodType::MCBJUICE,       // 🇲🇺 Mauritius’ mobile payments
            PaymentMethodType::Instapay,       // 🌍 Various country implementations

            // 🌎 **Local & Regional Bank Transfers (Semi-Manual, Delayed Settlements)**
            PaymentMethodType::PIX,            // 🇧🇷 Brazil’s leading instant payments
            PaymentMethodType::QRIS,           // 🇮🇩 Indonesia’s QR-based payment
            PaymentMethodType::DUITNOW,        // 🇲🇾 Malaysia’s real-time payments
            PaymentMethodType::PAYNOW,         // 🇸🇬 Singapore’s most used bank transfer
            PaymentMethodType::PROMPTPAY,      // 🇹🇭 Thailand’s national QR payments
            PaymentMethodType::TOUCH_N_GO,     // 🇲🇾 Malaysia’s top digital wallet
            PaymentMethodType::UPI,            // 🇮🇳 India’s dominant real-time payments
            PaymentMethodType::SEPA,           // 🇪🇺 Europe’s standard bank transfers
            PaymentMethodType::OXXO,           // 🇲🇽 Mexico’s cash payment option

            // 📲 **Mobile Money & Telecom-Based Payments (Highly Automated, Instant)**
            PaymentMethodType::MPESA,          // 🇰🇪🇹🇿 Most dominant mobile money in East Africa
            PaymentMethodType::AIRTEL,         // 🇳🇬 Airtel Money in Africa & Asia
            PaymentMethodType::MTN_MOMO,       // 🌍 Africa’s major mobile money service
            PaymentMethodType::ORANGE_MONEY,   // 🌍 Leading in North & West Africa
            PaymentMethodType::CELLMONI,       // 🇳🇬 Nigeria’s mobile money service
            PaymentMethodType::MONCASH,        // 🇭🇹 Haiti’s mobile money system
            PaymentMethodType::TIGOPESA,       // 🇹🇿 Tanzania’s mobile money service
            PaymentMethodType::ECOCASH,        // 🇿🇼 Zimbabwe’s largest mobile money
            PaymentMethodType::INNBUCKS,       // 🇿🇼 Zimbabwe’s digital wallet
            PaymentMethodType::BKASH,          // 🇧🇩 Bangladesh’s top mobile banking
            PaymentMethodType::KASPI,          // 🇰🇿 Kazakhstan’s dominant mobile payments
            PaymentMethodType::ESEWA,          // 🇳🇵 Nepal’s largest mobile wallet

            // 💵 **Cash-Based & Manual Payment Methods (Fully Manual, Requires Human Verification)**
            PaymentMethodType::BANK_TRANSFER,       // 🏦 Manual bank deposits
            PaymentMethodType::CASH_ON_DELIVERY,    // 💰 Cash payments upon receiving goods
            PaymentMethodType::STORE_CREDIT,        // 🏬 Merchant-specific store credit
            PaymentMethodType::OTHER,               // 📝 Other payment setup
        ];

        $score = 1;
        $popularityOrder = [];

        foreach ($rankedPaymentMethods as $rankedPaymentMethod) {
                $popularityOrder[$rankedPaymentMethod->value] = $score++;
        }

        try {
            return $popularityOrder[$paymentMethod['type']->value];
        } catch (\Throwable $th) {
            Log::error($paymentMethod['type']->value);
            Log::error($popularityOrder);
        }
    }


    /**
     * Return payment methods
     *
     * @return array
     */
    public function getPaymentMethodTemplates()
    {
        $selectValidationRules = fn($label) => [
            'required' => [true, "The $label is required"]
        ];

        $emailValidationRules = fn() => [
            'required' => [true, "The email is required"],
            'regex_pattern' => ['^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$', "Enter a valid email address, e.g., user@example.com"]
        ];

        $mobileNumberValidationRules = fn($label) => [
            'required' => [true, "The $label is required"]
        ];

        $merchantCodeValidationRules = fn() => [
            'required' => [true, "The Merchant code is required"],
            'regex_pattern' => ['^[A-Za-z0-9]{5,20}$', "Enter a valid merchant code (5-20 alphanumeric characters)"]
        ];

        $customDialCodeValidationRules = fn() => [
            'regex_pattern' => ['^\*\d{1,3}(\*(\d+|\{[a-zA-Z_]+\}))*#$', "Enter a valid USSD dial code (e.g., *123*1*2#)"]
        ];

        $urlValidationRules = fn($label, $placeholder) => [
            'required' => [true, "The $label is required"],
            'regex_pattern' => [
                '^(https?:\/\/)([a-zA-Z0-9-]{1,63}\.)+[a-zA-Z]{2,6}(:[0-9]{1,5})?(\/[^\s]*)?$',
                "Enter a valid $label, e.g $placeholder"
            ]
        ];

        return [
            [
                'active' => 1,
                'name' => 'Pix',
                'type' => PaymentMethodType::PIX,
                'automated_verification' => false,
                'supported_countries' => [
                    'BR' // Brazil
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'email',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Email', 'value' => 'email'],
                            ['label' => 'CPF', 'value' => 'cpf'],
                            ['label' => 'CNPJ (Brazilian companies)', 'value' => 'cnpj']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'email',
                        'label' => 'Email',
                        'attribute' => 'email',
                        'condition' => ['id_type=email'],
                        'validation_rules' => $emailValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'label' => 'CPF',
                        'attribute' => 'cpf',
                        'condition' => ['id_type=cpf'],
                        'validation_rules' => [
                            'required' => [true, "The CPF is required"],
                            'regex_pattern' => ['^\d{11}$', "CPF must be exactly 11 digits"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'label' => 'CNPJ',
                        'attribute' => 'cnpj',
                        'condition' => ['id_type=cnpj'],
                        'validation_rules' => [
                            'required' => [true, "The CNPJ is required"],
                            'regex_pattern' => ['^\d{14}$', "CNPJ must be exactly 14 digits"]
                        ]
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'UPI',
                'type' => PaymentMethodType::UPI,
                'automated_verification' => false,
                'supported_countries' => [
                    'IN' // India
                ],
                'config_schema' => [
                    [
                        'type' => 'string',
                        'label' => 'UPI ID',
                        'attribute' => 'upi_id',
                        'description' => 'Enter your UPI ID - eg. 1234567890@upi',
                        'validation_rules' => [
                            'required' => [true, "The UPI ID is required"],
                            'regex_pattern' => ['^[\w.-]+@[\w.-]+$',"Enter a valid UPI ID, e.g., 1234567890@upi or user@bank"]
                        ]
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Yoco',
                'type' => PaymentMethodType::YOCO,
                'automated_verification' => false,
                'supported_countries' => [
                    'ZA' // South Africa
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Yoco username',
                        'placeholder' => 'https://pay.yoco.com/username',
                        'description' => 'Enter your Yoco username',
                        'validation_rules' => $urlValidationRules('Yoco username', 'https://pay.yoco.com/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Yoco account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.yoco.com/za/get-started'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'QRIS',
                'type' => PaymentMethodType::QRIS,
                'automated_verification' => false,
                'supported_countries' => [
                    'ID' // Indonesia
                ],
                'config_schema' => [
                    [
                        'type' => 'image',
                        'attribute' => 'photo',
                        'label' => 'QRIS QR code',
                        'description' => 'Upload your QRIS QR code',
                        'validation_rules' => [
                            'required' => [true, "The QR Code is required"],
                            'qr_code' => ["The QRIS QR code is not valid"],
                            'mime_types' => [['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], "Only JPEG, JPG, PNG, and GIF formats are allowed"],
                            'max_size' => [5 * 1024 * 1024, "Image size should not exceed 5MB"]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Wise',
                'type' => PaymentMethodType::WISE,
                'automated_verification' => false,
                'supported_countries' => null, // Global
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Wise tag',
                        'placeholder' => 'https://wise.com/pay/me/username',
                        'description' => 'Enter your Wise tag',
                        'learn_more' => [
                            'label' => 'Learn more',
                            'href' => 'https://wise.com/help/articles/6DtiR7Ugdp7hfoKJHfRfvJ/how-do-i-use-wisetag'
                        ],
                        'validation_rules' => $urlValidationRules('Wise tag', 'https://wise.com/pay/me/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Wise account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://wise.com/invite/dic/julianbrandont'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Lynk',
                'type' => PaymentMethodType::LYNK,
                'automated_verification' => false,
                'supported_countries' => [
                    'JM' // Jamaica
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Lynk payment link',
                        'placeholder' => 'https://deep.lynk.us/AbcD',
                        'description' => 'Enter your Lynk payment link',
                        'validation_rules' => $urlValidationRules('Lynk payment link', 'https://deep.lynk.us/AbcD')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Lynk account?'
                            ],
                            [
                                'text' => 'Download App',
                                'href' => 'https://www.lynk.us'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'GCash',
                'type' => PaymentMethodType::GCASH,
                'automated_verification' => false,
                'supported_countries' => [
                    'PH', // Philippines
                    'MY', // Malaysia
                    'SG', // Singapore
                    'TH', // Thailand
                    'VN', // Vietnam
                    'ID', // Indonesia
                    'HK', // Hong Kong
                    'TW', // Taiwan
                    'JP', // Japan
                    'KR', // South Korea
                    'IN', // India
                    'PK', // Pakistan
                    'BD', // Bangladesh
                    'MM', // Myanmar
                    'LA', // Laos
                    'KH', // Cambodia
                    'BR', // Brazil
                    'MX', // Mexico
                    'US', // United States
                    'CA', // Canada
                    'GB', // United Kingdom
                    'AU', // Australia
                    'NZ', // New Zealand
                    'ZA', // South Africa
                    'KE', // Kenya
                    'NG', // Nigeria
                    'GH', // Ghana
                    'TZ', // Tanzania
                    'UG', // Uganda
                    'ZM', // Zambia
                    'MW', // Malawi
                ],
                'config_schema' => [
                    [
                        'type' => 'string',
                        'attribute' => 'user_id',
                        'label' => 'GCash User ID',
                        'description' => 'Enter your GCash User ID - eg. *****ZHU485.',
                        'learn_more' => [
                            'label' => 'Learn how to find your User ID',
                            'href' => 'https://www.perfectorder.shop'
                        ],
                        'validation_rules' => [
                            'required' => [true, "The GCash User ID is required"],
                            'regex_pattern' => ['^[A-Za-z0-9]{6,20}$',"Enter a valid GCash User ID (6-20 alphanumeric characters)"]
                        ],
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a GCash account?'
                            ],
                            [
                                'text' => 'Download App',
                                'href' => 'https://new.gcash.com'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'eSewa',
                'type' => PaymentMethodType::ESEWA,
                'automated_verification' => false,
                'supported_countries' => [
                    'NP', // Nepal
                    'IN', // India
                    'BD', // Bangladesh
                    'PK', // Pakistan
                    'LK', // Sri Lanka
                ],
                'config_schema' => [
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Venmo',
                'type' => PaymentMethodType::VENMO,
                'automated_verification' => false,
                'supported_countries' => [
                    'US' // United States
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Venmo username',
                        'placeholder' => 'https://venmo.com/username',
                        'description' => 'Enter your Venmo username',
                        'validation_rules' => $urlValidationRules('Venmo username', 'https://venmo.com/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Venmo account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://account.venmo.com/signup'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Zelle',
                'type' => PaymentMethodType::ZELLE,
                'automated_verification' => false,
                'supported_countries' => [
                    'US' // United States
                ],
                'config_schema' => [
                    [
                        'type' => 'email',
                        'label' => 'Email',
                        'attribute' => 'email',
                        'description' => 'Enter your Zelle email address',
                        'validation_rules' => $emailValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Ziina',
                'type' => PaymentMethodType::ZIINA,
                'automated_verification' => false,
                'supported_countries' => [
                    'AE' // United Arab Emirates
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Ziina username',
                        'placeholder' => 'https://pay.ziina.com/username',
                        'description' => 'Enter your Ziina username',
                        'validation_rules' => $urlValidationRules('Ziina username', 'https://pay.ziina.com/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Ziina account?'
                            ],
                            [
                                'text' => 'Download App',
                                'href' => 'https://ziina.com/app-link/DownloadApp'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Kaspi',
                'type' => PaymentMethodType::KASPI,
                'automated_verification' => false,
                'supported_countries' => [
                    'KZ' // Kazakhstan
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Kaspi payment link',
                        'placeholder' => 'https://pay.kaspi.kz/pay/xyzab40cd',
                        'description' => 'Enter your Kaspi payment link',
                        'validation_rules' => $urlValidationRules('Kaspi payment link', 'https://pay.kaspi.kz/pay/xyzab40cd')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Kaspi account?'
                            ],
                            [
                                'text' => 'Download App',
                                'href' => 'https://kaspi.kz'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'M-Pesa',
                'type' => PaymentMethodType::MPESA,
                'automated_verification' => false,
                'supported_countries' => [
                    'KE', // Kenya
                    'TZ', // Tanzania
                    'UG', // Uganda
                    'RW', // Rwanda
                    'ZM', // Zambia
                    'MZ'  // Mozambique
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Till Number', 'value' => 'till number'],
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Till Number',
                        'attribute' => 'till_number',
                        'condition' => ['id_type=till number'],
                        'validation_rules' => [
                            'required' => [true, "The Till Number is required"],
                            'regex_pattern' => ['^\d{5,10}$', "Till Number must be between 5 and 10 digits"]
                        ]
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'PayPal Me',
                'type' => PaymentMethodType::PAYPAL_ME,
                'automated_verification' => false,
                'supported_countries' => null,
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Paypal.me username',
                        'placeholder' => 'https://paypal.me/username',
                        'description' => 'Enter your Paypal.me username',
                        'validation_rules' => $urlValidationRules('Paypal.me username', 'https://paypal.me/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Paypal.me link?'
                            ],
                            [
                                'text' => 'Create Link',
                                'href' => 'https://www.paypal.com/paypalme'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'PayNow',
                'type' => PaymentMethodType::PAYNOW,
                'automated_verification' => false,
                'supported_countries' => [
                    'SG', // Singapore
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'UEN (Unique Entity Number)', 'value' => 'uen'],
                            ['label' => 'VPA (Virtual Payment Address)', 'value' => 'vpa'],
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'UEN (Unique Entity Number)',
                        'attribute' => 'uen',
                        'condition' => ['id_type=uen'],
                        'validation_rules' => [
                            'required' => [true, "The UEN is required"],
                            'regex_pattern' => ['^[0-9A-Z]{9,10}$', "UEN must be 9 or 10 alphanumeric characters"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'label' => 'VPA (Virtual Payment Address)',
                        'attribute' => 'vpa',
                        'condition' => ['id_type=vpa'],
                        'validation_rules' => [
                            'required' => [true, "The VPA is required"],
                            'regex_pattern' => ['^[a-zA-Z0-9_.-]+@[a-zA-Z0-9_.-]+$', "Enter a valid Virtual Payment Address (e.g., user@paynow)"]
                        ]
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'WigWag',
                'type' => PaymentMethodType::WIGWAG,
                'automated_verification' => false,
                'supported_countries' => [
                    'NG', // Nigeria
                    'ZA', // South Africa
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'WigWag username',
                        'placeholder' => 'https://just.wigwag.me/username',
                        'description' => 'Enter your WigWag username',
                        'validation_rules' => $urlValidationRules('WigWag username', 'https://just.wigwag.me/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a WigWag account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://just.wigwag.me/signup/b9a0Dw'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Tikkie',
                'type' => PaymentMethodType::TIKKIE,
                'automated_verification' => false,
                'supported_countries' => [
                    'NL', // Netherlands
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Tikkie payment link',
                        'placeholder' => 'https://tikkie.me/pay/XXXXXXX',
                        'description' => 'Enter your Tikkie payment link',
                        'validation_rules' => $urlValidationRules('Tikkie payment link', 'https://tikkie.me/pay/XXXXXXX')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Tikkie account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.tikkie.me'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Airtel',
                'type' => PaymentMethodType::AIRTEL,
                'automated_verification' => false,
                'supported_countries' => [
                    'CD', // Democratic Republic of Congo
                    'CG', // Republic of Congo
                    'GA', // Gabon
                    'KE', // Kenya
                    'MG', // Madagascar
                    'MW', // Malawi
                    'NE', // Niger
                    'RW', // Rwanda
                    'SC', // Seychelles
                    'TD', // Chad
                    'TZ', // Tanzania
                    'UG', // Uganda
                    'ZM', // Zambia
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'EcoCash',
                'type' => PaymentMethodType::ECOCASH,
                'automated_verification' => false,
                'supported_countries' => [
                    'ZW', // Zimbabwe
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'iKhokha',
                'type' => PaymentMethodType::IKHOKHA,
                'automated_verification' => false,
                'supported_countries' => [
                    'ZA', // South Africa
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'iKhokha payment link',
                        'placeholder' => 'https://pay.ikhokha.com/xxx/yyy/zzz',
                        'description' => 'Enter your iKhokha payment link',
                        'learn_more' => [
                            'label' => 'Learn more',
                            'href' => 'https://youtu.be/QWMOLDTjbZg?si=s7thBtpGlZasc5yK'
                        ],
                        'validation_rules' => $urlValidationRules('iKhokha payment link', 'https://pay.ikhokha.com/xxx/yyy/zzz')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have an iKhokha account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.ikhokha.com'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Revolut',
                'type' => PaymentMethodType::REVOLUT,
                'automated_verification' => false,
                'supported_countries' => [
                    'GB', // United Kingdom
                    'DE', // Germany
                    'FR', // France
                    'ES', // Spain
                    'IT', // Italy
                    'US', // United States
                    'AU', // Australia
                    'SG', // Singapore
                    'BR', // Brazil
                    'JP', // Japan
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Revolut ID',
                        'placeholder' => 'https://revolut.me/username',
                        'description' => 'Enter your Revolut ID',
                        'validation_rules' => $urlValidationRules('Revolut ID', 'https://revolut.me/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Revolut account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.revolut.com'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Pesapal',
                'type' => PaymentMethodType::PESAPAL,
                'automated_verification' => false,
                'supported_countries' => [
                    'KE', // Kenya
                    'UG', // Uganda
                    'TZ', // Tanzania
                    'RW', // Rwanda
                    'ZM', // Zambia
                    'MW', // Malawi
                    'ZW', // Zimbabwe
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Pesapal username',
                        'placeholder' => 'https://payments.pesapal.com/username',
                        'description' => 'Enter your Pesapal username',
                        'validation_rules' => $urlValidationRules('Pesapal username', 'https://payments.pesapal.com/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Pesapal account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://payments.pesapal.com'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'DuitNow',
                'type' => PaymentMethodType::DUITNOW,
                'automated_verification' => false,
                'supported_countries' => [
                    'MY', // Malaysia
                ],
                'config_schema' => [
                    [
                        'type' => 'image',
                        'attribute' => 'photo',
                        'label' => 'DuitNow QR code',
                        'description' => 'Upload your DuitNow QR code',
                        'validation_rules' => [
                            'required' => [true, "The QR Code is required"],
                            'qr_code' => ["The QRIS QR code is not valid"],
                            'mime_types' => [['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], "Only JPEG, JPG, PNG, and GIF formats are allowed"],
                            'max_size' => [5 * 1024 * 1024, "Image size should not exceed 5MB"]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'MonCash',
                'type' => PaymentMethodType::MONCASH,
                'automated_verification' => false,
                'supported_countries' => [
                    'HT', // Haiti
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'MTN MoMo',
                'type' => PaymentMethodType::MTN_MOMO,
                'automated_verification' => false,
                'supported_countries' => [
                    'CD', // Democratic Republic of Congo
                    'CG', // Republic of Congo
                    'GA', // Gabon
                    'KE', // Kenya
                    'MG', // Madagascar
                    'MW', // Malawi
                    'NE', // Niger
                    'RW', // Rwanda
                    'SC', // Seychelles
                    'TD', // Chad
                    'TZ', // Tanzania
                    'UG', // Uganda
                    'ZM', // Zambia
                    'BJ', // Benin
                    'CI', // Côte d'Ivoire (Ivory Coast)
                    'CM', // Cameroon
                    'GH', // Ghana
                    'GN', // Guinea
                    'LR', // Liberia
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Cellmoni',
                'type' => PaymentMethodType::CELLMONI,
                'automated_verification' => false,
                'supported_countries' => [
                    'CM', // Cameroon
                    'PG', // Papua New Guinea
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'TigoPesa',
                'type' => PaymentMethodType::TIGOPESA,
                'automated_verification' => false,
                'supported_countries' => [
                    'TZ', // Tanzania
                    'ML', // Mali
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'InnBucks',
                'type' => PaymentMethodType::INNBUCKS,
                'automated_verification' => false,
                'supported_countries' => [
                    'ZW', // Zimbabwe
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Cash App',
                'type' => PaymentMethodType::CASH_APP,
                'automated_verification' => false,
                'supported_countries' => [
                    'US', // United States
                    'GB', // United Kingdom
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'CashApp username',
                        'placeholder' => 'https://cash.app/$username',
                        'description' => 'Enter your CashApp username',
                        'validation_rules' => $urlValidationRules('CashApp username', 'https://cash.app/$username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a CashApp account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://cash.app'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'PromptPay',
                'type' => PaymentMethodType::PROMPTPAY,
                'automated_verification' => false,
                'supported_countries' => [
                    'TH', // Thailand
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'National ID or Tax ID', 'value' => 'national id or tax id'],
                            ['label' => 'E-wallet ID', 'value' => 'e-wallet id'],
                            ['label' => 'Bank Account Number', 'value' => 'bank account number'],
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'National ID or Tax ID',
                        'attribute' => 'national_id_or_tax_id',
                        'condition' => ['id_type=national id or tax id'],
                        'validation_rules' => [
                            'required' => [true, "The National ID or Tax ID is required"],
                            'regex_pattern' => ['^\d{13}$', "The National ID or Tax ID must be exactly 13 digits"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'label' => 'E-wallet ID',
                        'attribute' => 'e_wallet_id',
                        'condition' => ['id_type=e-wallet id'],
                        'validation_rules' => [
                            'required' => [true, "The E-wallet ID is required"],
                            'regex_pattern' => ['^\d{10,15}$', "The E-wallet ID must be between 10 to 15 digits"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Bank Account Number',
                        'attribute' => 'bank_account_number',
                        'condition' => ['id_type=bank account number'],
                        'validation_rules' => [
                            'required' => [true, "The Bank Account Number is required"],
                            'regex_pattern' => ['^\d{10,15}$', "The Bank Account Number must be between 10 to 15 digits"]
                        ]
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Touch n Go',
                'type' => PaymentMethodType::TOUCH_N_GO,
                'automated_verification' => false,
                'supported_countries' => [
                    'MY', // Malaysia
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Touch n Go payment link',
                        'placeholder' => 'https://payment.tngdigital.com.my/sc/XXXXXXX',
                        'description' => 'Enter your TNG static payment link',
                        'validation_rules' => $urlValidationRules('Touch n Go payment link', 'https://payment.tngdigital.com.my/sc/XXXXXXX')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Touch n Go account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.touchngo.com.my'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Mercado Pago',
                'type' => PaymentMethodType::MERCADO_PAGO,
                'automated_verification' => false,
                'supported_countries' => [
                    'AR', // Argentina
                    'BR', // Brazil
                    'MX', // Mexico
                    'CL', // Chile
                    'CO', // Colombia
                    'UY', // Uruguay
                    'PE', // Peru
                    'BO', // Bolivia
                    'PY', // Paraguay
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Mercado Pago payment link',
                        'placeholder' => 'https://link.mercadopago.com.ar/username',
                        'description' => 'Enter your Mercado Pago payment link',
                        'validation_rules' => $urlValidationRules('Mercado Pago payment link', 'https://link.mercadopago.com.ar/username')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Mercado Pago account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.mercadopago.com.ar'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'SEPA Credit Transfer',
                'type' => PaymentMethodType::SEPA,
                'automated_verification' => false,
                'supported_countries' => [
                    'AT', // Austria
                    'BE', // Belgium
                    'BG', // Bulgaria
                    'CY', // Cyprus
                    'DE', // Germany
                    'DK', // Denmark
                    'EE', // Estonia
                    'ES', // Spain
                    'FI', // Finland
                    'FR', // France
                    'GR', // Greece
                    'HR', // Croatia
                    'HU', // Hungary
                    'IE', // Ireland
                    'IT', // Italy
                    'LT', // Lithuania
                    'LU', // Luxembourg
                    'MT', // Malta
                    'NL', // Netherlands
                    'PL', // Poland
                    'PT', // Portugal
                    'RO', // Romania
                    'SE', // Sweden
                    'SI', // Slovenia
                    'SK', // Slovakia
                    'IS', // Iceland
                    'LI', // Liechtenstein
                    'NO', // Norway
                ],
                'config_schema' => [
                    [
                        'type' => 'string',
                        'label' => 'Account holder name',
                        'placeholder' => 'John Doe',
                        'attribute' => 'account_holder_name',
                        'validation_rules' => [
                            'required' => [true, "The Account Holder Name is required"],
                            'regex_pattern' => ['^[a-zA-Z\s\-]+$', "The Account Holder Name must contain only letters, spaces, and hyphens"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Bank account number (IBAN)',
                        'attribute' => 'bank_account_number',
                        'placeholder' => 'DE44500105175407324931',
                        'validation_rules' => [
                            'required' => [true, "The IBAN is required"],
                            'regex_pattern' => ['^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$', "Enter a valid IBAN (e.g., DE44500105175407324931)"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'BIC',
                        'attribute' => 'bic',
                        'placeholder' => 'DEUTDEFFXXX',
                        'validation_rules' => [
                            'regex_pattern' => ['^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$', "Enter a valid BIC (e.g., DEUTDEFFXXX)"]
                        ]
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Orange Money',
                'type' => PaymentMethodType::ORANGE_MONEY,
                'automated_verification' => false,
                'supported_countries' => [
                    'BW', // Botswana
                    'BF', // Burkina Faso (under the Airtel Money brand)
                    'CM', // Cameroon
                    'CF', // Central African Republic
                    'CD', // Democratic Republic of the Congo
                    'CI', // Côte d'Ivoire
                    'EG', // Egypt (branded as Orange Cash)
                    'GN', // Guinea
                    'GW', // Guinea-Bissau
                    'JO', // Jordan
                    'LR', // Liberia (under the Smile Money brand)
                    'MG', // Madagascar
                    'ML', // Mali
                    'MA', // Morocco
                    'NE', // Niger
                    'SN', // Senegal
                    'RO', // Romania
                    'SL', // Sierra Leone (under the Airtel Money brand)
                    'TN', // Tunisia
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Merchant code', 'value' => 'merchant code'],
                            ['label' => 'Merchant code (QR)', 'value' => 'merchant code qr']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code',
                        'attribute' => 'merchant_code',
                        'condition' => ['id_type=merchant code'],
                        'validation_rules' => $merchantCodeValidationRules()
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Merchant code (QR)',
                        'attribute' => 'merchant_code_qr',
                        'condition' => ['id_type=merchant code qr'],
                        'validation_rules' => [
                            'required' => [true, "The Merchant Code QR is required"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Custom dial code',
                        'attribute' => 'dial_code',
                        'placeholder' => '*123*1*2#',
                        'description' => 'Customize with your own dial code. Include {amount} or {ref} to replace with order amount and reference - e.g *185*1*{amount}*ref#',
                        'description_info' => 'Include {amount} or {ref} to replace with order amount and reference - e.g *123*1*{amount}*{ref}#',
                        'condition' => ['id_type!=merchant code qr'],
                        'validation_rules' => $customDialCodeValidationRules()
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Instapay',
                'type' => PaymentMethodType::Instapay,
                'automated_verification' => false,
                'supported_countries' => [
                    'EG', // Egypt
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'instapay payment link',
                        'options' => [
                            ['label' => 'Instapay payment link', 'value' => 'instapay payment link'],
                            ['label' => 'Instapay ID', 'value' => 'instapay id'],
                            ['label' => 'Phone number', 'value' => 'phone number'],
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Instapay payment link',
                        'placeholder' => 'https://ipn.eg/S/xxx/instapay/yyy',
                        'description' => 'Enter your Instapay payment link',
                        'condition' => ['id_type=instapay payment link'],
                        'validation_rules' => $urlValidationRules('Instapay payment link', 'https://ipn.eg/S/xxx/instapay/yyy')
                    ],
                    [
                        'type' => 'string',
                        'attribute' => 'instapay_id',
                        'label' => 'Instapay ID',
                        'placeholder' => 'xxxx@instapay',
                        'description' => 'Enter your Instapay payment address. This allows direct payments via Instapay.',
                        'condition' => ['id_type=instapay id'],
                        'validation_rules' => [
                            'required' => [true, "The Instapay ID is required"],
                            'regex_pattern' => ['^[a-zA-Z0-9._%+-]+@instapay$', "Enter a valid Instapay ID (e.g., xxxx@instapay)"]
                        ]
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'description' => 'Enter your registered phone number for Instapay.',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have an Instapay account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.instapay.eg'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'bKash',
                'type' => PaymentMethodType::BKASH,
                'automated_verification' => false,
                'supported_countries' => [
                    'BD', // Bangladesh (bKash's primary market)
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'bKash payment link',
                        'placeholder' => 'https://shop.bkash.com/xxxx/paymentlink',
                        'description' => 'Enter your bKash payment link',
                        'learn_more' => [
                            'label' => 'Learn more',
                            'href' => 'https://www.youtube.com/watch?v=ik1ZV5bWUDQ'
                        ],
                        'validation_rules' => $urlValidationRules('bKash payment link', 'https://shop.bkash.com/xxxx/paymentlink')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a bKash account?'
                            ],
                            [
                                'text' => 'Download App',
                                'href' => 'https://www.bkash.com'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Wave',
                'type' => PaymentMethodType::WAVE,
                'automated_verification' => false,
                'supported_countries' => [
                    'CI', // Ivory Coast (Côte d'Ivoire)
                    'SN', // Senegal
                    'UG', // Uganda
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'Wave payment link', 'value' => 'wave payment link']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'description' => 'Enter your registered phone number for Wave.',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Wave payment link',
                        'placeholder' => 'https://pay.wave.com/xxx',
                        'description' => 'Enter your Wave payment link',
                        'condition' => ['id_type=wave payment link'],
                        'validation_rules' => $urlValidationRules('Wave payment link', 'https://pay.wave.com/xxx')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have an Wave account?'
                            ],
                            [
                                'text' => 'Download App',
                                'href' => 'https://www.wave.com'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'OXXO',
                'type' => PaymentMethodType::OXXO,
                'automated_verification' => false,
                'supported_countries' => [
                    'MX', // Mexico
                ],
                'config_schema' => [
                    [
                        'type' => 'string',
                        'label' => 'OXXO account number',
                        'attribute' => 'oxxo_account_number',
                        'placeholder' => '2422123456789012',
                        'description' => 'Enter your OXXO account number',
                        'validation_rules' => [
                            'required' => [true, "The OXXO account number is required"],
                            'regex_pattern' => ['^\d{16}$', "OXXO account number must be exactly 16 digits"]
                        ]
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have an OXXO account?'
                            ],
                            [
                                'text' => 'Download App',
                                'href' => 'https://www.oxxo.com'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'Snapscan',
                'type' => PaymentMethodType::SNAPSCAN,
                'automated_verification' => false,
                'supported_countries' => [
                    'ZA', // South Africa
                ],
                'config_schema' => [
                    [
                        'type' => 'url',
                        'attribute' => 'url',
                        'label' => 'Snapscan payment link',
                        'placeholder' => 'https://pos.snapscan.io/qr/ABCD0123',
                        'description' => 'Enter your Snapscan URL',
                        'validation_rules' => $urlValidationRules('Snapscan payment link', 'https://pos.snapscan.io/qr/ABCD0123')
                    ],
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Don\'t have a Snapscan account?'
                            ],
                            [
                                'text' => 'Sign up',
                                'href' => 'https://www.snapscan.co.za'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'active' => 1,
                'name' => 'MB Way',
                'type' => PaymentMethodType::MBWAY,
                'automated_verification' => false,
                'supported_countries' => [
                    'PT', // Portugal
                ],
                'config_schema' => [
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'MCB Juice',
                'type' => PaymentMethodType::MCBJUICE,
                'automated_verification' => false,
                'supported_countries' => [
                    'MU', // Mauritius
                ],
                'config_schema' => [
                    [
                        'type' => 'select',
                        'label' => 'ID type',
                        'attribute' => 'id_type',
                        'default' => 'phone number',
                        'options' => [
                            ['label' => 'Phone number', 'value' => 'phone number'],
                            ['label' => 'MCB account number', 'value' => 'mcb account number']
                        ],
                        'validation_rules' => $selectValidationRules('ID type')
                    ],
                    [
                        'type' => 'mobile_number',
                        'label' => 'Phone number',
                        'attribute' => 'phone_number',
                        'condition' => ['id_type=phone number'],
                        'validation_rules' => $mobileNumberValidationRules('Phone number')
                    ],
                    [
                        'type' => 'string',
                        'label' => 'MCB account number',
                        'attribute' => 'mcb_account_number',
                        'condition' => ['id_type=mcb account number'],
                        'validation_rules' => [
                            'required' => [true, "The MCB account number is required"],
                            'regex_pattern' => ['^\d{12}$', "MCB account number must be exactly 12 digits"]
                        ]
                    ],
                ],
            ],
            [
                'active' => 1,
                'name' => 'Bank Transfer',
                'type' => PaymentMethodType::BANK_TRANSFER,
                'automated_verification' => false,
                'supported_countries' => null,
                'config_schema' => [
                    [
                        'type' => 'content',
                        'content' => [
                            [
                                'text' => 'Provide details of your bank account for the transfer.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'label' => 'Account Number',
                        'placeholder' => '123456789012 (or IBAN)',
                        'attribute' => 'account_number',
                        'validation_rules' => [
                            'required' => [true, "The Account Number is required"],
                            'regex_pattern' => ['^[A-Za-z0-9]{8,34}$', "Enter a valid account number (IBAN or standard format)"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Account Holder Name',
                        'placeholder' => 'John Doe',
                        'attribute' => 'account_holder_name',
                        'validation_rules' => [
                            'regex_pattern' => ['^[a-zA-Z\s]{2,50}$', "Enter a valid account holder name (only letters and spaces, 2-50 characters)"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Bank Name',
                        'placeholder' => 'Bank of America, HSBC, etc.',
                        'attribute' => 'bank_name',
                        'validation_rules' => [
                            'regex_pattern' => ['^[a-zA-Z\s&]{2,100}$', "Enter a valid bank name (letters, spaces, & allowed, 2-100 characters)"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'SWIFT or Bank Code',
                        'placeholder' => 'CHASUS33 (US), HSBCGB2L (UK), etc.',
                        'attribute' => 'bank_code',
                        'validation_rules' => [
                            'regex_pattern' => ['^[A-Za-z0-9]{6,11}$', "Enter a valid SWIFT or bank code (6-11 alphanumeric characters)"]
                        ]
                    ],
                    [
                        'type' => 'string',
                        'optional' => true,
                        'label' => 'Branch Code or Sort Code',
                        'description' => '',
                        'placeholder' => '1234 (US), 12-34-56 (UK Sort Code), etc.',
                        'validation_rules' => [
                            'regex_pattern' => ['^[0-9-]{4,10}$', "Enter a valid branch code (numeric, with optional dashes, 4-10 characters)"]
                        ]
                    ],
                    [
                        'type' => 'image',
                        'optional' => true,
                        'attribute' => 'photo',
                        'label' => 'Image',
                        'description' => 'Upload supporting image',
                        'validation_rules' => [
                            'mime_types' => [['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], "Only JPEG, JPG, PNG, and GIF formats are allowed"],
                            'max_size' => [5 * 1024 * 1024, "Image size should not exceed 5MB"]
                        ]
                    ]
                ]
            ],
            [
                'active' => 1,
                'name' => 'Cash On Delivery',
                'type' => PaymentMethodType::CASH_ON_DELIVERY,
                'automated_verification' => false,
                'supported_countries' => null, // Global
                'config_schema' => [
                    [
                        'type' => 'image',
                        'optional' => true,
                        'attribute' => 'photo',
                        'label' => 'Image',
                        'description' => 'Upload supporting image',
                        'validation_rules' => [
                            'mime_types' => [['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], "Only JPEG, JPG, PNG, and GIF formats are allowed"],
                            'max_size' => [5 * 1024 * 1024, "Image size should not exceed 5MB"]
                        ]
                    ]
                ]
            ],
            [
                'active' => 1,
                'name' => 'Store Credit',
                'type' => PaymentMethodType::STORE_CREDIT,
                'automated_verification' => false,
                'supported_countries' => null, // Global
                'config_schema' => [
                    [
                        'type' => 'image',
                        'optional' => true,
                        'attribute' => 'photo',
                        'label' => 'Image',
                        'description' => 'Upload supporting image',
                        'validation_rules' => [
                            'mime_types' => [['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], "Only JPEG, JPG, PNG, and GIF formats are allowed"],
                            'max_size' => [5 * 1024 * 1024, "Image size should not exceed 5MB"]
                        ]
                    ]
                ]
            ],
            [
                'active' => 1,
                'name' => 'Other Payment',
                'type' => PaymentMethodType::OTHER,
                'automated_verification' => false,
                'supported_countries' => null, // Global
                'config_schema' => [
                    [
                        'type' => 'image',
                        'optional' => true,
                        'attribute' => 'logo',
                        'label' => 'Logo',
                        'description' => 'Upload logo',
                        'validation_rules' => [
                            'mime_types' => [['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], "Only JPEG, JPG, PNG, and GIF formats are allowed"],
                            'max_size' => [5 * 1024 * 1024, "Image size should not exceed 5MB"]
                        ]
                    ],
                    [
                        'type' => 'image',
                        'optional' => true,
                        'attribute' => 'photo',
                        'label' => 'Image',
                        'description' => 'Upload supporting image',
                        'validation_rules' => [
                            'mime_types' => [['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], "Only JPEG, JPG, PNG, and GIF formats are allowed"],
                            'max_size' => [5 * 1024 * 1024, "Image size should not exceed 5MB"]
                        ]
                    ]
                ]
            ],

            //  Automated Verification

            [
                'active' => 1,
                'name' => 'DPO (Direct Pay Online)',
                'type' => PaymentMethodType::DPO,
                'automated_verification' => true,
                'supported_countries' => [
                    'BW', // Botswana
                    'BF', // Burkina Faso
                    'BI', // Burundi
                    'CM', // Cameroon
                    'CV', // Cape Verde
                    'CF', // Central African Republic
                    'TD', // Chad
                    'KM', // Comoros
                    'CG', // Congo - Brazzaville
                    'CI', // Côte d'Ivoire
                    'DJ', // Djibouti
                    'EG', // Egypt
                    'GQ', // Equatorial Guinea
                    'ER', // Eritrea
                    'SZ', // Eswatini
                    'ET', // Ethiopia
                    'GA', // Gabon
                    'GM', // Gambia
                    'GH', // Ghana
                    'GN', // Guinea
                    'GW', // Guinea-Bissau
                    'KE', // Kenya
                    'LS', // Lesotho
                    'LR', // Liberia
                    'LY', // Libya
                    'MG', // Madagascar
                    'MW', // Malawi
                    'ML', // Mali
                    'MR', // Mauritania
                    'MU', // Mauritius
                    'MA', // Morocco
                    'MZ', // Mozambique
                    'NA', // Namibia
                    'NE', // Niger
                    'NG', // Nigeria
                    'RW', // Rwanda
                    'ST', // São Tomé and Príncipe
                    'SN', // Senegal
                    'SC', // Seychelles
                    'SL', // Sierra Leone
                    'SO', // Somalia
                    'ZA', // South Africa
                    'SS', // South Sudan
                    'SD', // Sudan
                    'TZ', // Tanzania
                    'TG', // Togo
                    'TN', // Tunisia
                    'UG', // Uganda
                    'ZM', // Zambia
                    'ZW'  // Zimbabwe
                ]
            ],
        ];
    }
}
