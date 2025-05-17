<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::get('/variations', \App\Livewire\VariationManager::class);

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]
    ], function(){

    Livewire::setUpdateRoute(function ($handle) {
        return Route::post('/livewire/update', $handle);
    });

    Route::get('/', function () {
        return redirect()->route('login');
    })->name('home');

    Route::get('dashboard', \App\Livewire\Pages\Dashboard::class)
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Route::get('settings/profile', Profile::class)->name('settings.profile');
        Route::get('settings/password', Password::class)->name('settings.password');
        Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    });

    Route::get('/category/index', \App\Livewire\Pages\Category\Index::class)->name('category.index');


    Route::get('/product/index', \App\Livewire\Pages\Product\Index::class)->name('product.index');
    Route::get('/product/add', \App\Livewire\Pages\Product\Add::class)->name('product.add');
    Route::get('/products/{id}/edit', App\Livewire\Pages\Product\Edit::class)->name('products.edit');
    Route::get('/product/attributes/add', \App\Livewire\Pages\Product\Attributes\Index::class)->name('product.attributes.add');
    Route::get('/product/variation/image/{id}', \App\Livewire\Pages\Product\VariationImages::class)->name('product.variation.image');
    Route::get('/inventory/index', \App\Livewire\Pages\Inventory\Index::class)->name('inventory.index');

    Route::get('/order/index', \App\Livewire\Pages\Order\Index::class)->name('order.index');
    Route::get('/order/{order}/details', \App\Livewire\Pages\Order\Details::class)->name('order.details');

    Route::get('/client/index', \App\Livewire\Pages\User\Index::class)->name('client.index');
    Route::get('/client/{id}/profile', \App\Livewire\Pages\User\Profile::class)->name('client.profile');

    Route::get('/report/index', \App\Livewire\Pages\Report\Index::class)->name('report.index');

    Route::get('/pos/index', \App\Livewire\Pages\Pos\Index::class)->name('pos.index');

});

require __DIR__.'/auth.php';
