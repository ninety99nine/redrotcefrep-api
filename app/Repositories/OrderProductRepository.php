<?php

namespace App\Repositories;

use App\Traits\AuthTrait;
use App\Models\OrderProduct;
use App\Traits\Base\BaseTrait;
use App\Repositories\BaseRepository;
use App\Http\Resources\OrderProductResources;

class OrderProductRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show order products.
     *
     * @return OrderProductResources|array
     */
    public function showOrderProducts(): OrderProductResources|array
    {
        if($this->getQuery() == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show order products'];
            $this->setQuery(OrderProduct::query()->latest());
        }

        return $this->getOutput();
    }

    /**
     * Show order product.
     *
     * @param OrderProduct|string|null $orderProductId
     * @return OrderProduct|array|null
     */
    public function showOrderProduct(OrderProduct|string|null $orderProductId = null): OrderProduct|array|null
    {
        if(($orderProduct = $orderProductId) instanceof OrderProduct) {
            $orderProduct = $this->applyEagerLoadingOnModel($orderProduct);
        }else {
            $query = $this->getQuery() ?? OrderProduct::query();
            if($orderProductId) $query = $query->where('order_products.id', $orderProductId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $orderProduct = $this->query->first();
        }

        return $this->showResourceExistence($orderProduct);
    }

    /***********************************************
     *            MISCELLANEOUS METHODS           *
     **********************************************/
}
