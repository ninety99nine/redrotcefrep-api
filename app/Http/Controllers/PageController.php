<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Repositories\PageRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Page\ShowPagesRequest;
use App\Http\Requests\Models\Page\CreatePageRequest;
use App\Http\Requests\Models\Page\UpdatePageRequest;
use App\Http\Requests\Models\Page\DeletePagesRequest;
use App\Http\Requests\Models\Page\UpdatePageVisibilityRequest;
use App\Http\Requests\Models\Page\UpdatePageArrangementRequest;

class PageController extends BaseController
{
    /**
     *  @var PageRepository
     */
    protected $repository;

    /**
     * PageController constructor.
     *
     * @param PageRepository $repository
     */
    public function __construct(PageRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show pages.
     *
     * @param ShowPagesRequest $request
     * @param string|null $storeId
     * @return JsonResponse
     */
    public function showPages(ShowPagesRequest $request): JsonResponse
    {
        if($request->storeId) {
            $request->merge(['store_id' => $request->storeId]);
        }

        return $this->prepareOutput($this->repository->showPages($request->all()));
    }

    /**
     * Create page.
     *
     * @param CreatePageRequest $request
     * @return JsonResponse
     */
    public function createPage(CreatePageRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createPage($request->all()));
    }

    /**
     * Delete pages.
     *
     * @param DeletePagesRequest $request
     * @return JsonResponse
     */
    public function deletePages(DeletePagesRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deletePages($request->all()));
    }

    /**
     * Update page visibility.
     *
     * @param UpdatePageVisibilityRequest $request
     * @return JsonResponse
     */
    public function updatePageVisibility(UpdatePageVisibilityRequest $request)
    {
        return $this->prepareOutput($this->repository->updatePageVisibility($request->all()));
    }

    /**
     * Update page arrangement.
     *
     * @param UpdatePageArrangementRequest $request
     * @return JsonResponse
     */
    public function updatePageArrangement(UpdatePageArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updatePageArrangement($request->all()));
    }

    /**
     * Show page.
     *
     * @param string $pageId
     * @return JsonResponse
     */
    public function showPage(string $pageId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPage($pageId));
    }

    /**
     * Update page.
     *
     * @param UpdatePageRequest $request
     * @param string $pageId
     * @return JsonResponse
     */
    public function updatePage(UpdatePageRequest $request, string $pageId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updatePage($pageId, $request->all()));
    }

    /**
     * Delete page.
     *
     * @param string $pageId
     * @return JsonResponse
     */
    public function deletePage(string $pageId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deletePage($pageId));
    }
}
