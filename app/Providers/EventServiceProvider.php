<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Product;
use App\Models\Address;
use App\Models\FriendGroup;
use App\Observers\UserObserver;
use App\Observers\OrderObserver;
use App\Observers\StoreObserver;
use App\Observers\ProductObserver;
use App\Observers\AddressObserver;
use App\Observers\FriendGroupObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        Order::observe(OrderObserver::class);
        Store::observe(StoreObserver::class);
        Product::observe(ProductObserver::class);
        Address::observe(AddressObserver::class);
        FriendGroup::observe(FriendGroupObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
