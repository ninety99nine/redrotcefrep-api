<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorePaymentMethodController;

Route::controller(StorePaymentMethodController::class)
    ->prefix('store-payment-methods')
    ->group(function () {
        Route::get('/', 'showStorePaymentMethods')->name('show.store.payment.methods');
        Route::post('/', 'createStorePaymentMethod')->name('create.store.payment.method');
        Route::delete('/', 'deleteStorePaymentMethods')->name('delete.store.payment.methods');
        Route::post('/arrangement', 'updateStorePaymentMethodArrangement')->name('update.store.payment.method.arrangement');

        //  Store Payment Method
        Route::prefix('{storePaymentMethodId}')->group(function () {
            Route::get('/', 'showStorePaymentMethod')->name('show.store.payment.method');
            Route::put('/', 'updateStorePaymentMethod')->name('update.store.payment.method');
            Route::delete('/', 'deleteStorePaymentMethod')->name('delete.store.payment.method');

            //  Logo
            Route::get('/logo', 'showStorePaymentMethodLogo')->name('show.store.payment.method.logo');
            Route::post('/logo', 'uploadStorePaymentMethodLogo')->name('upload.store.payment.method.logo');
            Route::delete('/logo', 'deleteStorePaymentMethodLogo')->name('delete.store.payment.method.logo');

            //  Photo
            Route::get('/photo', 'showStorePaymentMethodPhoto')->name('show.store.payment.method.photo');
            Route::post('/photo', 'uploadStorePaymentMethodPhoto')->name('upload.store.payment.method.photo');
            Route::delete('/photo', 'deleteStorePaymentMethodPhoto')->name('delete.store.payment.method.photo');
        });
});
