<?php

use App\Jobs\SendSms;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/test-sms', function () {

    try {

        /*
        $friends = [(new User)->where('id', 1)->first(), (new User)->where('id', 1)->first(), (new User)->where('id', 1)->first()];
        $friend = (new User)->where('id', 2)->first();
        $customer = (new User)->where('id', 1)->first();
        $order = (new Order)->where('order_for', 'Me And Friends')->first();
        $store = (new Store)->first();
        $transaction = (new Transaction)->whereNotNull('dpo_payment_url')->first();

        $content = $order->craftNewOrderForFriendMessage($store, $customer, $friend, $friends);
        $recipientMobileNumber = $customer->mobile_number->withExtension;
        */
        SendSms::dispatch('This is a test sms', '26772882239', null, null, null);

        return 'Sent!';

    } catch (\Throwable $th) {

        report($th);

    }

});

//  Remove this when running on production
Route::get('/php-info', function () {
    return phpinfo();
});

Route::controller(WebController::class)->group(function(){
    Route::get('/', 'welcome')->name('welcome.page');
    Route::get('/{transaction}/payment-success', 'paymentSuccess')->name('payment.success.page');
    Route::get('/perfect-pay-advertisement', 'perfectPayAdvertisement')->name('perfect.pay.advertisement.page');
});

//  Redirect to terms and conditions
Route::redirect('/terms', config('app.TERMS_AND_CONDITIONS_REDIRECT_URL'), 301)->name('terms.and.conditions.show');

//  Incase we don't match any route
Route::fallback(function() {

    //  Return our 404 Not Found page
    return View('errors.404');

});
