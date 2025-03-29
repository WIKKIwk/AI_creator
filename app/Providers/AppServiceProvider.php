<?php

namespace App\Providers;

use App\Services\Cache\Cache;
use App\Services\Cache\RedisCache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Cache::class, function () {
            return new RedisCache();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
