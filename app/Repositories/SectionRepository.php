<?php

namespace App\Repositories;

use App\Models\Page;
use App\Models\Section;
use App\Traits\AuthTrait;
use Illuminate\Support\Str;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Http\Resources\SectionResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class SectionRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show sections.
     *
     * @param array $data
     * @return SectionResources|array
     */
    public function showSections(array $data = []): SectionResources|array
    {
        if($this->getQuery() == null) {

            $pageId = isset($data['page_id']) ? $data['page_id'] : null;

            if(is_null($pageId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show sections'];
                $this->setQuery(Section::latest());
            }else{

                $page = Page::whereId($pageId)->with(['store'])->first();

                if($page) {

                    $store = $page->store;

                    if($store) {

                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show sections'];
                        $this->setQuery($page->sections()->orderByPivot('position'));

                    }else{
                        return ['message' => 'This store does not exist'];
                    }

                }else{
                    return ['message' => 'This page does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create section.
     *
     * @param array $data
     * @return Section|array
     */
    public function createSection(array $data): Section|array
    {
        $pageId = $data['page_id'];
        $page = Page::whereId($pageId)->with(['store'])->first();

        if($page) {

            $store = $page->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create sections'];
            }else{
                return ['created' => false, 'message' => 'This store does not exist'];
            }

        }else{
            return ['created' => false, 'message' => 'This page does not exist'];
        }

        $data = [
            ...$data,
            'store_id' => $page->store_id
        ];

        $section = Section::create($data);

        $page->sections()->attach($section->id, [
            'id' => Str::uuid(),
            'visible' => $data['visible'] ?? 0
        ]);

        return $this->showCreatedResource($section);
    }

    /**
     * Delete sections.
     *
     * @param array $data
     * @return array
     */
    public function deleteSections(array $data): array
    {
        $pageId = $data['page_id'];

        if(is_null($pageId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete sections'];
            $this->setQuery(Section::query());
        }else{

            $page = Page::whereId($pageId)->with(['store'])->first();

            if($page) {

                $store = $page->store;

                if($store) {

                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete sections'];
                    $this->setQuery($page->sections());

                }else{
                    return ['deleted' => false, 'message' => 'This store does not exist'];
                }

            }else{
                return ['deleted' => false, 'message' => 'This page does not exist'];
            }
        }

        $sectionIds = $data['section_ids'];
        $sections = $this->getSectionsByIds($sectionIds);

        if($totalSections = $sections->count()) {

            foreach($sections as $section) {
                $section->delete();
            }

            return ['deleted' => true, 'message' => $totalSections . ($totalSections == 1 ? ' section': ' sections') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No sections deleted'];
        }
    }

    /**
     * Update section visibility
     *
     * @param array $data
     * @return array
     */
    public function updateSectionVisibility(array $data): array
    {
        $pageId = $data['page_id'];
        $page = Page::whereId($pageId)->with(['store'])->first();

        if ($page) {
            $store = $page->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if (!$isAuthourized) {
                    return ['message' => 'You do not have permission to update section visibility'];
                }
                $this->setQuery($page->sections());
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This page does not exist'];
        }

        $sections = $this->query->get();
        $sectionIdsAndVisibility = $data['visibility'];

        $existingSectionIdsAndVisibility = $sections->map(function ($section) {
            return ['id' => $section->id, 'visible' => $section->pivot->visible];
        });

        $newSectionIdsAndVisibility = collect($sectionIdsAndVisibility)->filter(function ($item) use ($existingSectionIdsAndVisibility) {
            return $existingSectionIdsAndVisibility->contains('id', $item['id']);
        })->toArray();

        $oldSectionIdsAndVisibility = collect($existingSectionIdsAndVisibility)->filter(function ($item) use ($sectionIdsAndVisibility) {
            return collect($sectionIdsAndVisibility)->doesntContain('id', $item['id']);
        })->toArray();

        $finalSectionIdsAndVisibility = $newSectionIdsAndVisibility + $oldSectionIdsAndVisibility;
        $finalSectionIdsAndVisibility = collect($finalSectionIdsAndVisibility)->mapWithKeys(fn($item) => [$item['id'] => $item['visible'] ? 1 : 0])->toArray();

        if (count($finalSectionIdsAndVisibility)) {
            foreach ($finalSectionIdsAndVisibility as $sectionId => $visibility) {
                DB::table('page_section')
                    ->where('page_id', $page->id)
                    ->where('section_id', $sectionId)
                    ->update(['visible' => $visibility]);
            }

            return ['updated' => true, 'message' => 'Section visibility has been updated'];
        }

        return ['updated' => false, 'message' => 'No matching sections to update'];
    }

    /**
     * Update section arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateSectionArrangement(array $data): array
    {
        $pageId = $data['page_id'];
        $page = Page::whereId($pageId)->with(['store'])->first();

        if ($page) {
            $store = $page->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to update section arrangement'];
                $this->setQuery($page->sections()->orderByPivot('position', 'asc'));
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This page does not exist'];
        }

        $sections = $this->query->get();
        $originalSectionPositions = $sections->pluck('pivot.position', 'id');

        $sectionIds = $data['section_ids'];

        $arrangement = collect($sectionIds)->filter(function ($sectionId) use ($originalSectionPositions) {
            return collect($originalSectionPositions)->keys()->contains($sectionId);
        })->toArray();

        $movedSectionPositions = collect($arrangement)->mapWithKeys(function ($sectionId, $newPosition) use ($originalSectionPositions) {
            return [$sectionId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalSectionPositions = $originalSectionPositions->except(collect($movedSectionPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedSectionPositions) {
            return [$id => count($movedSectionPositions) + $index + 1];
        })->toArray();

        $sectionPositions = $movedSectionPositions + $adjustedOriginalSectionPositions;

        if(count($sectionPositions)) {

            foreach ($sectionPositions as $sectionId => $position) {
                DB::table('page_section')
                    ->where('page_id', $page->id)
                    ->where('section_id', $sectionId)
                    ->update(['position' => $position]);
            }

            return ['updated' => true, 'message' => 'Section arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching sections to update'];
    }

    /**
     * Show section.
     *
     * @param string $sectionId
     * @return Section|array|null
     */
    public function showSection(string $sectionId): Section|array|null
    {
        $section = Section::find($sectionId);
        return $this->showResourceExistence($section);
    }

    /**
     * Update section.
     *
     * @param string $sectionId
     * @param array $data
     * @return Section|array
     */
    public function updateSection(string $sectionId, array $data): Section|array
    {
        $section = Section::with(['store'])->find($sectionId);

        if($section) {
            $store = $section->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update section'];
                if(!$this->checkIfHasRelationOnRequest('store')) $section->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $section->update($data);
            return $this->showUpdatedResource($section);

        }else{
            return ['updated' => false, 'message' => 'This section does not exist'];
        }
    }

    /**
     * Delete section.
     *
     * @param string $sectionId
     * @return array
     */
    public function deleteSection(string $sectionId): array
    {
        $section = Section::with(['store'])->find($sectionId);

        if($section) {
            $store = $section->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete section'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $section->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Section deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Section delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This section does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query section by ID.
     *
     * @param Section|string $sectionId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function querySectionById(Section|string $sectionId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('sections.id', $sectionId)->with($relationships);
    }

    /**
     * Get section by ID.
     *
     * @param Section|string $sectionId
     * @param array $relationships
     * @return Section|null
     */
    public function getSectionById(Section|string $sectionId, array $relationships = []): Section|null
    {
        return $this->querySectionById($sectionId, $relationships)->first();
    }

    /**
     * Query sections by IDs.
     *
     * @param array<string> $sectionId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function querySectionsByIds($sectionIds): Builder|Relation
    {
        return $this->query->whereIn('sections.id', $sectionIds);
    }

    /**
     * Get sections by IDs.
     *
     * @param array<string> $sectionId
     * @param string $relationships
     * @return Collection
     */
    public function getSectionsByIds($sectionIds): Collection
    {
        return $this->querySectionsByIds($sectionIds)->get();
    }
}
