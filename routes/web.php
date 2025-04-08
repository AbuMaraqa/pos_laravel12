<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;


Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => [ 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath' ]
    ], function(){

    Livewire::setUpdateRoute(function ($handle) {
        return Route::post('/livewire/update', $handle);
    });

    Route::get('/', function () {
        return view('welcome');
    })->name('home');

    Route::view('dashboard', 'dashboard')
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Route::get('settings/profile', Profile::class)->name('settings.profile');
        Route::get('settings/password', Password::class)->name('settings.password');
        Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    });


    Route::get('/product/index', \App\Livewire\Pages\Product\Index::class)->name('product.index');
    Route::get('/product/add', \App\Livewire\Pages\Product\Add::class)->name('product.add');

    Route::get('/product/attributes/add', \App\Livewire\Pages\Product\Attributes\Index::class)->name('product.attributes.add');

    Route::get('/order/index', \App\Livewire\Pages\Order\Index::class)->name('order.index');
    Route::get('/order/{order}/details', \App\Livewire\Pages\Order\Details::class)->name('order.details');

});

require __DIR__.'/auth.php';
