<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\ModuleController;

Route::controller(ColumnController::class)
    ->prefix('columns')
    ->group(function () {
        Route::get('/', 'showColumns')->name('show.columns');
        Route::post('/', 'createColumn')->name('create.column');
        Route::delete('/', 'deleteColumns')->name('delete.columns');
        Route::post('/visibility', 'updateColumnVisibility')->name('update.column.visibility');
        Route::post('/arrangement', 'updateColumnArrangement')->name('update.column.arrangement');

        //  Column
        Route::prefix('{columnId}')->group(function () {
            Route::get('/', 'showColumn')->name('show.column');
            Route::put('/', 'updateColumn')->name('update.column');
            Route::delete('/', 'deleteColumn')->name('delete.column');

            //  Modules
            Route::controller(ModuleController::class)->prefix('modules')->group(function () {
                Route::get('/', 'showModules')->name('show.column.modules');
            });
        });
});

