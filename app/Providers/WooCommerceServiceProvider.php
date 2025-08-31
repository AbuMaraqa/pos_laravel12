<?php

namespace App\Providers;

use App\Services\WooCommerceService;
use Illuminate\Support\ServiceProvider;

class WooCommerceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(WooCommerceService::class, function ($app) {
            $userId = auth()->check() ? auth()->id() : null;
            return new WooCommerceService($userId);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
