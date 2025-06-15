<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\User;
use App\Jobs\SendSms;
use App\Models\Order;
use App\Models\Review;
use App\Enums\CacheName;
use App\Traits\AuthTrait;
use App\Models\AiMessage;
use App\Models\AiAssistant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\CacheManager;
use App\Traits\Base\BaseTrait;
use App\Enums\RequestFileName;
use App\Enums\ReturnAccessToken;
use App\Helpers\RequestAuthUser;
use App\Models\SubscriptionPlan;
use App\Services\Money\MoneyService;
use App\Http\Resources\UserResource;
use App\Repositories\AuthRepository;
use App\Repositories\BaseRepository;
use App\Http\Resources\UserResources;
use App\Http\Resources\AddressResources;
use App\Repositories\AiMessageRepository;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SmsAlertActivityAssociation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Exceptions\DeleteOfSuperAdminRestrictedException;

class UserRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show users.
     *
     * @param array $data
     * @return UserResources|array
     */
    public function showUsers(array $data = []): UserResources|array
    {
        if($this->getQuery() == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show users'];
            $this->setQuery(User::query()->notGuest()->when(!request()->has('_sort'), fn($query) => $query->latest()));
        }

        return $this->getOutput();
    }

    /**
     * Create user.
     *
     * @param array $data
     * @return UserResource
     */
    public function createUser(array $data): UserResource
    {
        return $this->getAuthRepository()->register($data, ReturnAccessToken::NO);
    }

    /**
     * Delete users.
     *
     * @param array $data
     * @return array
     */
    public function deleteUsers(array $data): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete users'];

        $userIds = $data['user_ids'];
        $users = $this->getUsersByIds($userIds);

        if($totalUsers = $users->count()) {

            foreach($users as $user) {
                $user->delete();
            }

            return ['deleted' => true, 'message' => $totalUsers . ($totalUsers == 1 ? ' user': ' users') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No users deleted'];
        }
    }

    /**
     * Search user by mobile number.
     *
     * @param string $mobileNumber
     * @return User|array|null
     */
    public function searchUserByMobileNumber(string $mobileNumber): User|array|null
    {
        $query = User::searchMobileNumber($mobileNumber);
        $this->setQuery($query)->applyEagerLoadingOnQuery();
        $user = $this->query->first();

        return $this->showResourceExistence($user);
    }

    /**
     * Show user.
     *
     * @param User|string|null $userId
     * @return User|array|null
     */
    public function showUser(User|string|null $userId = null): User|array|null
    {
        if(($user = $userId) instanceof User) {
            $user = $this->applyEagerLoadingOnModel($user);
        }else {
            $query = $this->getQuery() ?? User::query();
            if($userId) $query = $query->where('users.id', $userId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $user = $this->query->first();
        }

        return $this->showResourceExistence($user);
    }

    /**
     * Update user.
     *
     * @param User $user
     * @param array $data
     * @return User|array
     */
    public function updateUser(User $user, array $data): User|array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update this user'];

        if (isset($data['password'])) $data['password'] = AuthRepository::getEncryptedRequestPassword($data['password']);
        $data = collect($data)->only(['first_name', 'last_name', 'about_me', 'mobile_number', 'password'])->toArray();

        $user->update($data);
        $this->forgetCachedUserDetails($user);
        AuthRepository::revokeUserMobileVerificationCode($user);

        return $this->showUpdatedResource($user);
    }

    /**
     * Delete user.
     *
     * @param User $user
     * @return array
     */
    public function deleteUser(User $user): array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to delete this user'];

        $attemptingToDeleteAnotherSuperAdmin = ($user->isSuperAdmin() && $user->id != $this->getAuthUser()->id);
        if($attemptingToDeleteAnotherSuperAdmin) throw new DeleteOfSuperAdminRestrictedException;

        $this->logoutUser($user);
        $user->delete();

        return ['deleted' => true, 'message' => 'User deleted'];
    }

    /**
     * Generate user mobile verification code.
     *
     * @param User $user
     * @return array
     */
    public function generateUserMobileVerificationCode(User $user): array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['generated' => false, 'message' => 'You do not have permission to generate the mobile verification code'];

        return $this->getAuthRepository()->generateMobileVerificationCode($user->mobile_number->formatE164());
    }

    /**
     * Verify user mobile verification code.
     *
     * @param User $user
     * @param array $data
     * @return array
     */
    public function verifyUserMobileVerificationCode(User $user, array $data): array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['message' => 'You do not have permission to verify the mobile verification code'];

        return $this->getAuthRepository()->verifyMobileVerificationCode($user->mobile_number->formatE164(), $data['verification_code']);
    }

    /**
     * Show user tokens.
     *
     * @param User $user
     * @return array
     */
    public function showUserTokens(User $user): array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['message' => 'You do not have permission to show tokens'];

        return $this->getAuthRepository()->showTokens($user);
    }

    /**
     * Logout user.
     *
     * @param User $user
     * @param array $data
     * @return array
     */
    public function logoutUser(User $user, $data = [])
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['logout' => false, 'message' => 'You do not have permission to logout user'];

        return $this->getAuthRepository()->logout($user, $data);
    }

    /**
     * Show user profile photo.
     *
     * @param User $user
     * @return array
     */
    public function showUserProfilePhoto(User $user): array
    {
        return $this->getMediaFileRepository()->setQuery($user->profilePhoto())->showMediaFile();
    }

    /**
     * Upload user profile photo.
     *
     * @param User $user
     * @return array
     */
    public function uploadUserProfilePhoto(User $user): array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['uploaded' => false, 'message' => 'You do not have permission to update user profile photo'];

        if($user->profilePhoto) {
            $result = $this->getMediaFileRepository()->authourize()->updateMediaFile($user->profilePhoto);
        }else{
            $result = $this->getMediaFileRepository()->authourize()->createMediaFile(RequestFileName::PROFILE_PHOTO, $user);
        }

        $uploaded = (isset($result['created']) && $result['created'] == true) || (isset($result['updated']) && $result['updated'] == true);

        if($uploaded) {
            return [
                'uploaded' => $uploaded,
                'message' => 'Profile photo uploaded',
                'media_file' => $result['media_file']
            ];
        }else{
            return ['uploaded' => false, 'message' => 'Store logo failed to upload'];
        }
    }

    /**
     * Delete user profile photo.
     *
     * @param User $user
     * @return array
     */
    public function deleteUserProfilePhoto(User $user): array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete user profile photo'];

        if($user->profilePhoto) {
            return $this->getMediaFileRepository()->deleteMediaFile($user->profilePhoto);
        }else{
            return ['deleted' => false, 'message' => 'This user profile photo does not exist'];
        }
    }

    /**
     * Show user AI Assistant.
     *
     * @param User $user
     * @return array
     */
    public function showUserAiAssistant(User $user): array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['message' => 'You do not have permission to show user AI Assistant'];

        return $this->getAiAssistantRepository()->showResourceExistence($user->aiAssistant);
    }

    /**
     * Show user friends.
     *
     * @param User $user
     * @return UserResources|array
     */
    public function showUserFriends(User $user): UserResources|array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['message' => 'You do not have permission to show user friends'];

        $query = $user->friends()->latest();
        return $this->setQuery($query)->showUsers();
    }

    /**
     * Show user addresses.
     *
     * @param User $user
     * @return AddressResources|array
     */
    public function showUserAddresses(User $user): AddressResources|array
    {
        $isAuthourized = $this->isAuthourized() || $this->getAuthUser()->id == $user->id;
        if(!$isAuthourized) return ['message' => 'You do not have permission to show user addresses'];

        $query = $user->addresses()->latest('updated_at');
        return $this->getAddressRepository()->setQuery($query)->showAddresses();
    }

    /**
     * Show user resource totals.
     *
     * @return array
     */
    public function showUserResourceTotals(User $user, $filter = [])
    {
        $data = [];
        $userId = $user->id;
        $expiryAt = now()->addHour();

        if(empty($filter) || in_array('notifications', $filter)) {

            $data['totalNotifications'] = (new CacheManager(CacheName::TOTAL_NOTIFICATIONS))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->notifications()->count();
            });

        }

        if(empty($filter) || in_array('unreadNotifications', $filter)) {

            $data['totalUnreadNotifications'] = (new CacheManager(CacheName::TOTAL_UNREAD_NOTIFICATIONS))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->notifications()->unread()->count();
            });

        }

        if(empty($filter) || in_array('reviewsAsCustomer', $filter)) {

            $data['totalReviews'] = (new CacheManager(CacheName::TOTAL_REVIEWS))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->reviews()->count();
            });
        }

        if(empty($filter) || in_array('reviewsAsTeamMember', $filter)) {

            $data['totalReviewsAsTeamMember'] = (new CacheManager(CacheName::TOTAL_REVIEWS_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() use ($user) {
                return Review::whereHas('store.teamMembers', function ($query) use ($user) {
                    $query->joinedTeam()->where('user_store_association.user_id', $user->id);
                })->count();
            });

        }

        if(empty($filter) || in_array('ordersAsCustomer', $filter)) {

            $data['totalOrdersAsCustomer'] = (new CacheManager(CacheName::TOTAL_ORDERS_AS_CUSTOMER))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->placedOrders()->count();
            });

        }

        if(empty($filter) || in_array('ordersAsTeamMember', $filter)) {

            $data['totalOrdersAsTeamMember'] = (new CacheManager(CacheName::TOTAL_ORDERS_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() {
                return Order::whereHas('store.teamMembers', function ($query) {
                    $query->joinedTeam();
                })->count();
            });

        }

        if(empty($filter) || in_array('friendGroupsJoined', $filter)) {

            $data['totalFriendGroupsJoined'] = (new CacheManager(CacheName::TOTAL_GROUPS_JOINED))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->friendGroups()->joinedGroup()->count();
            });

        }

        if(empty($filter) || in_array('friendGroupsJoinedAsCreator', $filter)) {

            $data['totalFriendGroupsJoinedAsCreator'] = (new CacheManager(CacheName::TOTAL_GROUPS_JOINED_AS_CREATOR))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->friendGroups()->joinedGroupAsCreator()->count();
            });

        }

        if(empty($filter) || in_array('friendGroupsJoinedAsNonCreator', $filter)) {

            $data['totalFriendGroupsJoinedAsNonCreator'] = (new CacheManager(CacheName::TOTAL_GROUPS_JOINED_AS_NON_CREATOR))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->friendGroups()->joinedGroupAsNonCreator()->count();
            });

        }

        if(empty($filter) || in_array('friendGroupsInvitedToJoinAsGroupMember', $filter)) {

            $data['totalFriendGroupsInvitedToJoinAsGroupMember'] = (new CacheManager(CacheName::TOTAL_GROUPS_INVITED_TO_JOIN_AS_GROUP_MEMBER))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->friendGroups()->invitedToJoinGroup()->count();
            });

        }

        if(empty($filter) || in_array('storesAsFollower', $filter)) {

            $data['totalStoresAsFollower'] = (new CacheManager(CacheName::TOTAL_STORES_AS_FOLLOWER))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->storesAsFollower()->count();
            });

        }

        if(empty($filter) || in_array('storesAsCustomer', $filter)) {

            $data['totalStoresAsCustomer'] = (new CacheManager(CacheName::TOTAL_STORES_AS_CUSTOMER))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->storesAsCustomer()->count();
            });

        }

        if(empty($filter) || in_array('storesAsRecentVisitor', $filter)) {

            $data['totalStoresAsRecentVisitor'] = (new CacheManager(CacheName::TOTAL_STORES_AS_RECENT_VISITOR))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->storesAsRecentVisitor()->count();
            });

        }

        if(empty($filter) || in_array('storesJoinedAsTeamMember', $filter)) {

            $data['totalStoresJoinedAsTeamMember'] = (new CacheManager(CacheName::TOTAL_STORES_JOINED_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->storesAsTeamMember()->joinedTeam()->count();
            });

        }

        if(empty($filter) || in_array('storesJoinedAsCreator', $filter)) {

            $data['totalStoresJoinedAsCreator'] = (new CacheManager(CacheName::TOTAL_STORES_JOINED_AS_CREATOR))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->storesAsTeamMember()->joinedTeamAsCreator()->count();
            });

        }

        if(empty($filter) || in_array('storesJoinedAsNonCreator', $filter)) {

            $data['totalStoresJoinedAsCreator'] = (new CacheManager(CacheName::TOTAL_STORES_JOINED_AS_NON_CREATOR))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->storesAsTeamMember()->joinedTeamAsNonCreator()->count();
            });

        }

        if(empty($filter) || in_array('storesInvitedToJoinAsTeamMember', $filter)) {

            $data['totalStoresInvitedToJoinAsTeamMember'] = (new CacheManager(CacheName::TOTAL_STORES_INVITED_TO_JOIN_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->storesAsTeamMember()->invitedToJoinTeam()->count();
            });

        }

        if(empty($filter) || in_array('activeAutoBillingSchedules', $filter)) {

            $data['totalActiveAutoBillingSchedules'] = (new CacheManager(CacheName::TOTAL_ACTIVE_AUTO_BILLING_SCHEDULES))->append($userId)->remember($expiryAt, function() use ($user) {
                return $user->autoBillingSchedules()->active()->count();
            });

        }

        return $data;
    }

    /***********************************************
     *            MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query user by ID.
     *
     * @param User|string $userId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryUserById(User|string $userId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('users.id', $userId)->with($relationships);
    }

    /**
     * Get user by ID.
     *
     * @param User|string $userId
     * @param array $relationships
     * @return User|null
     */
    public function getUserById(User|string $userId, array $relationships = []): User|null
    {
        return $this->queryUserById($userId, $relationships)->first();
    }

    /**
     * Query users by IDs.
     *
     * @param array<string> $userId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryUsersByIds($userIds): Builder|Relation
    {
        return $this->query->whereIn('users.id', $userIds);
    }

    /**
     * Get users by IDs.
     *
     * @param array<string> $userId
     * @param string $relationships
     * @return Collection
     */
    public function getUsersByIds($userIds): Collection
    {
        return $this->queryUsersByIds($userIds)->get();
    }

    /**
     * Return the Guest User ID
     *
     * @return string
     */
    public function getGuestUserId()
    {
        return (new CacheManager(CacheName::GUEST_USER_ID))->rememberForever(function () {
            return $this->getGuestUser()->id;
        });
    }

    /**
     * Return the Guest User instance
     *
     * @return User
     */
    public function getGuestUser()
    {
        return (new CacheManager(CacheName::GUEST_USER))->rememberForever(function () {
            $guestUser = User::where('is_guest', '1')->first();
            return $guestUser ? $guestUser : $this->createGuestUser();
        });
    }

    /**
     * Create the Guest User
     *
     * @return User
     */
    public function createGuestUser()
    {
        return User::create([
            'is_guest' => true,
            'password' => NULL,
            'last_name' => NULL,
            'last_seen_at' => NULL,
            'mobile_number' => NULL,
            'first_name' => 'Guest',
            'remember_token' => NULL,
            'is_super_admin' => false,
            'mobile_number_verified_at' => NULL,
        ]);
    }

    /**
     * Forget cached user details. Incase this user is currently logged in and their account
     * details have been cached, we would need to clear their cached account details by
     * acquiring this access tokens and then forgetting any account details cached
     * using the bearer tokens of these access tokens.
     *
     * @param User $user
     * @return void
     */
    private function forgetCachedUserDetails(User $user)
    {
        $accessTokens = $user->tokens()->get();

        foreach($accessTokens as $accessToken) {
            (new RequestAuthUser())->forgetAuthUserOnCacheUsingAccessToken($accessToken);
        }
    }



















































    /**
     * Show user order filters
     *
     * @return array
     */
    public function showUserOrderFilters()
    {
        return $this->orderRepository()->showUserOrderFilters($this->getUser());
    }

    /**
     * Show user orders
     *
     * @return OrderRepository
     */
    public function showUserOrders()
    {
        return $this->orderRepository()->showUserOrders($this->getUser());
    }

    /**
     * Show user review filters
     *
     * @return array
     */
    public function showReviewFilters()
    {
        return $this->reviewRepository()->showUserReviewFilters($this->getUser());
    }

    /**
     * Show user reviews
     *
     * @return ReviewRepository
     */
    public function showReviews()
    {
        return $this->reviewRepository()->showUserReviews($this->getUser());
    }

    /**
     * Show the user's first store
     *
     * @return array
     */
    public function showUserFirstCreatedStore()
    {
        return $this->storeRepository()->showUserFirstCreatedStore($this->getUser());
    }

    /**
     * Show user store filters
     *
     * @return array
     */
    public function showStoreFilters()
    {
        return $this->storeRepository()->showUserStoreFilters($this->getUser());
    }

    /**
     * Show user stores
     *
     * @return StoreRepository
     */
    public function showStores()
    {
        return $this->storeRepository()->showUserStores($this->getUser());
    }

    /**
     * Create user store
     *
     * @return StoreRepository
     */
    public function createStore(Request $request)
    {
        return $this->storeRepository()->create($request);
    }

    /**
     * Request the AI Assistant payment shortcode
     *
     * This will allow the user to dial the shortcode and pay via USSD
     *
     * @return StoreRepository
     */
    public function generateAiAssistantPaymentShortcode(Request $request)
    {
        $user = $this->getUser();

        //  Get the User ID that this shortcode is reserved for
        $reservedForUserId = $user->id;

        //  Get the AI Assistant information for the user
        $aiAssistant = $this->aiAssistantRepository()->showAiAssistant($user)->model;

        //  Request a payment shortcode for this AI Assistant
        $shortcodeRepository = $this->shortcodeRepository()->createPaymentShortcode($aiAssistant, $reservedForUserId);

        //  If we want to return the AI Assistant model with the payment shortcode embedded
        if( $request->input('embed') ) {

            //  Set the AI Assistant as the repository model with the payment shortcode
            return $this->aiAssistantRepository(

                //  Load the payment shortcode on this AI Assistant
                $aiAssistant->load('authPaymentShortcode')

            );

        //  If we want to return the payment shortcode alone
        }else{

            //  Return the shortcode repository
            return $shortcodeRepository;

        }
    }

    /**
     * Show the AI Assistant subscriptions
     *
     * @return SubscriptionRepository
     * @throws ModelNotFoundException
     */
    public function showAiAssistantSubscriptions()
    {
        //  Get the user
        $user = $this->getUser();

        //  Get the AI Assistant information for the user
        $aiAssistant = $this->aiAssistantRepository()->showAiAssistant($user)->model;

        //  Return the subscription repository
        return $this->subscriptionRepository()->setModel($aiAssistant->subscriptions())->get();
    }

    /**
     * Calculate AI access subscription amount
     *
     * @param Request $request
     * @return array
     */
    public function calculateAiAccessSubscriptionAmount(Request $request)
    {
        //  Get the Subscription Plan ID
        $subscriptionPlanId = $request->input('subscription_plan_id');

        //  Get the Subscription Plan
        $subscriptionPlan = SubscriptionPlan::find($subscriptionPlanId);

        //  Calculate the subscription plan amount
        $amount = $this->subscriptionPlanRepository()->setModel($subscriptionPlan)->calculateSubscriptionPlanAmountAgainstSubscriptionDuration($request);

        return [
            'calculation' => MoneyService::convertToMoneyFormat($amount, 'BWP')
        ];
    }

    /**
     * Create AI Assistant subscription
     *
     * A subscription enables the user access to the AI Assistant.
     *
     * @return SubscriptionRepository | AiAssistantRepository
     * @throws ModelNotFoundException
     */
    public function createAiAssistantSubscription(Request $request)
    {
        $user = $this->getUser();

        //  Get the AI Assistant information for the user
        $aiAssistant = $this->aiAssistantRepository()->showAiAssistant($user)->model;

        //  Get the latest subscription matching the given user to this AiAssistant model
        $latestSubscription = $aiAssistant->subscriptions()->where('user_id', $user->id)->latest()->first();

        //  Create a subscription
        $subscriptionRepository = $this->subscriptionRepository()->createSubscription($aiAssistant, $request, $latestSubscription);

        //  Expire the active payment shortcode
        $this->shortcodeRepository()->setModel($aiAssistant->shortcodes()->payable())->expireShortcode();

        //  Get the subscription
        $subscription = $subscriptionRepository->model;

        //  Get the subscription end datetime
        $subscriptionExpiresAt = $subscription->end_at;

        //  Get the subscription transaction amount
        $transactionAmount = $subscription->transaction->amount->amount;

        //  Calculate the new paid tokens
        $newPaidTokens = $transactionAmount * AiAssistant::PAID_TOKEN_RATE;

        //  If the remaining paid tokens have not yet expired
        if(Carbon::parse($aiAssistant->remaining_paid_tokens_expire_at)->isFuture()) {

            //  Set the remaining paid tokens
            $remainingPaidTokens = ($aiAssistant->remaining_paid_tokens) + $newPaidTokens;

        }else{

            //  Set the remaining paid tokens
            $remainingPaidTokens = $newPaidTokens;

        }

        //  The remaining paid tokens after the last subscription are the current remaining paid tokens
        //  before the user starts consuming these remaining paid tokens.
        $remainingPaidTokensAfterLastSubscription = $remainingPaidTokens;

        //  Update the AI Assistant model
        $aiAssistant->update([
            'requires_subscription' => false,
            'remaining_paid_tokens' => $remainingPaidTokens,
            'remaining_paid_tokens_expire_at' => $subscriptionExpiresAt,
            'remaining_paid_tokens_after_last_subscription' => $remainingPaidTokensAfterLastSubscription,
        ]);

        // Send sms to user that their subscription was paid successfully
        SendSms::dispatch(
            $subscription->craftSubscriptionSuccessfulSmsMessageForUser($user, $aiAssistant),
            $user->mobile_number->formatE164(),
            null, null, null
        );

        //  If we want to return the AI Assistant model with the subscription embedded
        if( $request->input('embed') ) {

            /**
             * Set the AI Assistant as the repository model with the
             * current authenticated user's active subscription
             */
            return $this->setModel(

                //  Load the subscription on this AI Assistant
                $aiAssistant->load(['authActiveSubscription'])

            );

        //  If we want to return the subscription alone
        }else{

            //  Return the subscription repository model
            return $subscriptionRepository;

        }
    }

    /**
     * Show AI Assistant
     *
     * @return AiAssistantRepository
     */
    public function showAiAssistant()
    {
        return $this->aiAssistantRepository()->showAiAssistant($this->getUser());
    }

    /**
     * Show AI messages
     *
     * @param Request $request
     * @return AiMessageRepository
     */
    public function showAiMessages(Request $request)
    {
        $aiMessages = $this->getUser()->aiMessages()->latest();

        if($categoryId = $request->input('category_id')) {
            $aiMessages = $aiMessages->where('category_id', $categoryId);
        }

        return $this->aiMessageRepository()->setModel($aiMessages)->get();
    }

    /**
     * Create an AI message
     *
     * @param Request $request
     * @return AiMessageRepository|null
     */
    public function createAiMessage(Request $request)
    {
        return $this->aiMessageRepository()->createUserAiMessage($this->getUser(), $request, false);
    }

    /**
     * Create an AI message while streaming
     *
     * @param Request $request
     * @return AiMessageRepository|null
     */
    public function createAiMessageWhileStreaming(Request $request)
    {
        return $this->aiMessageRepository()->createUserAiMessage($this->getUser(), $request, true);
    }

    /**
     * Show an AI message
     *
     * @param AiMessage $aiMessage
     * @return AiMessageRepository
     */
    public function showAiMessage(AiMessage $aiMessage)
    {
        return $this->aiMessageRepository()->setModel($aiMessage);
    }

    /**
     * Update an AI message
     *
     * @param Request $request
     * @param AiMessage $aiMessage
     * @return AiMessageRepository
     */
    public function updateAiMessage(Request $request, AiMessage $aiMessage)
    {
        return $this->aiMessageRepository()->setModel($aiMessage)->update($request);
    }

    /**
     * Delete an AI message
     *
     * @param AiMessage $aiMessage
     * @return array
     */
    public function deleteAiMessage(AiMessage $aiMessage)
    {
        return $this->aiMessageRepository()->setModel($aiMessage)->delete();
    }

    /**
     * Show Sms Alert
     *
     * @return SmsAlertRepository
     */
    public function showSmsAlert()
    {
        return $this->smsAlertRepository()->showSmsAlert($this->getUser());
    }

    /**
     * Request the Sms Alert payment shortcode
     *
     * This will allow the user to dial the shortcode and pay via USSD
     *
     * @return StoreRepository
     */
    public function generateSmsAlertPaymentShortcode(Request $request)
    {
        $user = $this->getUser();

        //  Get the User ID that this shortcode is reserved for
        $reservedForUserId = $user->id;

        //  Get the SMS Alert information for the user
        $smsAlert = $this->showSmsAlert()->model;

        //  Request a payment shortcode for this SMS Alert
        $shortcodeRepository = $this->shortcodeRepository()->createPaymentShortcode($smsAlert, $reservedForUserId);

        //  If we want to return the SMS Alert model with the payment shortcode embedded
        if( $request->input('embed') ) {

            //  Set the SMS Alert as the repository model with the payment shortcode
            return $this->smsAlertRepository(

                //  Load the payment shortcode on this SMS Alert
                $smsAlert->load('authPaymentShortcode')

            );

        //  If we want to return the payment shortcode alone
        }else{

            //  Return the shortcode repository
            return $shortcodeRepository;

        }
    }

    /**
     * Show the SMS Alert transactions
     *
     * @return TransactionRepository
     */
    public function showSmsAlertTransactions()
    {
        //  Get the SMS Alert information for the user
        $smsAlert = $this->showSmsAlert()->model;

        //  Return the transaction repository
        return $this->transactionRepository()->setModel($smsAlert->transactions()->latest())->get();
    }

    /**
     * Create SMS Alert transaction
     *
     * This grants the user sms credits to be used for SMS Alerts
     *
     * @return SubscriptionRepository | AiAssistantRepository
     */
    public function createSmsAlertTransaction(Request $request)
    {
        //  Get the SMS Alert information for the user
        $smsAlert = $this->showSmsAlert()->model;

        //  Get the Subscription Plan ID
        $subscriptionPlanId = $request->input('subscription_plan_id');

        //  Get the Subscription Plan
        $subscriptionPlan = SubscriptionPlan::find($subscriptionPlanId);

        //  Create a transaction
        $transactionRepository = $this->transactionRepository()->createTransaction($smsAlert, $subscriptionPlan, $request);

        //  Expire the active payment shortcode
        $this->shortcodeRepository()->setModel($smsAlert->shortcodes())->expireShortcode();

        //  Get the Subscription Plan sms credits
        $smsCredits = $this->subscriptionPlanRepository()->setModel($subscriptionPlan)->getSubscriptionPlanSmsCredits($request);

        //  Update the SMS Alert sms credits
        $smsAlert->update(['sms_credits' => ($smsAlert->sms_credits + $smsCredits)]);

        //  Get the transaction
        $transaction = $transactionRepository->model;

        // Send sms to user that their transaction was paid successfully
        SendSms::dispatch(
            $smsAlert->craftSmsAlertsPaidSuccessfullyMessage($smsCredits, $transaction),
            $this->getUser()->mobile_number->formatE164(),
            null, null, null
        );

        //  If we want to return the SMS Alert model with the transaction embedded
        if( $request->input('embed') ) {

            /**
             * Set the SMS Alert as the repository model
             * with the latest transaction
             */
            return $this->setModel(

                //  Load the latest transaction on this SMS Alert
                $smsAlert->load(['latestTransaction'])

            );

        //  If we want to return the transaction alone
        }else{

            //  Return the transaction repository model
            return $transactionRepository;

        }
    }

    /**
     * Calculate SMS Alert transaction amount
     *
     * @param Request $request
     * @return array
     */
    public function calculateSmsAlertTransactionAmount(Request $request)
    {
        //  Get the sms credits required
        $smsCredits = $request->input('sms_credits');

        //  Get the Subscription Plan ID
        $subscriptionPlanId = $request->input('subscription_plan_id');

        //  Get the Subscription Plan
        $subscriptionPlan = SubscriptionPlan::find($subscriptionPlanId);

        //  Calculate the transaction amount
        $amount = $subscriptionPlan->price->amount * $smsCredits;

        return [
            'calculation' => MoneyService::convertToMoneyFormat($amount, 'BWP')
        ];
    }

    /**
     * Update the sms alert activity association
     *
     * @param SmsAlertActivityAssociation $smsAlertActivityAssociation
     * @param Request $request
     * @return SmsAlertActivityAssociationRepository
     */
    public function updateSmsAlertActivityAssociation(SmsAlertActivityAssociation $smsAlertActivityAssociation, Request $request)
    {
        return $this->smsAlertActivityAssociationRepository()->setModel($smsAlertActivityAssociation)->update($request);
    }




    /**
     * Show the user resource totals
     *
     * @return array
     */
    public function showResourceTotals()
    {
        $data = [];
        $userId = $this->getUser()->id;
        $expiryAt = now()->addHour();

        $includedResourceTotals = collect(explode(',', request()->input('_include_resource_totals')))->map(function($field) {
            return Str::camel(trim($field));
        })->filter()->toArray();

        if(in_array('totalSmsAlertCredits', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalSmsAlertCredits'] = (new CacheManager(CacheName::TOTAL_SMS_ALERT_CREDITS))->append($userId)->remember($expiryAt, function() {
                $smsAlert = $this->showSmsAlert()->model;
                return $smsAlert->sms_credits;
            });

        }

        if(in_array('totalNotifications', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalNotifications'] = (new CacheManager(CacheName::TOTAL_NOTIFICATIONS))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->notifications()->count();
            });

        }

        if(in_array('totalUnreadNotifications', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalUnreadNotifications'] = (new CacheManager(CacheName::TOTAL_UNREAD_NOTIFICATIONS))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->notifications()->unread()->count();
            });

        }

        if(in_array('totalReviews', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalReviews'] = (new CacheManager(CacheName::TOTAL_REVIEWS))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->reviews()->count();
            });
        }

        if(in_array('totalReviewsAsTeamMember', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalReviewsAsTeamMember'] = (new CacheManager(CacheName::TOTAL_REVIEWS_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() {
                return Review::whereHas('store.teamMembers', function ($query) {
                    $query->joinedTeam()->where('user_store_association.user_id', $this->getUser()->id);
                })->count();
            });

        }

        if(in_array('totalOrdersAsCustomer', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalOrdersAsCustomer'] = (new CacheManager(CacheName::TOTAL_ORDERS_AS_CUSTOMER))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->ordersAsCustomer()->count();
            });

        }

        if(in_array('totalOrdersAsTeamMember', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalOrdersAsTeamMember'] = (new CacheManager(CacheName::TOTAL_ORDERS_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() {
                return Order::whereHas('store.teamMembers', function ($query) {
                    $query->joinedTeam();
                })->count();
            });

        }

        if(in_array('totalOrdersAsCustomerOrFriend', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalOrdersAsCustomerOrFriend'] = (new CacheManager(CacheName::TOTAL_ORDERS_AS_CUSTOMER_OR_FRIEND))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->ordersAsCustomerOrFriend()->count();
            });

        }

        if(in_array('totalGroupsJoined', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalGroupsJoined'] = (new CacheManager(CacheName::TOTAL_GROUPS_JOINED))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->friendGroups()->joinedGroup()->count();
            });

        }

        if(in_array('totalGroupsJoinedAsCreator', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalGroupsJoinedAsCreator'] = (new CacheManager(CacheName::TOTAL_GROUPS_JOINED_AS_CREATOR))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->friendGroups()->joinedGroupAsCreator()->count();
            });

        }

        if(in_array('totalGroupsJoinedAsNonCreator', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalGroupsJoinedAsNonCreator'] = (new CacheManager(CacheName::TOTAL_GROUPS_JOINED_AS_NON_CREATOR))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->friendGroups()->joinedGroupAsNonCreator()->count();
            });

        }

        if(in_array('totalGroupsInvitedToJoinAsGroupMember', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalGroupsInvitedToJoinAsGroupMember'] = (new CacheManager(CacheName::TOTAL_GROUPS_INVITED_TO_JOIN_AS_GROUP_MEMBER))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->friendGroups()->invitedToJoinGroup()->count();
            });

        }

        if(in_array('totalStoresAsFollower', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalStoresAsFollower'] = (new CacheManager(CacheName::TOTAL_STORES_AS_FOLLOWER))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->storesAsFollower()->count();
            });

        }

        if(in_array('totalStoresAsCustomer', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalStoresAsCustomer'] = (new CacheManager(CacheName::TOTAL_STORES_AS_CUSTOMER))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->storesAsCustomer()->count();
            });

        }

        if(in_array('totalStoresAsRecentVisitor', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalStoresAsRecentVisitor'] = (new CacheManager(CacheName::TOTAL_STORES_AS_RECENT_VISITOR))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->storesAsRecentVisitor()->count();
            });

        }

        if(in_array('totalStoresJoinedAsTeamMember', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalStoresJoinedAsTeamMember'] = (new CacheManager(CacheName::TOTAL_STORES_JOINED_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->storesAsTeamMember()->joinedTeam()->count();
            });

        }

        if(in_array('totalStoresJoinedAsCreator', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalStoresJoinedAsCreator'] = (new CacheManager(CacheName::TOTAL_STORES_JOINED_AS_CREATOR))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->storesAsTeamMember()->joinedTeamAsCreator()->count();
            });

        }

        if(in_array('totalStoresJoinedAsNonCreator', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalStoresJoinedAsCreator'] = (new CacheManager(CacheName::TOTAL_STORES_JOINED_AS_NON_CREATOR))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->storesAsTeamMember()->joinedTeamAsNonCreator()->count();
            });

        }

        if(in_array('totalStoresInvitedToJoinAsTeamMember', $includedResourceTotals) || empty($includedResourceTotals)) {

            $data['totalStoresInvitedToJoinAsTeamMember'] = (new CacheManager(CacheName::TOTAL_STORES_INVITED_TO_JOIN_AS_TEAM_MEMBER))->append($userId)->remember($expiryAt, function() {
                return $this->getUser()->storesAsTeamMember()->invitedToJoinTeam()->count();
            });

        }

        return $data;
    }
}
