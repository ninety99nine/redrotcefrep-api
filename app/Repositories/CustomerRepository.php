<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\Customer;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use App\Http\Resources\OrderResources;
use App\Http\Resources\CustomerResources;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\TransactionResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class CustomerRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show customers.
     *
     * @param Store|string|null $storeId
     * @return CustomerResources|array
     */
    public function showCustomers(Store|string|null $storeId = null): CustomerResources|array
    {
        if($this->getQuery() == null) {
            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show customers'];
                $this->setQuery(Customer::latest());
            }else{
                $store = $storeId instanceof Store ? $storeId : Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show customers'];
                    $this->setQuery($store->customers()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create customer.
     *
     * @param array $data
     * @return Customer|array
     */
    public function createCustomer(array $data): Customer|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create customers'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'currency' => $store->currency,
            'store_id' => $storeId
        ]);

        $customer = Customer::create($data);
        return $this->showCreatedResource($customer);
    }

    /**
     * Delete customers.
     *
     * @param array $data
     * @return array
     */
    public function deleteCustomers(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete customers'];
            $this->setQuery(Customer::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete customers'];
                $this->setQuery($store->customers());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $customerIds = $data['customer_ids'];
        $customers = $this->getCustomersByIds($customerIds);

        if($totalCustomers = $customers->count()) {

            foreach($customers as $customer) {
                $customer->delete();
            }

            return ['deleted' => true, 'message' => $totalCustomers . ($totalCustomers == 1 ? ' customer': ' customers') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No customers deleted'];
        }
    }

    /**
     * Show customer.
     *
     * @param string $customerId
     * @return Customer|array|null
     */
    public function showCustomer(string $customerId): Customer|array|null
    {
        $customer = $this->setQuery(Customer::with(['store'])->whereId($customerId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($customer) {
            $store = $customer->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show customer'];
                if(!$this->checkIfHasRelationOnRequest('store')) $customer->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($customer);
    }

    /**
     * Update customer.
     *
     * @param string $customerId
     * @param array $data
     * @return Customer|array
     */
    public function updateCustomer(string $customerId, array $data): Customer|array
    {
        $customer = Customer::with(['store'])->find($customerId);

        if($customer) {
            $store = $customer->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update customer'];
                if(!$this->checkIfHasRelationOnRequest('store')) $customer->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $customer->update($data);
            return $this->showUpdatedResource($customer);
        }else{
            return ['updated' => false, 'message' => 'This customer does not exist'];
        }
    }

    /**
     * Delete customer.
     *
     * @param string $customerId
     * @return array
     */
    public function deleteCustomer(string $customerId): array
    {
        $customer = Customer::with(['store'])->find($customerId);

        if($customer) {
            $store = $customer->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete customer'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $customer->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Customer removed'];
            }else{
                return ['deleted' => false, 'message' => 'Customer removal unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This customer does not exist'];
        }
    }

    /**
     * Show customer orders.
     *
     * @param string $customerId
     * @return OrderResources|array
     */
    public function showCustomerOrders(string $customerId): OrderResources|array
    {
        $customer = Customer::with(['store'])->find($customerId);

        if($customer) {
            $store = $customer->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show customer orders'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getOrderRepository()->setQuery($customer->orders())->showOrders();
        }else{
            return ['message' => 'This customer does not exist'];
        }
    }

    /**
     * Show customer transactions.
     *
     * @param string $customerId
     * @return TransactionResources|array
     */
    public function showCustomerTransactions(string $customerId): TransactionResources|array
    {
        $customer = Customer::with(['store'])->find($customerId);

        if($customer) {
            $store = $customer->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show customer transactions'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getTransactionRepository()->setQuery($customer->transactions())->showTransactions();
        }else{
            return ['message' => 'This customer does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query customer by ID.
     *
     * @param Customer|string $customerId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryCustomerById(Customer|string $customerId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('customers.id', $customerId)->with($relationships);
    }

    /**
     * Get customer by ID.
     *
     * @param Customer|string $customerId
     * @param array $relationships
     * @return Customer|null
     */
    public function getCustomerById(Customer|string $customerId, array $relationships = []): Customer|null
    {
        return $this->queryCustomerById($customerId, $relationships)->first();
    }

    /**
     * Query customers by IDs.
     *
     * @param array<string> $customerId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryCustomersByIds($customerIds): Builder|Relation
    {
        return $this->query->whereIn('customers.id', $customerIds);
    }

    /**
     * Get customers by IDs.
     *
     * @param array<string> $customerId
     * @param string $relationships
     * @return Collection
     */
    public function getCustomersByIds($customerIds): Collection
    {
        return $this->queryCustomersByIds($customerIds)->get();
    }
}
