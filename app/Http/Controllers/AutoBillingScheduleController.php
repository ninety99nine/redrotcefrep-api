<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Base\BaseController;
use App\Repositories\AutoBillingScheduleRepository;
use App\Http\Requests\Models\AutoBillingSchedule\ShowAutoBillingSchedulesRequest;
use App\Http\Requests\Models\AutoBillingSchedule\CreateAutoBillingScheduleRequest;
use App\Http\Requests\Models\AutoBillingSchedule\UpdateAutoBillingScheduleRequest;
use App\Http\Requests\Models\AutoBillingSchedule\DeleteAutoBillingSchedulesRequest;

class AutoBillingScheduleController extends BaseController
{
    /**
     *  @var AutoBillingScheduleRepository
     */
    protected $repository;

    /**
     * AutoBillingScheduleController constructor.
     *
     * @param AutoBillingScheduleRepository $repository
     */
    public function __construct(AutoBillingScheduleRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show auto billing schedules.
     *
     * @param ShowAutoBillingScheduleRequest $request
     * @return JsonResponse
     */
    public function showAutoBillingSchedules(ShowAutoBillingSchedulesRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showAutoBillingSchedules($request->all()));
    }

    /**
     * Create auto billing schedule.
     *
     * @param CreateAutoBillingScheduleRequest $request
     * @return JsonResponse
     */
    public function createAutoBillingSchedule(CreateAutoBillingScheduleRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createAutoBillingSchedule($request->all()));
    }

    /**
     * Delete auto billing schedules.
     *
     * @param DeleteAutoBillingSchedulesRequest $request
     * @return JsonResponse
     */
    public function deleteAutoBillingSchedules(DeleteAutoBillingSchedulesRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteAutoBillingSchedules($request->input('auto_billing_schedule_ids')));
    }

    /**
     * Show auto billing schedule.
     *
     * @param string $autoBillingScheduleId
     * @return JsonResponse
     */
    public function showAutoBillingSchedule(string $autoBillingScheduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showAutoBillingSchedule($autoBillingScheduleId));
    }

    /**
     * Update auto billing schedule.
     *
     * @param UpdateAutoBillingScheduleRequest $request
     * @param string $autoBillingScheduleId
     * @return JsonResponse
     */
    public function updateAutoBillingSchedule(UpdateAutoBillingScheduleRequest $request, string $autoBillingScheduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateAutoBillingSchedule($autoBillingScheduleId, $request->all()));
    }

    /**
     * Delete auto billing schedule.
     *
     * @param string $autoBillingScheduleId
     * @return JsonResponse
     */
    public function deleteAutoBillingSchedule(string $autoBillingScheduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteAutoBillingSchedule($autoBillingScheduleId));
    }
}
