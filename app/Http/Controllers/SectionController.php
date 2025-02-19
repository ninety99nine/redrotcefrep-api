<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\SectionRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Section\ShowSectionsRequest;
use App\Http\Requests\Models\Section\CreateSectionRequest;
use App\Http\Requests\Models\Section\UpdateSectionRequest;
use App\Http\Requests\Models\Section\DeleteSectionsRequest;
use App\Http\Requests\Models\Section\UpdateSectionVisibilityRequest;
use App\Http\Requests\Models\Section\UpdateSectionArrangementRequest;

class SectionController extends BaseController
{
    /**
     *  @var SectionRepository
     */
    protected $repository;

    /**
     * SectionController constructor.
     *
     * @param SectionRepository $repository
     */
    public function __construct(SectionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show sections.
     *
     * @param ShowSectionsRequest $request
     * @param string|null $pageId
     * @return JsonResponse
     */
    public function showSections(ShowSectionsRequest $request): JsonResponse
    {
        if($request->pageId) {
            $request->merge(['page_id' => $request->pageId]);
        }

        return $this->prepareOutput($this->repository->showSections($request->all()));
    }

    /**
     * Create section.
     *
     * @param CreateSectionRequest $request
     * @return JsonResponse
     */
    public function createSection(CreateSectionRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createSection($request->all()));
    }

    /**
     * Delete sections.
     *
     * @param DeleteSectionsRequest $request
     * @return JsonResponse
     */
    public function deleteSections(DeleteSectionsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteSections($request->all()));
    }

    /**
     * Update section visibility.
     *
     * @param UpdateSectionVisibilityRequest $request
     * @return JsonResponse
     */
    public function updateSectionVisibility(UpdateSectionVisibilityRequest $request)
    {
        return $this->prepareOutput($this->repository->updateSectionVisibility($request->all()));
    }

    /**
     * Update section arrangement.
     *
     * @param UpdateSectionArrangementRequest $request
     * @return JsonResponse
     */
    public function updateSectionArrangement(UpdateSectionArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updateSectionArrangement($request->all()));
    }

    /**
     * Show section.
     *
     * @param string $sectionId
     * @return JsonResponse
     */
    public function showSection(string $sectionId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showSection($sectionId));
    }

    /**
     * Update section.
     *
     * @param UpdateSectionRequest $request
     * @param string $sectionId
     * @return JsonResponse
     */
    public function updateSection(UpdateSectionRequest $request, string $sectionId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateSection($sectionId, $request->all()));
    }

    /**
     * Delete section.
     *
     * @param string $sectionId
     * @return JsonResponse
     */
    public function deleteSection(string $sectionId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteSection($sectionId));
    }
}
