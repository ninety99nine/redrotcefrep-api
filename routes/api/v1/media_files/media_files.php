<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaFileController;

Route::controller(MediaFileController::class)
    ->prefix('media-files')
    ->group(function () {
        Route::get('/', 'showMediaFiles')->name('show.media.files');
        Route::post('/', 'createMediaFile')->name('create.media.file');
        Route::delete('/', 'deleteMediaFiles')->name('delete.media.files');

        //  Media File
        Route::prefix('{mediaFileId}')->group(function () {
            Route::get('/', 'showMediaFile')->name('show.media.file');
            Route::post('/', 'updateMediaFile')->name('update.media.file');
            Route::delete('/', 'deleteMediaFile')->name('delete.media.file');
        });
});
