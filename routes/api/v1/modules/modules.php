<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ModuleController;

Route::controller(ModuleController::class)
    ->prefix('modules')
    ->group(function () {
        Route::get('/', 'showModules')->name('show.modules');
        Route::post('/', 'createModule')->name('create.module');
        Route::delete('/', 'deleteModules')->name('delete.modules');
        Route::post('/visibility', 'updateModuleVisibility')->name('update.module.visibility');
        Route::post('/arrangement', 'updateModuleArrangement')->name('update.module.arrangement');

        //  Module
        Route::prefix('{moduleId}')->group(function () {
            Route::get('/', 'showModule')->name('show.module');
            Route::put('/', 'updateModule')->name('update.module');
            Route::delete('/', 'deleteModule')->name('delete.module');

            //  Module Media Files
            Route::prefix('media-files')->group(function () {
                Route::get('/', 'showModuleMediaFiles')->name('show.module.media.files');
                Route::post('/', 'createModuleMediaFile')->name('create.module.media.file');
                Route::prefix('{mediaFileId}')->group(function () {
                    Route::get('/', 'showModuleMediaFile')->name('show.module.media.file');
                    Route::post('/', 'updateModuleMediaFile')->name('update.module.media.file');
                    Route::delete('/', 'deleteModuleMediaFile')->name('delete.module.media.file');
                });
            });
        });
});

