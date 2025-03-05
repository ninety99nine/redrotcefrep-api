<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PromotionController;

Route::controller(PromotionController::class)
    ->prefix('promotions')
    ->group(function () {
        Route::get('/', 'showPromotions')->name('show.promotions');
        Route::post('/', 'createPromotion')->name('create.promotion');
        Route::delete('/', 'deletePromotions')->name('delete.promotions');

        //  Promotion
        Route::prefix('{promotionId}')->group(function () {
            Route::get('/', 'showPromotion')->name('show.promotion');
            Route::put('/', 'updatePromotion')->name('update.promotion');
            Route::delete('/', 'deletePromotion')->name('delete.promotion');
        });
});
