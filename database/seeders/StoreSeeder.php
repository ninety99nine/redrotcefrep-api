<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PricingPlan;
use Illuminate\Http\Request;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Repositories\StoreRepository;
use Database\Seeders\Traits\SeederHelper;
use App\Models\Pivots\UserStoreAssociation;

class StoreSeeder extends Seeder
{
    use SeederHelper;

    /**
     *  Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //  Create fake users only on local/dev environment
        if (app()->environment('local', 'dev')) {

            $storeRepository = resolve(StoreRepository::class);

            //  Get the user ids in random order
            $userIds = DB::table('users')->inRandomOrder()->pluck('id');

            foreach($userIds as $userId) {

                /**
                 *  Log in as this user
                 *  -------------------
                 *
                 *  Set the current user id as the authentication user id.
                 *  This is important since the created() method is called
                 *  on the StoreObserver class so that the authenticated
                 *  user is registered as the creator of these stores.
                 *
                 *  Note that by default we use the "auth" guard to authenticate,
                 *  particularly using the "sanctum" driver. This does not
                 *  support the loginUsingId() method. Therefore we should
                 *  specify using the "web" guard which supports this
                 *  loginUsingId() method.
                 */
                auth('web')->loginUsingId($userId);

                //  Create 1 to 5 fake stores for this logged in user
                $stores = Store::factory()->count(rand(1, 5))->has(

                    //  Create 1 to 10 fake products for this store
                    Product::factory()->count(rand(1, 10))->state(function (array $attributes, Store $store) use ($userId) {

                        //  Set the user_id on the product (This means that this user created this product)
                        return ['user_id' => $userId];

                    })

                )->has(

                    //  Create 0 to 5 fake promotions for this store
                    Promotion::factory()->count(rand(0, 5))->state(function (array $attributes, Store $store) use ($userId) {

                        //  Set the user_id on the product (This means that this user created this product)
                        return ['user_id' => $userId];

                    })

                )->create();

                //  Get a random pricing plan
                $pricingPlan = PricingPlan::find(rand(1, PricingPlan::count()));

                //  Create a new request with the subscription plan and payment method
                $request = (new Request)->merge([
                    'pricing_plan' => $pricingPlan->id,
                    'payment_method_id' => 1
                ]);

                //  Foreach created store
                foreach($stores as $store) {

                    //  Get the other user ids that are not the current authenticated user
                    $otherUserIds = collect($userIds)->filter(function($currUserId) use ($userId) {

                        //  Must not be the current authenticated user's id
                        return $currUserId != $userId;

                    })->shuffle();

                    //  Get a random number of the user ids
                    $selectedUserIds = $otherUserIds->slice(0, $otherUserIds->count() - 1)->toArray();

                    foreach($selectedUserIds as $selectedUserId) {

                        //  By chance, if the user has a following connection
                        if(rand(1, 100) > 80) {

                            //  Get a random follower status
                            $followerStatus = collect(UserStoreAssociation::FOLLOWER_STATUSES())->random();

                        }else{

                            //  Do not set the following connection
                            $followerStatus = null;

                        }

                        //  By chance, if the user has a team member connection
                        if(rand(1, 100) > 80) {

                            //  Get a random team member role except the creator role
                            $teamMemberRole = collect(UserStoreAssociation::TEAM_MEMBER_ROLES())->filter(function($role) {

                                //  Must not be a creator
                                return $role != 'Creator';

                            })->random();

                            //  Get a random team member status
                            $teamMemberStatus = collect(UserStoreAssociation::TEAM_MEMBER_STATUSES())->random();

                            //  Get the available store permissions
                            $storePermissions = $storeRepository->extractPermissions(['*']);

                            //  Get the team member permissions based on their roles
                            $teamMemberPermissions = $teamMemberRole == 'Admin' ? ['*'] : collect($storePermissions)->map(fn($permission) => $permission['grant'])->random(rand(1, count($storePermissions)));

                        }else{

                            //  Do not set the team member connection
                            $teamMemberRole = null;
                            $teamMemberStatus = null;
                            $teamMemberPermissions = [];

                        }

                        $hasActivityAsFollower = in_array($followerStatus, ['Following', 'Unfollowed', 'Declined']);
                        $hasActivityAsTeamMember = in_array($teamMemberStatus, ['Joined', 'Left', 'Declined']);

                        if($hasActivityAsFollower || $hasActivityAsTeamMember) {
                            $lastSeenAt = now();
                        }else{
                            $lastSeenAt = null;
                        }

                        //  Associate the selected user with this store
                        $store->users()->attach([
                            $selectedUserId => [
                                'team_member_permissions' => json_encode($teamMemberPermissions),
                                'team_member_status' => $teamMemberStatus,
                                'team_member_role' => $teamMemberRole,
                                'follower_status' => $followerStatus,
                                'last_seen_at' => $lastSeenAt
                            ]
                        ]);

                        //  If this user has a team member role
                        if($teamMemberRole != null) {

                            /**
                             *  Log in as this team member
                             *
                             *  Note that by default we use the "auth" guard to authenticate,
                             *  particularly using the "sanctum" driver. This does not
                             *  support the loginUsingId() method. Therefore we should
                             *  specify using the "web" guard which supports this
                             *  loginUsingId() method.
                             */
                            auth('web')->loginUsingId($selectedUserId);

                            //  Create a new team member subscription to this store
                            $storeRepository->setModel($store)->createSubscription($request);

                        }

                    }

                }

            }

        }
    }
}
