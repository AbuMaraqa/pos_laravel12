<?php

namespace App\Providers;

use App\Livewire\Pages\Product\Wizard\ProductInfo;
use App\Livewire\Pages\Product\Wizard\ProductVariation;
use App\Livewire\Pages\Product\WizardRegistration;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Livewire::component('checkout-wizard', WizardRegistration::class);
        Livewire::component('product-info', ProductInfo::class);
        Livewire::component('product-variation', ProductVariation::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
