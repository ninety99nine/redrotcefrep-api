<?php

namespace App\Repositories;

use App\Models\Store;
use App\Traits\AuthTrait;
use App\Models\StoreQuota;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\StoreQuotaResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class StoreQuotaRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show store quotas.
     *
     * @return StoreQuotaResources|array
     */
    public function showStoreQuotas(array $data = []): StoreQuotaResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;

            if($storeId) {
                $store = $storeId instanceof Store ? $storeId : Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show store quotas'];
                    $this->setQuery(StoreQuota::query()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }else {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show store quotas'];
                $this->setQuery(StoreQuota::query()->latest());
            }
        }

        return $this->getOutput();
    }

    /**
     * Create store quota.
     *
     * @param array $data
     * @return StoreQuota|array
     */
    public function createStoreQuota(array $data): StoreQuota|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create store quotas'];

        $storeQuotaExists = StoreQuota::whereStoreId($data['store_id'])->exists();
        if($storeQuotaExists) return ['created' => false, 'message' => 'The store quota already exists for this store'];

        $storeQuota = StoreQuota::create($data);
        return $this->showCreatedResource($storeQuota);
    }

    /**
     * Delete store quotas.
     *
     * @param array $storeQuotaIds
     * @return array
     */
    public function deleteStoreQuotas(array $storeQuotaIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete store quotas'];

        $storeQuotas = $this->setQuery(StoreQuota::query())->getStoreQuotasByIds($storeQuotaIds);

        if($totalStoreQuotas = $storeQuotas->count()) {

            foreach($storeQuotas as $storeQuota) {
                $storeQuota->delete();
            }

            return ['deleted' => true, 'message' => $totalStoreQuotas  .($totalStoreQuotas  == 1 ? ' store quota': ' store quotas') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No store quotas deleted'];
        }
    }

    /**
     * Show store quota.
     *
     * @param StoreQuota|string|null $storeQuotaId
     * @return StoreQuota|array|null
     */
    public function showStoreQuota(StoreQuota|string|null $storeQuotaId = null): StoreQuota|array|null
    {
        if(($storeQuota = $storeQuotaId) instanceof StoreQuota) {
            $storeQuota = $this->applyEagerLoadingOnModel($storeQuota);
        }else {
            $query = $this->getQuery() ?? StoreQuota::query();
            if($storeQuotaId) $query = $query->where('store_quotas.id', $storeQuotaId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $storeQuota = $this->query->first();
        }

        return $this->showResourceExistence($storeQuota);
    }

    /**
     * Update store quota.
     *
     * @param StoreQuota|string $storeQuotaId
     * @param array $data
     * @return StoreQuota|array
     */
    public function updateStoreQuota(StoreQuota|string $storeQuotaId, array $data): StoreQuota|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update store quota'];

        $storeQuota = $storeQuotaId instanceof StoreQuota ? $storeQuotaId : StoreQuota::find($storeQuotaId);

        if($storeQuota) {

            $storeQuota->update($data);
            return $this->showUpdatedResource($storeQuota);

        }else{
            return ['updated' => false, 'message' => 'This store quota does not exist'];
        }
    }

    /**
     * Delete store quota.
     *
     * @param StoreQuota|string $storeQuotaId
     * @return array
     */
    public function deleteStoreQuota(StoreQuota|string $storeQuotaId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete store quota'];

        $storeQuota = $storeQuotaId instanceof StoreQuota ? $storeQuotaId : StoreQuota::find($storeQuotaId);

        if($storeQuota) {
            $deleted = $storeQuota->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Store quota deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Store quota delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This store quota does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query store quota by ID.
     *
     * @param string $storeQuotaId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryStoreQuotaById(string $storeQuotaId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('store_quotas.id', $storeQuotaId)->with($relationships);
    }

    /**
     * Get store quota by ID.
     *
     * @param string $storeQuotaId
     * @param array $relationships
     * @return StoreQuota|null
     */
    public function getStoreQuotaById(string $storeQuotaId, array $relationships = []): StoreQuota|null
    {
        return $this->queryStoreQuotaById($storeQuotaId, $relationships)->first();
    }

    /**
     * Query store quotas by IDs.
     *
     * @param array<string> $storeQuotaId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryStoreQuotasByIds($storeQuotaIds): Builder|Relation
    {
        return $this->query->whereIn('store_quotas.id', $storeQuotaIds);
    }

    /**
     * Get store quotas by IDs.
     *
     * @param array<string> $storeQuotaId
     * @param string $relationships
     * @return Collection
     */
    public function getStoreQuotasByIds($storeQuotaIds): Collection
    {
        return $this->queryStoreQuotasByIds($storeQuotaIds)->get();
    }
}
