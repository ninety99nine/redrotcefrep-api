<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderPromotionController;

Route::controller(OrderPromotionController::class)
    ->prefix('order-promotions')
    ->group(function () {
        Route::get('/', 'showOrderPromotions')->name('show.order.promotions');

        //  Order Promotion
        Route::prefix('{orderPromotionId}')->group(function () {
            Route::get('/', 'showOrderPromotion')->name('show.order.promotion');
        });
});
