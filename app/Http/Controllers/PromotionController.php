<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\PromotionRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Promotion\ShowPromotionsRequest;
use App\Http\Requests\Models\Promotion\CreatePromotionRequest;
use App\Http\Requests\Models\Promotion\UpdatePromotionRequest;
use App\Http\Requests\Models\Promotion\DeletePromotionsRequest;

class PromotionController extends BaseController
{
    /**
     *  @var PromotionRepository
     */
    protected $repository;

    /**
     * PromotionController constructor.
     *
     * @param PromotionRepository $repository
     */
    public function __construct(PromotionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show promotions.
     *
     * @param ShowPromotionsRequest $request
     * @param string|null $storeId
     * @return JsonResponse
     */
    public function showPromotions(ShowPromotionsRequest $request, string|null $storeId = null): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPromotions($storeId ?? $request->input('store_id')));
    }

    /**
     * Create promotion.
     *
     * @param CreatePromotionRequest $request
     * @return JsonResponse
     */
    public function createPromotion(CreatePromotionRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createPromotion($request->all()));
    }

    /**
     * Delete promotions.
     *
     * @param DeletePromotionsRequest $request
     * @return JsonResponse
     */
    public function deletePromotions(DeletePromotionsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deletePromotions($request->all()));
    }

    /**
     * Show promotion.
     *
     * @param string $promotionId
     * @return JsonResponse
     */
    public function showPromotion(string $promotionId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPromotion($promotionId));
    }

    /**
     * Update promotion.
     *
     * @param UpdatePromotionRequest $request
     * @param string $promotionId
     * @return JsonResponse
     */
    public function updatePromotion(UpdatePromotionRequest $request, string $promotionId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updatePromotion($promotionId, $request->all()));
    }

    /**
     * Delete promotion.
     *
     * @param string $promotionId
     * @return JsonResponse
     */
    public function deletePromotion(string $promotionId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deletePromotion($promotionId));
    }
}
