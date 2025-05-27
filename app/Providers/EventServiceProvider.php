<?php

namespace App\Providers;

use App\Events\ProdOrderChanged;
use App\Events\SupplyOrderClosed;
use App\Listeners\ProdOrderNotification;
use App\Listeners\SupplyOrderAfterClose;
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
        ProdOrderChanged::class => [
            ProdOrderNotification::class
        ],
        SupplyOrderClosed::class => [
            SupplyOrderAfterClose::class
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
