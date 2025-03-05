<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderProductController;

Route::controller(OrderProductController::class)
    ->prefix('order-products')
    ->group(function () {
        Route::get('/', 'showOrderProducts')->name('show.order.products');

        //  Order Product
        Route::prefix('{orderProductId}')->group(function () {
            Route::get('/', 'showOrderProduct')->name('show.order.product');
        });
});
