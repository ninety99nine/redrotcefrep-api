<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Configs -  By Julian B Tabona
    |--------------------------------------------------------------------------
    |
    | These values are configurations that are application specific and not
    | required or implemented by the Laravel Framework.
    |
    */
    'ORANGE_AIRTIME_BILLING_CLIENT_SECRET' => env('ORANGE_AIRTIME_BILLING_CLIENT_SECRET'),
    'ORANGE_AIRTIME_BILLING_ON_BEHALF_OF' => env('ORANGE_AIRTIME_BILLING_ON_BEHALF_OF'),
    'TERMS_AND_CONDITIONS_REDIRECT_URL' => env('TERMS_AND_CONDITIONS_REDIRECT_URL'),
    'FRONTEND_SOCIAL_AUTH_REDIRECT_URI' => env('FRONTEND_SOCIAL_AUTH_REDIRECT_URI'),
    'ORANGE_AIRTIME_BILLING_CLIENT_ID' => env('ORANGE_AIRTIME_BILLING_CLIENT_ID'),
    'USSD_RESERVED_SHORTCODE_RANGE' => env('USSD_RESERVED_SHORTCODE_RANGE', 0),
    'ORANGE_MONEY_PUSH_PAYMENT_URL' => env('ORANGE_MONEY_PUSH_PAYMENT_URL'),
    'ORANGE_AIRTIME_BILLING_URL' => env('ORANGE_AIRTIME_BILLING_URL'),
    'USSD_MAIN_SHORT_CODE' => env('USSD_MAIN_SHORT_CODE'),
    'DPO_CREATE_TOKEN_URL' => env('DPO_CREATE_TOKEN_URL'),
    'DPO_CANCEL_TOKEN_URL' => env('DPO_CANCEL_TOKEN_URL'),
    'DPO_VERIFY_TOKEN_URL' => env('DPO_VERIFY_TOKEN_URL'),
    'DPO_COMPANY_TOKEN' => env('DPO_COMPANY_TOKEN'),
    'DPO_COUNTRY_CODE' => env('DPO_COUNTRY_CODE'),
    'DEFAULT_CURRENCY' => env('DEFAULT_CURRENCY'),
    'DEFAULT_LANGUAGE' => env('DEFAULT_LANGUAGE'),
    'DEFAULT_COUNTRY' => env('DEFAULT_COUNTRY'),
    'DPO_PAYMENT_URL' => env('DPO_PAYMENT_URL'),
    'USSD_ENDPOINT' => env('USSD_ENDPOINT'),
    'FRONTEND_URI' => env('FRONTEND_URI'),
    'USSD_TOKEN' => env('USSD_TOKEN'),

    'SMS_SENDER_MOBILE_NUMBER' => env('SMS_SENDER_MOBILE_NUMBER'),
    'SMS_SENDER_NAME' => env('SMS_SENDER_NAME'),
    'SMS_CREDENTIALS' => env('SMS_CREDENTIALS'),
    'SMS_ENABLED' => env('SMS_ENABLED'),
    'SMS_URL' => env('SMS_URL'),

    'OPENAI_API_TEMPERATURE' => env('OPENAI_API_TEMPERATURE'),
    'OPENAI_API_MAX_TOKENS' => env('OPENAI_API_MAX_TOKENS'),
    'OPENAI_API_MODEL' => env('OPENAI_API_MODEL'),
    'OPENAI_API_KEY' => env('OPENAI_API_KEY'),
    'OPENAI_API_URL' => env('OPENAI_API_URL'),

    'STORES_SLACK_WEBHOOK_URL' => env('STORES_SLACK_WEBHOOK_URL'),
    'ORDERS_SLACK_WEBHOOK_URL' => env('ORDERS_SLACK_WEBHOOK_URL'),

    'AWS_DEFAULT_REGION' => env('AWS_DEFAULT_REGION'),
    'AWS_BUCKET' => env('AWS_BUCKET'),


    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'Africa/Gaborone',   //    'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([

        /*
         * Package Service Providers...
         */
        Bugsnag\BugsnagLaravel\BugsnagServiceProvider::class,
        OpenAI\Laravel\ServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\TelescopeServiceProvider::class,

    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        'Bugsnag' => Bugsnag\BugsnagLaravel\Facades\Bugsnag::class,
        'OpenAI' => OpenAI\Laravel\Facades\OpenAI::class,
    ])->toArray(),

];
