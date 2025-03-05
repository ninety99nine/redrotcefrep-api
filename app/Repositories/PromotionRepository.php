<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\Promotion;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use App\Http\Resources\PromotionResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class PromotionRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show promotions.
     *
     * @param Store|string|null $storeId
     * @return PromotionResources|array
     */
    public function showPromotions(Store|string|null $storeId = null): PromotionResources|array
    {
        if($this->getQuery() == null) {
            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show promotions'];
                $this->setQuery(Promotion::latest());
            }else{
                $store = $storeId instanceof Store ? $storeId : Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show promotions'];
                    $this->setQuery($store->promotions()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create promotion.
     *
     * @param array $data
     * @return Promotion|array
     */
    public function createPromotion(array $data): Promotion|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create promotions'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'currency' => $store->currency,
            'store_id' => $storeId
        ]);

        $promotion = Promotion::create($data);
        return $this->showCreatedResource($promotion);
    }

    /**
     * Delete promotions.
     *
     * @param array $data
     * @return array
     */
    public function deletePromotions(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete promotions'];
            $this->setQuery(Promotion::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete promotions'];
                $this->setQuery($store->promotions());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $promotionIds = $data['promotion_ids'];
        $promotions = $this->getPromotionsByIds($promotionIds);

        if($totalPromotions = $promotions->count()) {

            foreach($promotions as $promotion) {
                $promotion->delete();
            }

            return ['deleted' => true, 'message' => $totalPromotions . ($totalPromotions == 1 ? ' promotion': ' promotions') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No promotions deleted'];
        }
    }

    /**
     * Show promotion.
     *
     * @param string $promotionId
     * @return Promotion|array|null
     */
    public function showPromotion(string $promotionId): Promotion|array|null
    {
        $promotion = $this->setQuery(Promotion::with(['store'])->whereId($promotionId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($promotion) {
            $store = $promotion->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show promotion'];
                if(!$this->checkIfHasRelationOnRequest('store')) $promotion->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($promotion);
    }

    /**
     * Update promotion.
     *
     * @param string $promotionId
     * @param array $data
     * @return Promotion|array
     */
    public function updatePromotion(string $promotionId, array $data): Promotion|array
    {
        $promotion = Promotion::with(['store'])->find($promotionId);

        if($promotion) {
            $store = $promotion->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update promotion'];
                if(!$this->checkIfHasRelationOnRequest('store')) $promotion->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $promotion->update($data);
            return $this->showUpdatedResource($promotion);

        }else{
            return ['updated' => false, 'message' => 'This promotion does not exist'];
        }
    }

    /**
     * Delete promotion.
     *
     * @param string $promotionId
     * @return array
     */
    public function deletePromotion(string $promotionId): array
    {
        $promotion = Promotion::with(['store'])->find($promotionId);

        if($promotion) {
            $store = $promotion->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete promotion'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $promotion->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Promotion deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Promotion delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This promotion does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query promotion by ID.
     *
     * @param Promotion|string $promotionId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryPromotionById(Promotion|string $promotionId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('promotions.id', $promotionId)->with($relationships);
    }

    /**
     * Get promotion by ID.
     *
     * @param Promotion|string $promotionId
     * @param array $relationships
     * @return Promotion|null
     */
    public function getPromotionById(Promotion|string $promotionId, array $relationships = []): Promotion|null
    {
        return $this->queryPromotionById($promotionId, $relationships)->first();
    }

    /**
     * Query promotions by IDs.
     *
     * @param array<string> $promotionId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryPromotionsByIds($promotionIds): Builder|Relation
    {
        return $this->query->whereIn('promotions.id', $promotionIds);
    }

    /**
     * Get promotions by IDs.
     *
     * @param array<string> $promotionId
     * @param string $relationships
     * @return Collection
     */
    public function getPromotionsByIds($promotionIds): Collection
    {
        return $this->queryPromotionsByIds($promotionIds)->get();
    }
}
