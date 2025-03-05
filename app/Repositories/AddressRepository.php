<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Store;
use App\Models\Address;
use App\Models\Customer;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Traits\Base\BaseTrait;
use App\Http\Resources\AddressResources;
use App\Models\DeliveryMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class AddressRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show addresses.
     *
     * @param array $data
     * @return AddressResources|array
     */
    public function showAddresses(array $data = []): AddressResources|array
    {
        if($this->getQuery() == null) {

            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {
                if(!$this->isAuthourized()); return ['message' => 'You do not have permission to show addresses'];
                $this->setQuery(Address::query()->latest());
            }else {
                $this->setQuery(request()->current_user->addresses()->latest());
            }

        }

        return $this->getOutput();
    }

    /**
     * Create address.
     *
     * @param array $data
     * @return Address|array
     */
    public function createAddress(array $data): Address|array
    {
        if(isset($data['user_id'])) {

            $user = User::find($data['user_id']);
            if($user) {
                $isAuthourized = $this->isAuthourized() || $user->id == request()->current_user->id;
                if(!$isAuthourized) return ['message' => 'You do not have permission to add addresses'];
                $data = array_merge($data, ['owner_id' => $user->id, 'owner_type' => $user->getResourceName()]);
            }else{
                return ['created' => false, 'message' => 'The user does not exist'];
            }

        }else if(isset($data['store_id'])) {

            $store = Store::find($data['store_id']);
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to add addresses'];
                $data = array_merge($data, ['owner_id' => $store->id, 'owner_type' => $store->getResourceName()]);
            }else{
                return ['created' => false, 'message' => 'The store does not exist'];
            }

        }else if(isset($data['customer_id'])) {

            $customer = Customer::with(['store'])->find($data['customer_id']);

            if($customer) {
                $store = $customer->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to add addresses'];
                    $data = array_merge($data, ['owner_id' => $customer->id, 'owner_type' => $customer->getResourceName()]);
                }else{
                    return ['created' => false, 'message' => 'The store does not exist'];
                }
            }else{
                return ['created' => false, 'message' => 'The customer does not exist'];
            }

        }else if(isset($data['delivery_method_id'])) {

            $deliveryMethod = DeliveryMethod::with(['store'])->find($data['delivery_method_id']);

            if($deliveryMethod) {
                $store = $deliveryMethod->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to add addresses'];
                    $data = array_merge($data, ['owner_id' => $deliveryMethod->id, 'owner_type' => $deliveryMethod->getResourceName()]);
                }else{
                    return ['created' => false, 'message' => 'The store does not exist'];
                }
            }else{
                return ['created' => false, 'message' => 'The delivery method does not exist'];
            }

        }

        $address = Address::create($data);
        return $this->showCreatedResource($address);
    }

    /**
     * Delete addresses.
     *
     * @param array $addressIds
     * @return array
     */
    public function deleteAddresses(array $addressIds): array
    {
        if($this->getQuery() == null) {
            if($this->isAuthourized()) {
                $this->setQuery(Address::query());
            }else {
                $this->setQuery(request()->current_user->addresses());
            }
        }

        $addresses = $this->getAddressesByIds($addressIds);

        if($totalAddresses = $addresses->count()) {

            foreach($addresses as $address) {
                $address->delete();
            }

            return ['deleted' => true, 'message' => $totalAddresses . ($totalAddresses == 1 ? ' address': ' addresses') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No addresses deleted'];
        }
    }

    /**
     * Show address.
     *
     * @param Address|string|null $addressId
     * @return Address|array|null
     */
    public function showAddress(Address|string|null $addressId = null): Address|array|null
    {
        if(($address = $addressId) instanceof Address) {
            $address = $this->applyEagerLoadingOnModel($address);
        }else {
            $query = $this->getQuery() ?? Address::query();
            if($addressId) $query = $query->where('addresses.id', $addressId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $address = $this->query->first();
        }

        return $this->showResourceExistence($address);
    }

    /**
     * Update address.
     *
     * @param string $addressId
     * @param array $data
     * @return Address|array
     */
    public function updateAddress(string $addressId, array $data): Address|array
    {
        $address = Address::with(['owner'])->find($addressId);

        if($address) {

            $owner = $address->owner;

            if(!($isAuthourized = $this->isAuthourized())) {
                if(($user = $owner) instanceof User) {
                    $isAuthourized = request()->current_user->id == $user->id;
                }else if(($store = $owner) instanceof Store) {
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                }else if(($customer = $owner) instanceof Customer) {
                    $store = $customer->store;
                    if($store) {
                        $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    }else{
                        return ['update' => false, 'message' => 'The store does not exist'];
                    }
                }else if(($deliveryMethod = $owner) instanceof DeliveryMethod) {
                    $store = $deliveryMethod->store;
                    if($store) {
                        $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    }else{
                        return ['update' => false, 'message' => 'The store does not exist'];
                    }
                }
            }

            if ($isAuthourized) {

                $address->update($data);
                return $this->showUpdatedResource($address);

            }else{
                return ['updated' => false, 'message' => 'You do not have permission to update this address'];
            }

        }else{
            return ['updated' => false, 'message' => 'This address does not exist'];
        }
    }

    /**
     * Delete address.
     *
     * @param string $addressId
     * @return array
     */
    public function deleteAddress(string $addressId): array
    {
        $address = Address::with(['owner'])->find($addressId);

        if($address) {

            $owner = $address->owner;

            if(!($isAuthourized = $this->isAuthourized())) {
                if(($user = $owner) instanceof User) {
                    $isAuthourized = request()->current_user->id == $user->id;
                }else if(($store = $owner) instanceof Store) {
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                }else if(($customer = $owner) instanceof Customer) {
                    $store = $customer->store;
                    if($store) {
                        $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    }else{
                        return ['deleted' => false, 'message' => 'The store does not exist'];
                    }
                }else if(($deliveryMethod = $owner) instanceof DeliveryMethod) {
                    $store = $deliveryMethod->store;
                    if($store) {
                        $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    }else{
                        return ['deleted' => false, 'message' => 'The store does not exist'];
                    }
                }
            }

            if ($isAuthourized) {

                $deleted = $address->delete();

                if ($deleted) {
                    return ['deleted' => true, 'message' => 'Address deleted'];
                }else{
                    return ['deleted' => false, 'message' => 'Address delete unsuccessful'];
                }

            }else{
                return ['deleted' => false, 'message' => 'You do not have permission to delete this address'];
            }

        }else{
            return ['deleted' => false, 'message' => 'This address does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query address by ID.
     *
     * @param Address|string $addressId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryAddressById(Address|string $addressId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('addresses.id', $addressId)->with($relationships);
    }

    /**
     * Get address by ID.
     *
     * @param Address|string $addressId
     * @param array $relationships
     * @return Address|null
     */
    public function getAddressById(Address|string $addressId, array $relationships = []): Address|null
    {
        return $this->queryAddressById($addressId, $relationships)->first();
    }

    /**
     * Query addresses by IDs.
     *
     * @param array<string> $addressId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryAddressesByIds($addressIds): Builder|Relation
    {
        return $this->query->whereIn('addresses.id', $addressIds);
    }

    /**
     * Get addresses by IDs.
     *
     * @param array<string> $addressId
     * @param string $relationships
     * @return Collection
     */
    public function getAddressesByIds($addressIds): Collection
    {
        return $this->queryAddressesByIds($addressIds)->get();
    }
}
