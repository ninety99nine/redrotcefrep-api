<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\ColumnRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Column\ShowColumnsRequest;
use App\Http\Requests\Models\Column\CreateColumnRequest;
use App\Http\Requests\Models\Column\UpdateColumnRequest;
use App\Http\Requests\Models\Column\DeleteColumnsRequest;
use App\Http\Requests\Models\Column\UpdateColumnVisibilityRequest;
use App\Http\Requests\Models\Column\UpdateColumnArrangementRequest;

class ColumnController extends BaseController
{
    /**
     *  @var ColumnRepository
     */
    protected $repository;

    /**
     * ColumnController constructor.
     *
     * @param ColumnRepository $repository
     */
    public function __construct(ColumnRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show columns.
     *
     * @param ShowColumnsRequest $request
     * @param string|null $rowId
     * @return JsonResponse
     */
    public function showColumns(ShowColumnsRequest $request): JsonResponse
    {
        if($request->rowId) {
            $request->merge(['row_id' => $request->rowId]);
        }

        return $this->prepareOutput($this->repository->showColumns($request->all()));
    }

    /**
     * Create column.
     *
     * @param CreateColumnRequest $request
     * @return JsonResponse
     */
    public function createColumn(CreateColumnRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createColumn($request->all()));
    }

    /**
     * Delete columns.
     *
     * @param DeleteColumnsRequest $request
     * @return JsonResponse
     */
    public function deleteColumns(DeleteColumnsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteColumns($request->all()));
    }

    /**
     * Update column visibility.
     *
     * @param UpdateColumnVisibilityRequest $request
     * @return JsonResponse
     */
    public function updateColumnVisibility(UpdateColumnVisibilityRequest $request)
    {
        return $this->prepareOutput($this->repository->updateColumnVisibility($request->all()));
    }

    /**
     * Update column arrangement.
     *
     * @param UpdateColumnArrangementRequest $request
     * @return JsonResponse
     */
    public function updateColumnArrangement(UpdateColumnArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updateColumnArrangement($request->all()));
    }

    /**
     * Show column.
     *
     * @param string $columnId
     * @return JsonResponse
     */
    public function showColumn(string $columnId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showColumn($columnId));
    }

    /**
     * Update column.
     *
     * @param UpdateColumnRequest $request
     * @param string $columnId
     * @return JsonResponse
     */
    public function updateColumn(UpdateColumnRequest $request, string $columnId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateColumn($columnId, $request->all()));
    }

    /**
     * Delete column.
     *
     * @param string $columnId
     * @return JsonResponse
     */
    public function deleteColumn(string $columnId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteColumn($columnId));
    }
}
