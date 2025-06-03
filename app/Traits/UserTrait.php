<?php

namespace App\Traits;

use Exception;
use App\Models\Order;
use App\Models\Store;
use App\Models\Product;
use App\Enums\CacheName;
use App\Enums\TeamMemberStatus;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Helpers\CacheManager;
use App\Traits\Base\BaseTrait;
use App\Helpers\PlatformManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Pivots\UserStoreAssociation;
use App\Exceptions\XPlatformHeaderRequiredException;

trait UserTrait
{
    use BaseTrait;

    public function isSuperAdmin()
    {
        return $this->is_super_admin;
    }

    /**
     *  Update the last time the user was seen using services
     *  @return void
     */
    public function updateLastSeen() {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     *  Check if the current authenticated user has the given permissions on the store
     *  by passing the request as a parameter to be checked
     *
     *  @param Request $request
     *  @param string $permission
     *
     *  @return bool
     */
    public function hasStorePermissionUsingRequest(Request $request, $permission)
    {
        //  Initialise the model
        $model = null;

        //  If we have the store via the request
        if( $request->store ) {

            //  Get the store
            $model = $request->store;

        //  If we have the product via the request
        }elseif( $request->product ) {

            //  Get the product
            $model = $request->product;

        //  If we have the order via the request
        }elseif( $request->order ) {

            //  Get the order
            $model = $request->order;

        }

        //  If we have a requets model e.g store, order, product, e.t.c
        if($model) {

            return $this->hasStorePermission($model, $permission);

        }else{

            throw new Exception('This route does not contain the store id required to check permissions', Response::HTTP_BAD_REQUEST);

        }
    }

    /**
     *  Check if the current authenticated user has the given permissions on the store
     *
     *  @param ?Model $model
     *  @param string $permission
     *
     *  @return bool
     */
    public function hasStorePermission(?Model $model, string $permission)
    {
        //  Initialise the store
        $storeId = null;

        //  If we have the store as the model
        if( $model instanceof Store ) {

            //  Get the store id
            $storeId = $model->id;

        //  If we have the order as the model
        }elseif( $model instanceof Order ) {

            //  Get the order store id
            $storeId = $model->store_id;

        //  If we have the product as the model
        }elseif( $model instanceof Product ) {

            //  Get the product store id
            $storeId = $model->store_id;

        }

        //  If we have the store id
        if($storeId) {

            //  Check if we have the permissions stored in cache memory
            $hasPermissions = $this->getHasStorePermissionFromCache($storeId, $permission);

            //  If the permissions are not stored in cache memory
            if( $hasPermissions == null ) {

                //  Get the matching store
                if( $store = $this->storesAsTeamMember()->joinedTeam()->where('store_id', $storeId)->first() ) {

                    //  Check if the user has the given permissions on the store
                    $hasPermissions = collect($store->user_store_association->team_member_permissions)->contains(function($teamMemberPermission) use ($permission) {

                        //  Check if we have all permissions or atleast the permission required
                        return ($teamMemberPermission['grant'] == '*') || (strtolower($teamMemberPermission['grant']) == strtolower($permission));

                    });

                }

            }

            /**
             *  Add the permission status as a cache value so that we limit
             *  the numbers of times that we have to make this request. If
             *  the cache value already exists, then overide to extend the
             *  time to expiry.
             */
            $this->addHasStorePermissionIntoCache($storeId, $permission, $hasPermissions);

            return $hasPermissions;

        }else{

            throw new Exception('This route does not contain the store id required to check permissions.', Response::HTTP_BAD_REQUEST);

        }
    }

    /**
     *  Check if the current authenticated user has the given permissions
     *  on the store by checking the cache
     *
     *  @param string $storeId
     *  @param string $permission
     *
     *  @return bool
     */
    public function getHasStorePermissionFromCache($storeId, $permission)
    {
        return $this->hasStorePermissionCacheManager($storeId, $permission)->get();
    }

    /**
     *  Add a cache value which shows that the current authenticated user
     *  has the given permissions on the store. This cache value is valid
     *  for one day.
     *
     *  @param string $storeId
     *  @param string $permission
     *  @param boolean $status
     *
     *  @return bool
     */
    public function addHasStorePermissionIntoCache($storeId, $permission, $status)
    {
        return $this->hasStorePermissionCacheManager($storeId, $permission)->put($status, now()->addDay());
    }

    /**
     *  Remove the cache value which shows that the current authenticated user
     *  has the given permissions on the store
     *
     *  @param string $storeId
     *  @param string $permission
     *
     *  @return bool
     */
    public function removeHasStorePermissionFromCache($storeId, $permission)
    {
        return $this->hasStorePermissionCacheManager($storeId, $permission)->forget();
    }

    /**
     *  Get the has store permission cache manager
     *
     *  @return CacheManager
     */
    public function hasStorePermissionCacheManager($storeId, $permission)
    {
        return (new CacheManager(CacheName::HAS_STORE_PERMISSION))->append($permission)->append($storeId)->append(request()->auth_user->id);
    }

    /**
     *  Craft the sms alerts payment success sms messsage
     *
     *  @return string
     */
    public function craftSmsAlertsPaidSuccessfullyMessage(int $smsCredits, Transaction $transaction) {
        return $transaction->amount->amountWithCurrency.' paid successfully for ' . $smsCredits . ($smsCredits == 1 ? ' sms alert' : ' sms alerts').'. You now have '. $this->sms_credits . ($this->sms_credits == 1 ? ' sms alert' : ' sms alerts');
    }

}
