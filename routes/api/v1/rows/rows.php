<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RowController;
use App\Http\Controllers\ColumnController;

Route::controller(RowController::class)
    ->prefix('rows')
    ->group(function () {
        Route::get('/', 'showRows')->name('show.rows');
        Route::post('/', 'createRow')->name('create.row');
        Route::delete('/', 'deleteRows')->name('delete.rows');
        Route::post('/visibility', 'updateRowVisibility')->name('update.row.visibility');
        Route::post('/arrangement', 'updateRowArrangement')->name('update.row.arrangement');

        //  Row
        Route::prefix('{rowId}')->group(function () {
            Route::get('/', 'showRow')->name('show.row');
            Route::put('/', 'updateRow')->name('update.row');
            Route::delete('/', 'deleteRow')->name('delete.row');

            //  Columns
            Route::controller(ColumnController::class)->prefix('columns')->group(function () {
                Route::get('/', 'showColumns')->name('show.row.columns');
            });
        });
});

