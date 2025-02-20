<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Repositories\PricingPlanRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\PricingPlan\PayPricingPlanRequest;
use App\Http\Requests\Models\PricingPlan\ShowPricingPlansRequest;
use App\Http\Requests\Models\PricingPlan\CreatePricingPlanRequest;
use App\Http\Requests\Models\PricingPlan\UpdatePricingPlanRequest;
use App\Http\Requests\Models\PricingPlan\DeletePricingPlansRequest;

class PricingPlanController extends BaseController
{
    /**
     *  @var PricingPlanRepository
     */
    protected $repository;

    /**
     * PricingPlanController constructor.
     *
     * @param PricingPlanRepository $repository
     */
    public function __construct(PricingPlanRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show pricing plans.
     *
     * @param ShowPricingPlansRequest $request
     * @return JsonResponse
     */
    public function showPricingPlans(ShowPricingPlansRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPricingPlans($request->all()));
    }

    /**
     * Create pricing plan.
     *
     * @param CreatePricingPlanRequest $request
     * @return JsonResponse
     */
    public function createPricingPlan(CreatePricingPlanRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createPricingPlan($request->all()));
    }

    /**
     * Delete pricing plans.
     *
     * @param DeletePricingPlansRequest $request
     * @return JsonResponse
     */
    public function deletePricingPlans(DeletePricingPlansRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deletePricingPlans($request->input('pricing_plan_ids')));
    }

    /**
     * Show pricing plan.
     *
     * @param string $pricingPlanId
     * @return JsonResponse
     */
    public function showPricingPlan(string $pricingPlanId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPricingPlan($pricingPlanId));
    }

    /**
     * Update pricing plan.
     *
     * @param UpdatePricingPlanRequest $request
     * @param string $pricingPlanId
     * @return JsonResponse
     */
    public function updatePricingPlan(UpdatePricingPlanRequest $request, string $pricingPlanId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updatePricingPlan($pricingPlanId, $request->all()));
    }

    /**
     * Delete pricing plan.
     *
     * @param string $pricingPlanId
     * @return JsonResponse
     */
    public function deletePricingPlan(string $pricingPlanId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deletePricingPlan($pricingPlanId));
    }

    /**
     * Show pricing plan payment methods.
     *
     * @param string $pricingPlanId
     * @return JsonResponse
     */
    public function showPricingPlanPaymentMethods(string $pricingPlanId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPricingPlanPaymentMethods($pricingPlanId));
    }

    /**
     * Pay pricing plan.
     *
     * @param PayPricingPlanRequest $request
     * @param string $pricingPlanId
     * @return JsonResponse
     */
    public function payPricingPlan(PayPricingPlanRequest $request, string $pricingPlanId): JsonResponse
    {
        return $this->prepareOutput($this->repository->payPricingPlan($pricingPlanId, $request->all()));
    }

    /**
     * Verify pricing plan payment.
     *
     * @param string $pricingPlanId
     * @param string $transactionId
     * @return JsonResponse|View
     */
    public function verifyPricingPlanPayment(string $pricingPlanId, string $transactionId): JsonResponse|View
    {
        return $this->prepareOutput($this->repository->verifyPricingPlanPayment($pricingPlanId, $transactionId));
    }
}
