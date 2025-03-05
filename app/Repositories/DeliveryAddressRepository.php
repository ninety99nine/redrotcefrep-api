<?php

namespace App\Repositories;

use App\Models\User;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Traits\Base\BaseTrait;
use App\Models\DeliveryAddress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Resources\DeliveryAddressResources;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\Relation;

class DeliveryAddressRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show delivery addresses.
     *
     * @param array $data
     * @return DeliveryAddressResources|array
     */
    public function showDeliveryAddresses(array $data = []): DeliveryAddressResources|array
    {
        if($this->getQuery() == null) {

            $userId = isset($data['user_id']) ? $data['user_id'] : null;
            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {
                if(!$this->isAuthourized()); return ['message' => 'You do not have permission to show delivery addresses'];
                $this->setQuery(DeliveryAddress::query()->latest());
            }else {

                $user = in_array($userId, [request()->current_user->id, null]) ? request()->current_user : User::find($userId);

                if($user) {
                    $isAuthourized = $this->isAuthourized() || $user->id == request()->auth_user->id;
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show delivery addresses'];
                }else{
                    return ['message' => 'This user does not exist'];
                }

                $this->setQuery(DeliveryAddress::whereHas('order.store.teamMembersWhoJoined', function ($query) use ($user) {
                    $query->where('user_store_association.user_id', $user->id);
                }));

            }

        }

        return $this->getOutput();
    }

    /**
     * Create delivery address.
     *
     * @param array $data
     * @return DeliveryAddress|array
     */
    public function createDeliveryAddress(array $data): DeliveryAddress|array
    {
        $order = Order::with(['store', 'deliveryAddress'])->find($data['order_id']);

        $deliveryAddressExists = $order->deliveryAddress != null;
        if($deliveryAddressExists) return ['created' => false, 'message' => 'The delivery address already exists for this order'];

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create delivery addresses'];
            }else{
                return ['created' => false, 'message' => 'This store does not exist'];
            }
        }else{
            return ['created' => false, 'message' => 'This order does not exist'];
        }

        $deliveryAddress = DeliveryAddress::create($data);
        return $this->showCreatedResource($deliveryAddress);
    }

    /**
     * Delete delivery addresses.
     *
     * @param array $deliveryAddressIds
     * @return array
     */
    public function deleteDeliveryAddresses(array $deliveryAddressIds): array
    {
        if($this->getQuery() == null) {
            if($this->isAuthourized()) {
                $this->setQuery(DeliveryAddress::query());
            }else{
                $this->setQuery(DeliveryAddress::whereHas('order.store.teamMembersWhoJoined', function ($query) {
                    $query->where('user_store_association.user_id', request()->auth_user->id);
                }));
            }
        }

        $deliveryAddresses = $this->getDeliveryAddressesByIds($deliveryAddressIds);

        if($totalDeliveryAddresses = $deliveryAddresses->count()) {

            foreach($deliveryAddresses as $deliveryAddress) {
                $deliveryAddress->delete();
            }

            return ['deleted' => true, 'message' => $totalDeliveryAddresses . ($totalDeliveryAddresses == 1 ? ' delivery address': ' delivery addresses') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No delivery addresses deleted'];
        }
    }

    /**
     * Show delivery address.
     *
     * @param DeliveryAddress|string|null $deliveryAddressId
     * @return DeliveryAddress|array|null
     */
    public function showDeliveryAddress(DeliveryAddress|string|null $deliveryAddressId = null): DeliveryAddress|array|null
    {
        if(($deliveryAddress = $deliveryAddressId) instanceof DeliveryAddress) {
            $deliveryAddress = $this->applyEagerLoadingOnModel($deliveryAddress);
        }else {
            $query = $this->getQuery() ?? DeliveryAddress::query();
            if($deliveryAddressId) $query = $query->where('delivery_addresses.id', $deliveryAddressId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $deliveryAddress = $this->query->first();
        }

        return $this->showResourceExistence($deliveryAddress);
    }

    /**
     * Update delivery address.
     *
     * @param string $deliveryAddressId
     * @param array $data
     * @return DeliveryAddress|array
     */
    public function updateDeliveryAddress(string $deliveryAddressId, array $data): DeliveryAddress|array
    {
        $deliveryAddress = DeliveryAddress::with(['order.store'])->find($deliveryAddressId);

        if($deliveryAddress) {
            $order = $deliveryAddress->order;
            if($order) {
                $store = $order->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update this delivery address'];
                }else{
                    return ['updated' => false, 'message' => 'This store does not exist'];
                }
            }else{
                return ['updated' => false, 'message' => 'This order does not exist'];
            }

            $deliveryAddress->update($data);
            return $this->showUpdatedResource($deliveryAddress);

        }else{
            return ['updated' => false, 'message' => 'This delivery address does not exist'];
        }
    }

    /**
     * Delete delivery address.
     *
     * @param string $deliveryAddressId
     * @return array
     */
    public function deleteDeliveryAddress(string $deliveryAddressId): array
    {
        $deliveryAddress = DeliveryAddress::with(['order.store'])->find($deliveryAddressId);

        if($deliveryAddress) {
            $order = $deliveryAddress->order;
            if($order) {
                $store = $order->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['removed' => false, 'message' => 'You do not have permission to update this delivery address'];
                }else{
                    return ['removed' => false, 'message' => 'This store does not exist'];
                }
            }else{
                return ['removed' => false, 'message' => 'This order does not exist'];
            }

            $deleted = $deliveryAddress->delete();

            if ($deleted) {
                return ['removed' => true, 'message' => 'Delivery address removed'];
            }else{
                return ['removed' => false, 'message' => 'Delivery address removal unsuccessful'];
            }

        }else{
            return ['removed' => false, 'message' => 'This delivery address does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query delivery address by ID.
     *
     * @param DeliveryAddress|string $deliveryAddressId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryDeliveryAddressById(DeliveryAddress|string $deliveryAddressId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('delivery_addresses.id', $deliveryAddressId)->with($relationships);
    }

    /**
     * Get delivery address by ID.
     *
     * @param DeliveryAddress|string $deliveryAddressId
     * @param array $relationships
     * @return DeliveryAddress|null
     */
    public function getDeliveryAddressById(DeliveryAddress|string $deliveryAddressId, array $relationships = []): DeliveryAddress|null
    {
        return $this->queryDeliveryAddressById($deliveryAddressId, $relationships)->first();
    }

    /**
     * Query delivery addresses by IDs.
     *
     * @param array<string> $deliveryAddressId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryDeliveryAddressesByIds($deliveryAddressIds): Builder|Relation
    {
        return $this->query->whereIn('delivery_addresses.id', $deliveryAddressIds);
    }

    /**
     * Get delivery addresses by IDs.
     *
     * @param array<string> $deliveryAddressId
     * @param string $relationships
     * @return Collection
     */
    public function getDeliveryAddressesByIds($deliveryAddressIds): Collection
    {
        return $this->queryDeliveryAddressesByIds($deliveryAddressIds)->get();
    }
}
