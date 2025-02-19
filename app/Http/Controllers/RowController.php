<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\RowRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Row\ShowRowsRequest;
use App\Http\Requests\Models\Row\CreateRowRequest;
use App\Http\Requests\Models\Row\UpdateRowRequest;
use App\Http\Requests\Models\Row\DeleteRowsRequest;
use App\Http\Requests\Models\Row\UpdateRowVisibilityRequest;
use App\Http\Requests\Models\Row\UpdateRowArrangementRequest;

class RowController extends BaseController
{
    /**
     *  @var RowRepository
     */
    protected $repository;

    /**
     * RowController constructor.
     *
     * @param RowRepository $repository
     */
    public function __construct(RowRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show rows.
     *
     * @param ShowRowsRequest $request
     * @param string|null $sectionId
     * @return JsonResponse
     */
    public function showRows(ShowRowsRequest $request): JsonResponse
    {
        if($request->sectionId) {
            $request->merge(['section_id' => $request->sectionId]);
        }

        return $this->prepareOutput($this->repository->showRows($request->all()));
    }

    /**
     * Create row.
     *
     * @param CreateRowRequest $request
     * @return JsonResponse
     */
    public function createRow(CreateRowRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createRow($request->all()));
    }

    /**
     * Delete rows.
     *
     * @param DeleteRowsRequest $request
     * @return JsonResponse
     */
    public function deleteRows(DeleteRowsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteRows($request->all()));
    }

    /**
     * Update row visibility.
     *
     * @param UpdateRowVisibilityRequest $request
     * @return JsonResponse
     */
    public function updateRowVisibility(UpdateRowVisibilityRequest $request)
    {
        return $this->prepareOutput($this->repository->updateRowVisibility($request->all()));
    }

    /**
     * Update row arrangement.
     *
     * @param UpdateRowArrangementRequest $request
     * @return JsonResponse
     */
    public function updateRowArrangement(UpdateRowArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updateRowArrangement($request->all()));
    }

    /**
     * Show row.
     *
     * @param string $rowId
     * @return JsonResponse
     */
    public function showRow(string $rowId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showRow($rowId));
    }

    /**
     * Update row.
     *
     * @param UpdateRowRequest $request
     * @param string $rowId
     * @return JsonResponse
     */
    public function updateRow(UpdateRowRequest $request, string $rowId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateRow($rowId, $request->all()));
    }

    /**
     * Delete row.
     *
     * @param string $rowId
     * @return JsonResponse
     */
    public function deleteRow(string $rowId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteRow($rowId));
    }
}
