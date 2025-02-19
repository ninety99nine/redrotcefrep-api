<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RowController;
use App\Http\Controllers\SectionController;

Route::controller(SectionController::class)
    ->prefix('sections')
    ->group(function () {
        Route::get('/', 'showSections')->name('show.sections');
        Route::post('/', 'createSection')->name('create.section');
        Route::delete('/', 'deleteSections')->name('delete.sections');
        Route::post('/visibility', 'updateSectionVisibility')->name('update.section.visibility');
        Route::post('/arrangement', 'updateSectionArrangement')->name('update.section.arrangement');

        //  Section
        Route::prefix('{sectionId}')->group(function () {
            Route::get('/', 'showSection')->name('show.section');
            Route::put('/', 'updateSection')->name('update.section');
            Route::delete('/', 'deleteSection')->name('delete.section');

            //  Rows
            Route::controller(RowController::class)->prefix('rows')->group(function () {
                Route::get('/', 'showRows')->name('show.section.rows');
            });
        });
});

