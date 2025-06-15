<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutoBillingScheduleController;

Route::controller(AutoBillingScheduleController::class)
    ->prefix('auto-billing-schedules')
    ->group(function () {
        Route::get('/', 'showAutoBillingSchedules')->name('show.auto.billing.schedules');
        Route::post('/', 'createAutoBillingSchedules')->name('create.auto.billing.schedule');
        Route::delete('/', 'deleteAutoBillingSchedules')->name('delete.auto.billing.schedules');

        //  Auto Billing Schedule
        Route::prefix('{autoBillingScheduleId}')->group(function () {
            Route::get('/', 'showAutoBillingSchedule')->name('show.auto.billing.schedule');
            Route::put('/', 'updateAutoBillingSchedule')->name('update.auto.billing.schedule');
            Route::delete('/', 'deleteAutoBillingSchedule')->name('delete.auto.billing.schedule');
        });
});
