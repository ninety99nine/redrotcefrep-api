<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SectionController;

Route::controller(PageController::class)
    ->prefix('pages')
    ->group(function () {
        Route::get('/', 'showPages')->name('show.pages');
        Route::post('/', 'createPage')->name('create.page');
        Route::delete('/', 'deletePages')->name('delete.pages');
        Route::post('/visibility', 'updatePageVisibility')->name('update.page.visibility');
        Route::post('/arrangement', 'updatePageArrangement')->name('update.page.arrangement');

        //  Page
        Route::prefix('{pageId}')->group(function () {
            Route::get('/', 'showPage')->name('show.page');
            Route::put('/', 'updatePage')->name('update.page');
            Route::delete('/', 'deletePage')->name('delete.page');

            //  Sections
            Route::controller(SectionController::class)->prefix('sections')->group(function () {
                Route::get('/', 'showSections')->name('show.page.sections');
            });
        });
});

