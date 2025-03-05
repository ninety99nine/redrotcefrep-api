<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\CourierRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Courier\ShowCouriersRequest;
use App\Http\Requests\Models\Courier\CreateCourierRequest;
use App\Http\Requests\Models\Courier\UpdateCourierRequest;
use App\Http\Requests\Models\Courier\DeleteCouriersRequest;
use App\Http\Requests\Models\Courier\UpdateCourierArrangementRequest;

class CourierController extends BaseController
{
    /**
     *  @var CourierRepository
     */
    protected $repository;

    /**
     * CourierController constructor.
     *
     * @param CourierRepository $repository
     */
    public function __construct(CourierRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show couriers.
     *
     * @param ShowCourierRequest $request
     * @return JsonResponse
     */
    public function showCouriers(ShowCouriersRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showCouriers($request->all()));
    }

    /**
     * Create courier.
     *
     * @param CreateCourierRequest $request
     * @return JsonResponse
     */
    public function createCourier(CreateCourierRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createCourier($request->all()));
    }

    /**
     * Delete couriers.
     *
     * @param DeleteCouriersRequest $request
     * @return JsonResponse
     */
    public function deleteCouriers(DeleteCouriersRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteCouriers($request->input('courier_ids')));
    }

    /**
     * Update courier arrangement.
     *
     * @param UpdateCourierArrangementRequest $request
     * @return JsonResponse
     */
    public function updateCourierArrangement(UpdateCourierArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updateCourierArrangement($request->all()));
    }

    /**
     * Show courier.
     *
     * @param string $courierId
     * @return JsonResponse
     */
    public function showCourier(string $courierId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showCourier($courierId));
    }

    /**
     * Update courier.
     *
     * @param UpdateCourierRequest $request
     * @param string $courierId
     * @return JsonResponse
     */
    public function updateCourier(UpdateCourierRequest $request, string $courierId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateCourier($courierId, $request->all()));
    }

    /**
     * Delete courier.
     *
     * @param string $courierId
     * @return JsonResponse
     */
    public function deleteCourier(string $courierId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteCourier($courierId));
    }
}
