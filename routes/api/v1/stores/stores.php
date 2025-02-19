<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\StorePaymentMethodController;
use App\Http\Controllers\DeliveryMethodController;

Route::controller(StoreController::class)
    ->scopeBindings()
    ->prefix('stores')
    ->group(function () {

    Route::get('/', 'showStores')->name('show.stores');
    Route::post('/', 'createStore')->name('create.store');

    Route::withoutMiddleware('auth:sanctum')->group(function () {
        Route::post('/search-by-alias', 'searchStoreByAlias')->name('search.store.by.alias');
        Route::post('/search-by-ussd-mobile-number', 'searchStoreByUssdMobileNumber')->name('search.store.by.ussd.mobile.number');
    });

    Route::get('/last-visited-store', 'showLastVisitedStore')->name('show.last.visited.store');
    Route::get('/deposit-options', 'showStoreDepositOptions')->name('show.store.deposit.options');
    Route::get('/installment-options', 'showStoreInstallmentOptions')->name('show.store.installment.options');

    //  Follow Store Invitations
    Route::get('/check-invitations-to-follow-stores', 'checkInvitationsToFollowStores')->name('check.invitations.to.follow.stores');
    Route::put('/accept-all-invitations-to-follow-stores', 'acceptAllInvitationsToFollowStores')->name('accept.all.invitations.to.follow.stores');
    Route::put('/decline-all-invitations-to-follow-stores', 'declineAllInvitationsToFollowStores')->name('decline.all.invitations.to.follow.stores');

    //  Join Store Invitations
    Route::get('/check-invitations-to-join-stores', 'checkInvitationsToJoinStores')->name('check.invitations.to.join.stores');
    Route::put('/accept-all-invitations-to-join-stores', 'acceptAllInvitationsToJoinStores')->name('accept.all.invitations.to.join.stores');
    Route::put('/decline-all-invitations-to-join-stores', 'declineAllInvitationsToJoinStores')->name('decline.all.invitations.to.join.stores');

    //  Store
    Route::prefix('{storeId}')->group(function () {
        Route::get('/', 'showStore')->name('show.store');
        Route::put('/', 'updateStore')->name('update.store');
        Route::delete('/', 'deleteStore')->name('delete.store');
        Route::get('/logo', 'showStoreLogo')->name('show.store.logo');

        Route::post('/logo', 'uploadStoreLogo')->name('upload.store.logo');
        Route::get('/cover-photo', 'showStoreCoverPhoto')->name('show.store.cover.photo');
        Route::post('/cover-photo', 'uploadStoreCoverPhoto')->name('upload.store.cover.photo');

        //  Adverts
        Route::prefix('adverts')->group(function () {
            Route::get('/', 'showStoreAdverts')->name('show.store.adverts');
            Route::post('/', 'uploadStoreAdvert')->name('upload.store.advert');
        });

        Route::get('/qr-code-image-preview', 'showStoreQrCodeImagePreview')->withoutMiddleware('auth:sanctum')->name('show.store.qr.code.image.preview');

        //  Quick Start Guide
        Route::get('/quick-start-guide', 'showStoreQuickStartGuide')->name('show.store.quick.start.guide');

        //  Insights
        Route::get('/insights', 'showStoreInsights')->name('show.store.insights');

        //  Followers
        Route::prefix('followers')->group(function () {
            Route::get('/', 'showStoreFollowers')->name('show.store.followers');
            Route::post('/', 'inviteStoreFollowers')->name('invite.store.followers');
        });

        //  Following
        Route::prefix('following')->group(function () {
            Route::get('/', 'showStoreFollowing')->name('show.store.following');
            Route::put('/', 'updateStoreFollowing')->name('update.store.following');
        });

        //  Invitations To Follow
        Route::post('/accept-invitation-to-follow-store', 'acceptInvitationToFollowStore')->name('accept.invitation.to.follow.store');
        Route::post('/decline-invitation-to-follow-store', 'declineInvitationToFollowStore')->name('decline.invitation.to.follow.store');

        //  Team Members
        Route::prefix('team-members')->group(function () {

            Route::get('/', 'showStoreTeamMembers')->name('show.store.team.members');
            Route::post('/', 'inviteStoreTeamMembers')->name('invite.store.team.members');
            Route::delete('/', 'removeStoreTeamMembers')->name('remove.store.team.members');
            Route::get('/my-permissions', 'showMyStorePermissions')->name('show.my.store.permissions');
            Route::get('/team-member-permission-options', 'showTeamMemberPermissionOptions')->name('show.team.member.permission.options');

            Route::prefix('{teamMemberId}')->group(function () {
                Route::get('/', 'showStoreTeamMember')->name('show.store.team.member');
                Route::get('/permissions', 'showStoreTeamMemberPermissions')->name('show.store.team.member.permissions');
                Route::put('/permissions', 'updateStoreTeamMemberPermissions')->name('update.store.team.member.permissions');
            });

        });

        //  Invitations To Join Team
        Route::post('/accept-invitation-to-join-store-team', 'acceptInvitationToJoinStoreTeam')->name('accept.invitation.to.join.store.team');
        Route::post('/decline-invitation-to-join-store-team', 'declineInvitationToJoinStoreTeam')->name('decline.invitation.to.join.store.team');

        //  Pages
        Route::controller(PageController::class)->prefix('pages')->group(function () {
            Route::get('/', 'showPages')->name('show.store.pages');
        });

        //  Orders
        Route::controller(OrderController::class)->prefix('orders')->group(function () {
            Route::get('/', 'showOrders')->name('show.store.orders');
        });

        //  Products
        Route::controller(ProductController::class)->group(function () {

            Route::prefix('products')->group(function () {
                Route::get('/', 'showProducts')->name('show.store.products');
            });

            Route::withoutMiddleware('auth:sanctum')->group(function () {
                Route::get('/shopping/products', 'showProducts')->name('show.store.shopping.products');
            });

        });

        //  Coupons
        Route::controller(CouponController::class)->prefix('coupons')->group(function () {
            Route::get('/', 'showCoupons')->name('show.store.coupons');
        });

        //  Reviews
        Route::controller(ReviewController::class)->prefix('reviews')->group(function () {
            Route::get('/', 'showReviews')->name('show.store.reviews');
        });

        //  Customers
        Route::controller(CustomerController::class)->prefix('customers')->group(function () {
            Route::get('/', 'showCustomers')->name('show.store.customers');
        });

        //  Subscriptions
        Route::controller(SubscriptionController::class)->prefix('subscriptions')->group(function () {
            Route::get('/', 'showSubscriptions')->name('show.store.subscriptions');
        });

        //  Transactions
        Route::controller(TransactionController::class)->prefix('transactions')->group(function () {
            Route::get('/', 'showTransactions')->name('show.store.transactions');
        });

        //  Store Payment Methods
        Route::controller(StorePaymentMethodController::class)->prefix('payment-methods')->group(function () {
            Route::get('/', 'showStorePaymentMethods')->name('show.store.payment.methods.2');
        });

        //  Delivery Methods
        Route::controller(DeliveryMethodController::class)->group(function () {

            Route::prefix('delivery-methods')->group(function () {
                Route::get('/', 'showDeliveryMethods')->name('show.store.delivery.methods');
            });

            Route::withoutMiddleware('auth:sanctum')->group(function () {
                Route::get('/shopping/delivery-methods', 'showDeliveryMethods')->name('show.store.shopping.delivery.methods');
            });

        });

        //  Workflows
        Route::controller(WorkflowController::class)->prefix('workflows')->group(function () {
            Route::get('/', 'showWorkflows')->name('show.store.workflows');
        });









        //  Payment methods
        Route::get('/supported-payment-methods', 'showSupportedPaymentMethods')->name('.supported.payment.methods.show');
        Route::get('/available-payment-methods', 'showAvailablePaymentMethods')->name('.available.payment.methods.show');
        Route::get('/available-deposit-percentages', 'showAvailableDepositPercentages')->name('.available.deposit.percentages.show');
        Route::get('/available-installment-percentages', 'showAvailableInstallmentPercentages')->name('.available.installment.percentages.show');



















        //  Sharable Content
        Route::prefix('sharable-content')
            ->name('.sharable.content')->group(function () {

            Route::get('/', 'showSharableContent')->name('.show');
            Route::get('/choices', 'showSharableContentChoices')->name('.choices.show');

        });

    });

});
