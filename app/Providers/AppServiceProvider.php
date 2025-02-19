<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\CustomerRepository;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'user' => 'App\Models\User',
            'order' => 'App\Models\Order',
            'store' => 'App\Models\Store',
            'module' => 'App\Models\Module',
            'product' => 'App\Models\Product',
            'product' => 'App\Models\Product',
            'customer' => 'App\Models\Customer',
            'sms alert' => 'App\Models\SmsAlert',
            'transaction' => 'App\Models\Transaction',
            'ai assistant' => 'App\Models\AiAssistant',
            'pricing plan' => 'App\Models\PricingPlan',
            'subscription' => 'App\Models\Subscription',
            'delivery method' => 'App\Models\DeliveryMethod',
            'store payment method' => 'App\Models\Pivots\StorePaymentMethod',
        ]);

        /*
         *  Disable Wrapping API Resources
         *
         *  If you would like to disable the wrapping of the outer-most resource, you may use the
         *  "withoutWrapping" method on the base resource class. Typically, you should call this
         *  method from your AppServiceProvider or another service provider that is loaded on
         *  every request to your application:
         *  Reference: https://laravel.com/docs/5.7/eloquent-resources#concept-overview
         *
         */
        JsonResource::withoutWrapping();
    }
}
