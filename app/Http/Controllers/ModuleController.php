<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\ModuleRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Module\ShowModulesRequest;
use App\Http\Requests\Models\Module\CreateModuleRequest;
use App\Http\Requests\Models\Module\UpdateModuleRequest;
use App\Http\Requests\Models\Module\DeleteModulesRequest;
use App\Http\Requests\Models\Module\UpdateModuleVisibilityRequest;
use App\Http\Requests\Models\Module\UpdateModuleArrangementRequest;

class ModuleController extends BaseController
{
    /**
     *  @var ModuleRepository
     */
    protected $repository;

    /**
     * ModuleController constructor.
     *
     * @param ModuleRepository $repository
     */
    public function __construct(ModuleRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show modules.
     *
     * @param ShowModulesRequest $request
     * @param string|null $columnId
     * @return JsonResponse
     */
    public function showModules(ShowModulesRequest $request): JsonResponse
    {
        if($request->columnId) {
            $request->merge(['column_id' => $request->columnId]);
        }

        return $this->prepareOutput($this->repository->showModules($request->all()));
    }

    /**
     * Create module.
     *
     * @param CreateModuleRequest $request
     * @return JsonResponse
     */
    public function createModule(CreateModuleRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createModule($request->all()));
    }

    /**
     * Delete modules.
     *
     * @param DeleteModulesRequest $request
     * @return JsonResponse
     */
    public function deleteModules(DeleteModulesRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteModules($request->all()));
    }

    /**
     * Update module visibility.
     *
     * @param UpdateModuleVisibilityRequest $request
     * @return JsonResponse
     */
    public function updateModuleVisibility(UpdateModuleVisibilityRequest $request)
    {
        return $this->prepareOutput($this->repository->updateModuleVisibility($request->all()));
    }

    /**
     * Update module arrangement.
     *
     * @param UpdateModuleArrangementRequest $request
     * @return JsonResponse
     */
    public function updateModuleArrangement(UpdateModuleArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updateModuleArrangement($request->all()));
    }

    /**
     * Show module.
     *
     * @param string $moduleId
     * @return JsonResponse
     */
    public function showModule(string $moduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showModule($moduleId));
    }

    /**
     * Update module.
     *
     * @param UpdateModuleRequest $request
     * @param string $moduleId
     * @return JsonResponse
     */
    public function updateModule(UpdateModuleRequest $request, string $moduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateModule($moduleId, $request->all()));
    }

    /**
     * Delete module.
     *
     * @param string $moduleId
     * @return JsonResponse
     */
    public function deleteModule(string $moduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteModule($moduleId));
    }

    /**
     * Show module media files.
     *
     * @param string $moduleId
     * @return JsonResponse
     */
    public function showModuleMediaFiles(string $moduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showModuleMediaFiles($moduleId));
    }

    /**
     * Create module media file(s).
     *
     * @param string $moduleId
     * @return JsonResponse
     */
    public function createModuleMediaFile(string $moduleId): JsonResponse
    {
        return $this->prepareOutput($this->repository->createModuleMediaFile($moduleId));
    }

    /**
     * Show module media file.
     *
     * @param string $moduleId
     * @param string $mediaFileId
     * @return JsonResponse
     */
    public function showModuleMediaFile(string $moduleId, string $mediaFileId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showModuleMediaFile($moduleId, $mediaFileId));
    }

    /**
     * Update module media file.
     *
     * @param string $moduleId
     * @param string $mediaFileId
     * @return JsonResponse
     */
    public function updateModuleMediaFile(string $moduleId, string $mediaFileId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateModuleMediaFile($moduleId, $mediaFileId));
    }

    /**
     * Delete module media file.
     *
     * @param string $moduleId
     * @param string $mediaFileId
     * @return JsonResponse
     */
    public function deleteModuleMediaFile(string $moduleId, string $mediaFileId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteModuleMediaFile($moduleId, $mediaFileId));
    }
}
