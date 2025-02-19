<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Base\BaseController;
use App\Repositories\StorePaymentMethodRepository;
use App\Http\Requests\Models\StorePaymentMethod\ShowStorePaymentMethodsRequest;
use App\Http\Requests\Models\StorePaymentMethod\CreateStorePaymentMethodRequest;
use App\Http\Requests\Models\StorePaymentMethod\UpdateStorePaymentMethodRequest;
use App\Http\Requests\Models\StorePaymentMethod\DeleteStorePaymentMethodsRequest;
use App\Http\Requests\Models\StorePaymentMethod\UpdateStorePaymentMethodArrangementRequest;

class StorePaymentMethodController extends BaseController
{
    /**
     *  @var StorePaymentMethodRepository
     */
    protected $repository;

    /**
     * StorePaymentMethodController constructor.
     *
     * @param StorePaymentMethodRepository $repository
     */
    public function __construct(StorePaymentMethodRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show store payment methods.
     *
     * @param ShowStorePaymentMethodRequest $request
     * @return JsonResponse
     */
    public function showStorePaymentMethods(ShowStorePaymentMethodsRequest $request): JsonResponse
    {
        if($request->storeId) {
            $request->merge(['store_id' => $request->storeId]);
        }

        return $this->prepareOutput($this->repository->showStorePaymentMethods($request->all()));
    }

    /**
     * Create store payment method.
     *
     * @param CreateStorePaymentMethodRequest $request
     * @return JsonResponse
     */
    public function createStorePaymentMethod(CreateStorePaymentMethodRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createStorePaymentMethod($request->all()));
    }

    /**
     * Delete store payment methods.
     *
     * @param DeleteStorePaymentMethodsRequest $request
     * @return JsonResponse
     */
    public function deleteStorePaymentMethods(DeleteStorePaymentMethodsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteStorePaymentMethods($request->all()));
    }

    /**
     * Update store payment method arrangement.
     *
     * @param UpdateStorePaymentMethodArrangementRequest $request
     * @return JsonResponse
     */
    public function updateStorePaymentMethodArrangement(UpdateStorePaymentMethodArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updateStorePaymentMethodArrangement($request->all()));
    }

    /**
     * Show store payment method.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function showStorePaymentMethod(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStorePaymentMethod($storePaymentMethodId));
    }

    /**
     * Update store payment method.
     *
     * @param UpdateStorePaymentMethodRequest $request
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function updateStorePaymentMethod(UpdateStorePaymentMethodRequest $request, string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateStorePaymentMethod($storePaymentMethodId, $request->all()));
    }

    /**
     * Delete store payment method.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function deleteStorePaymentMethod(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteStorePaymentMethod($storePaymentMethodId));
    }

    /**
     * Show store payment method logo.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function showStorePaymentMethodLogo(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStorePaymentMethodLogo($storePaymentMethodId));
    }

    /**
     * Uplaod store payment method logo.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function uploadStorePaymentMethodLogo(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->uploadStorePaymentMethodLogo($storePaymentMethodId));
    }

    /**
     * Delete store payment method logo.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function deleteStorePaymentMethodLogo(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteStorePaymentMethodLogo($storePaymentMethodId));
    }

    /**
     * Show store payment method photo.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function showStorePaymentMethodPhoto(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStorePaymentMethodPhoto($storePaymentMethodId));
    }

    /**
     * Uplaod store payment method photo.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function uploadStorePaymentMethodPhoto(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->uploadStorePaymentMethodPhoto($storePaymentMethodId));
    }

    /**
     * Delete store payment method photo.
     *
     * @param string $storePaymentMethodId
     * @return JsonResponse
     */
    public function deleteStorePaymentMethodPhoto(string $storePaymentMethodId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteStorePaymentMethodPhoto($storePaymentMethodId));
    }
}
