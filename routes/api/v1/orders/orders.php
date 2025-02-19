<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TransactionController;

Route::controller(OrderController::class)
    ->prefix('orders')
    ->group(function () {
        Route::get('/', 'showOrders')->name('show.orders');
        Route::post('/', 'createOrder')->name('create.order')->withoutMiddleware('auth:sanctum');
        Route::delete('/', 'deleteOrders')->name('delete.orders');
        Route::get('/status/counts', 'showOrderStatusCounts')->name('show.order.status.counts');

        //  Order
        Route::prefix('{orderId}')->group(function () {
            Route::get('/', 'showOrder')->name('show.order');
            Route::put('/', 'updateOrder')->name('update.order');
            Route::delete('/', 'deleteOrder')->name('delete.order');
            Route::get('/cancellation-reasons', 'showOrderCancellationReason')->name('show.order.cancellation.reasons');

            Route::post('/generate-collection-code', 'generateOrderCollectionCode')->name('generate.order.collection.code');
            Route::post('/revoke-collection-code', 'revokeOrderCollectionCode')->name('revoke.order.collection.code');
            Route::post('/verify-order-collection', 'verifyOrderCollection')->name('verify.order.collection');

            Route::post('/update-status', 'updateOrderStatus')->name('update.order.status');
            Route::post('/request-payment', 'requestOrderPayment')->name('request.order.payment');
            Route::get('/verify-payment/{transactionId}', 'verifyOrderPayment')->name('verify.order.payment')->withoutMiddleware(['auth:sanctum', 'format.request.payload']);
            Route::get('/request-payment/payment-methods', 'showPaymentMethodsForRequestingOrderPayment')->name('show.payment.methods.for.requesting.order.payment');

            Route::post('/mark-as-paid', 'markOrderAsPaid')->name('mark.order.as.paid');
            Route::post('/mark-as-unpaid', 'markOrderAsUnpaid')->name('mark.order.as.unpaid');
            Route::get('/mark-as-paid/payment-methods', 'showPaymentMethodsForMarkingAsPaid')->name('show.payment.methods.for.marking.as.paid');

            Route::get('/cart', 'showOrderCart')->name('show.order.cart');
            Route::get('/store', 'showOrderStore')->name('show.order.store');
            Route::get('/customer', 'showOrderCustomer')->name('show.order.customer');
            Route::get('/occasion', 'showOrderOccasion')->name('show.order.occasion');
            Route::get('/placed-by-user', 'showOrderPlacedByUser')->name('show.order.placed.by.user');
            Route::get('/created-by-user', 'showOrderCreatedByUser')->name('show.order.created.by.user');
            Route::get('/collection-verified-by-user', 'showOrderCollectionVerifiedByUser')->name('show.order.collection.verified.by.user');
            Route::get('/delivery-address', 'showOrderDeliveryAddress')->name('show.order.delivery.address');

            Route::get('/friend-group', 'showOrderFriendGroup')->name('show.order.friend.group');
            Route::post('/friend-group', 'addOrderFriendGroup')->name('add.order.friend.group');
            Route::delete('/friend-group', 'removeOrderFriendGroup')->name('remove.order.friend.group');

            Route::get('/viewers', 'showOrderViewers')->name('show.order.viewers');

            //  Transactions
            Route::controller(TransactionController::class)->prefix('transactions')->group(function () {
                Route::get('/', 'showTransactions')->name('show.order.transactions');
            });
        });
});
