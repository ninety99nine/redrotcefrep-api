<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourierController;

Route::controller(CourierController::class)
    ->prefix('couriers')
    ->group(function () {
        Route::get('/', 'showCouriers')->name('show.couriers');
        Route::post('/', 'createCourier')->name('create.courier');
        Route::delete('/', 'deleteCouriers')->name('delete.couriers');
        Route::post('/arrangement', 'updateCourierArrangement')->name('update.courier.arrangement');

        //  Courier
        Route::prefix('{courierId}')->group(function () {
            Route::get('/', 'showCourier')->name('show.courier');
            Route::put('/', 'updateCourier')->name('update.courier');
            Route::delete('/', 'deleteCourier')->name('delete.courier');
        });
});
