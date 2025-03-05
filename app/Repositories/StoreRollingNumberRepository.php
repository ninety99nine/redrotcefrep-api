<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\StoreRollingNumber;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use App\Http\Resources\StoreRollingNumberResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class StoreRollingNumberRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show store rolling numbers.
     *
     * @param Store|string|null $storeId
     * @return StoreRollingNumberResources|array
     */
    public function showStoreRollingNumbers(Store|string|null $storeId = null): StoreRollingNumberResources|array
    {
        if($this->getQuery() == null) {
            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show store rolling numbers'];
                $this->setQuery(StoreRollingNumber::latest());
            }else{
                $store = $storeId instanceof Store ? $storeId : Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show store rolling numbers'];
                    $this->setQuery($store->storeRollingNumbers()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create store rolling number.
     *
     * @param array $data
     * @return StoreRollingNumber|array
     */
    public function createStoreRollingNumber(array $data): StoreRollingNumber|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create store rolling numbers'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'store_id' => $storeId
        ]);

        $storeRollingNumber = StoreRollingNumber::create($data);
        return $this->showCreatedResource($storeRollingNumber);
    }

    /**
     * Delete store rolling numbers.
     *
     * @param array $data
     * @return array
     */
    public function deleteStoreRollingNumbers(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete store rolling numbers'];
            $this->setQuery(StoreRollingNumber::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete store rolling numbers'];
                $this->setQuery($store->storeRollingNumbers());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $storeRollingNumberIds = $data['store_rolling_number_ids'];
        $storeRollingNumbers = $this->getStoreRollingNumbersByIds($storeRollingNumberIds);

        if($totalStoreRollingNumbers = $storeRollingNumbers->count()) {

            foreach($storeRollingNumbers as $storeRollingNumber) {
                $storeRollingNumber->delete();
            }

            return ['deleted' => true, 'message' => $totalStoreRollingNumbers . ($totalStoreRollingNumbers == 1 ? ' store rolling number': ' store rolling numbers') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No store rolling numbers deleted'];
        }
    }

    /**
     * Show store rolling number.
     *
     * @param string $storeRollingNumberId
     * @return StoreRollingNumber|array|null
     */
    public function showStoreRollingNumber(string $storeRollingNumberId): StoreRollingNumber|array|null
    {
        $storeRollingNumber = $this->setQuery(StoreRollingNumber::with(['store'])->whereId($storeRollingNumberId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($storeRollingNumber) {
            $store = $storeRollingNumber->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show store rolling number'];
                if(!$this->checkIfHasRelationOnRequest('store')) $storeRollingNumber->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($storeRollingNumber);
    }

    /**
     * Update store rolling number.
     *
     * @param string $storeRollingNumberId
     * @param array $data
     * @return StoreRollingNumber|array
     */
    public function updateStoreRollingNumber(string $storeRollingNumberId, array $data): StoreRollingNumber|array
    {
        $storeRollingNumber = StoreRollingNumber::with(['store'])->find($storeRollingNumberId);

        if($storeRollingNumber) {
            $store = $storeRollingNumber->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update store rolling number'];
                if(!$this->checkIfHasRelationOnRequest('store')) $storeRollingNumber->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $storeRollingNumber->update($data);
            return $this->showUpdatedResource($storeRollingNumber);

        }else{
            return ['updated' => false, 'message' => 'This store rolling number does not exist'];
        }
    }

    /**
     * Delete store rolling number.
     *
     * @param string $storeRollingNumberId
     * @return array
     */
    public function deleteStoreRollingNumber(string $storeRollingNumberId): array
    {
        $storeRollingNumber = StoreRollingNumber::with(['store'])->find($storeRollingNumberId);

        if($storeRollingNumber) {
            $store = $storeRollingNumber->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete store rolling number'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $storeRollingNumber->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Store rolling number deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Store rolling number delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This store rolling number does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query store rolling number by ID.
     *
     * @param StoreRollingNumber|string $storeRollingNumberId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryStoreRollingNumberById(StoreRollingNumber|string $storeRollingNumberId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('store_rolling_numbers.id', $storeRollingNumberId)->with($relationships);
    }

    /**
     * Get store rolling number by ID.
     *
     * @param StoreRollingNumber|string $storeRollingNumberId
     * @param array $relationships
     * @return StoreRollingNumber|null
     */
    public function getStoreRollingNumberById(StoreRollingNumber|string $storeRollingNumberId, array $relationships = []): StoreRollingNumber|null
    {
        return $this->queryStoreRollingNumberById($storeRollingNumberId, $relationships)->first();
    }

    /**
     * Query store rolling numbers by IDs.
     *
     * @param array<string> $storeRollingNumberId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryStoreRollingNumbersByIds($storeRollingNumberIds): Builder|Relation
    {
        return $this->query->whereIn('store_rolling_numbers.id', $storeRollingNumberIds);
    }

    /**
     * Get store rolling numbers by IDs.
     *
     * @param array<string> $storeRollingNumberId
     * @param string $relationships
     * @return Collection
     */
    public function getStoreRollingNumbersByIds($storeRollingNumberIds): Collection
    {
        return $this->queryStoreRollingNumbersByIds($storeRollingNumberIds)->get();
    }
}
