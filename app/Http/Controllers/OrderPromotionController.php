<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\OrderPromotionRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\OrderPromotion\ShowOrderPromotionsRequest;

class OrderPromotionController extends BaseController
{
    /**
     *  @var OrderPromotionRepository
     */
    protected $repository;

    /**
     * OrderPromotionController constructor.
     *
     * @param OrderPromotionRepository $repository
     */
    public function __construct(OrderPromotionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show order promotions.
     *
     * @param ShowOrderPromotionsRequest $request
     * @param string|null $storeId
     * @return JsonResponse
     */
    public function showOrderPromotions(ShowOrderPromotionsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderPromotions());
    }

    /**
     * Show order promotion.
     *
     * @param string $orderPromotionId
     * @return JsonResponse
     */
    public function showOrderPromotion(string $orderPromotionId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderPromotion($orderPromotionId));
    }
}
