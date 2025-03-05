<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\OrderProductRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\OrderProduct\ShowOrderProductsRequest;

class OrderProductController extends BaseController
{
    /**
     *  @var OrderProductRepository
     */
    protected $repository;

    /**
     * OrderProductController constructor.
     *
     * @param OrderProductRepository $repository
     */
    public function __construct(OrderProductRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show order products.
     *
     * @param ShowOrderProductsRequest $request
     * @param string|null $storeId
     * @return JsonResponse
     */
    public function showOrderProducts(ShowOrderProductsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderProducts());
    }

    /**
     * Show order product.
     *
     * @param string $orderProductId
     * @return JsonResponse
     */
    public function showOrderProduct(string $orderProductId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderProduct($orderProductId));
    }
}
