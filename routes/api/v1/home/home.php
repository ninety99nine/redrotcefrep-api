<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

Route::controller(HomeController::class)
    ->withoutMiddleware('auth:sanctum')
    ->group(function () {
        Route::get('/', 'showApiHome')->name('api.home');
        Route::get('/filters', 'showFilters')->name('show.filters');
        Route::get('/sorting', 'showSorting')->name('show.sorting');
        Route::get('/languages', 'showLanguages')->name('show.languages');
        Route::get('/currencies', 'showCurrencies')->name('show.currencies');
        Route::get('/social-media-icons', 'showSocialMediaIcons')->name('show.social.media.icons');
        Route::get('/countries', 'showCountries')->withoutMiddleware('format.response.payload')->name('show.countries');
        Route::get('/country-address-options', 'showCountryAddressOptions')->withoutMiddleware('format.response.payload')->name('show.country.address.options');
});
