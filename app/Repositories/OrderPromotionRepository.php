<?php

namespace App\Repositories;

use App\Traits\AuthTrait;
use App\Models\OrderPromotion;
use App\Traits\Base\BaseTrait;
use App\Repositories\BaseRepository;
use App\Http\Resources\OrderPromotionResources;

class OrderPromotionRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show order promotions.
     *
     * @return OrderPromotionResources|array
     */
    public function showOrderPromotions(): OrderPromotionResources|array
    {
        if($this->getQuery() == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show order promotions'];
            $this->setQuery(OrderPromotion::query()->latest());
        }

        return $this->getOutput();
    }

    /**
     * Show order promotion.
     *
     * @param OrderPromotion|string|null $orderPromotionId
     * @return OrderPromotion|array|null
     */
    public function showOrderPromotion(OrderPromotion|string|null $orderPromotionId = null): OrderPromotion|array|null
    {
        if(($orderPromotion = $orderPromotionId) instanceof OrderPromotion) {
            $orderPromotion = $this->applyEagerLoadingOnModel($orderPromotion);
        }else {
            $query = $this->getQuery() ?? OrderPromotion::query();
            if($orderPromotionId) $query = $query->where('order_promotions.id', $orderPromotionId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $orderPromotion = $this->query->first();
        }

        return $this->showResourceExistence($orderPromotion);
    }

    /***********************************************
     *            MISCELLANEOUS METHODS           *
     **********************************************/
}
