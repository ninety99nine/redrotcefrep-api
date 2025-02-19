<?php

namespace App\Repositories;

use App\Enums\ModuleType;
use App\Enums\RequestFileName;
use App\Models\Page;
use App\Models\Store;
use App\Models\Section;
use App\Traits\AuthTrait;
use Illuminate\Support\Str;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Http\Resources\PageResources;
use App\Models\Column;
use App\Models\Module;
use App\Models\Row;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class PageRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show pages.
     *
     * @param array $data
     * @return PageResources|array
     */
    public function showPages(array $data = []): PageResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;

            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show pages'];
                $this->setQuery(Page::latest());
            }else{

                $store = Store::find($storeId);

                if($store) {

                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show pages'];
                    $this->setQuery($store->pages()->orderBy('position'));

                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /**
     * Create page.
     *
     * @param array $data
     * @return Page|array
     */
    public function createPage(array $data): Page|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create pages'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $page = $store->pages()->create($data);

        if(isset($data['sections'])) {
            $this->saveSections($page, $data['sections']);
            $page->unsetRelation('sections');
        }

        if($data['homepage']) $store->pages()->where('id', '!=', $page->id)->update(['homepage' => 0]);

        return $this->showCreatedResource($page);
    }

    /**
     * Delete pages.
     *
     * @param array $data
     * @return array
     */
    public function deletePages(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete pages'];
            $this->setQuery(Page::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete pages'];
                $this->setQuery($store->pages());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $pageIds = $data['page_ids'];
        $pages = $this->getPagesByIds($pageIds);

        if($totalPages = $pages->count()) {

            foreach($pages as $page) {
                $page->delete();
            }

            return ['deleted' => true, 'message' => $totalPages . ($totalPages == 1 ? ' page': ' pages') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No pages deleted'];
        }
    }

    /**
     * Update page visibility
     *
     * @param array $data
     * @return array
     */
    public function updatePageVisibility(array $data): array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update page visibility'];
            $this->setQuery($store->pages());
        }else{
            return ['message' => 'This store does not exist'];
        }

        $pages = $this->query->get();
        $pageIdsAndVisibility = $data['visibility'];

        $existingPageIdsAndVisibility = $pages->map(function ($page) {
            return ['id' => $page->id, 'visible' => $page->visible];
        });

        $newPageIdsAndVisibility = collect($pageIdsAndVisibility)->filter(function ($item) use ($existingPageIdsAndVisibility) {
            return $existingPageIdsAndVisibility->contains('id', $item['id']);
        })->toArray();

        $oldPageIdsAndVisibility = collect($existingPageIdsAndVisibility)->filter(function ($item) use ($pageIdsAndVisibility) {
            return collect($pageIdsAndVisibility)->doesntContain('id', $item['id']);
        })->toArray();

        $finalPageIdsAndVisibility = $newPageIdsAndVisibility + $oldPageIdsAndVisibility;
        $finalPageIdsAndVisibility = collect($finalPageIdsAndVisibility)->mapWithKeys(fn($item) => [$item['id'] => $item['visible'] ? 1 : 0])->toArray();

        if(count($finalPageIdsAndVisibility)) {

            DB::table('pages')
            ->where('store_id', $store->id)
            ->whereIn('id', array_keys($finalPageIdsAndVisibility))
            ->update(['visible' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $visibility) {
                return 'WHEN "' . $id . '" THEN ' . $visibility . ' ';
            }, array_keys($finalPageIdsAndVisibility), $finalPageIdsAndVisibility)) . 'END')]);

            return ['updated' => true, 'message' => 'Page visibility has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching pages to update'];
    }

    /**
     * Update page arrangement
     *
     * @param array $data
     * @return array
     */
    public function updatePageArrangement(array $data): array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update page arrangement'];
            $this->setQuery($store->pages()->orderBy('position', 'asc'));
        }else{
            return ['message' => 'This store does not exist'];
        }

        $pages = $this->query->get();
        $originalPagePositions = $pages->pluck('position', 'id');

        $pageIds = $data['page_ids'];

        $arrangement = collect($pageIds)->filter(function ($pageId) use ($originalPagePositions) {
            return collect($originalPagePositions)->keys()->contains($pageId);
        })->toArray();

        $movedPagePositions = collect($arrangement)->mapWithKeys(function ($pageId, $newPosition) use ($originalPagePositions) {
            return [$pageId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalPagePositions = $originalPagePositions->except(collect($movedPagePositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedPagePositions) {
            return [$id => count($movedPagePositions) + $index + 1];
        })->toArray();

        $pagePositions = $movedPagePositions + $adjustedOriginalPagePositions;

        if(count($pagePositions)) {

            DB::table('pages')
                ->where('store_id', $store->id)
                ->whereIn('id', array_keys($pagePositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($pagePositions), $pagePositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Page arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching pages to update'];
    }

    /**
     * Show page.
     *
     * @param string $pageId
     * @return Page|array|null
     */
    public function showPage(string $pageId): Page|array|null
    {
        $page = $this->setQuery(Page::with(['store'])->whereId($pageId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($page) {
            $store = $page->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show page'];
                if(!$this->checkIfHasRelationOnRequest('store')) $page->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($page);
    }

    /**
     * Update page.
     *
     * @param string $pageId
     * @param array $data
     * @return Page|array
     */
    public function updatePage(string $pageId, array $data): Page|array
    {
        $page = Page::with(['store'])->find($pageId);

        if($page) {
            $store = $page->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update page'];
                if(!$this->checkIfHasRelationOnRequest('store')) $page->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $page->update($data);

            if(isset($data['sections'])) {
                $this->saveSections($page, $data['sections']);
                $page->unsetRelation('sections');
            }

            return $this->showUpdatedResource($page);

        }else{
            return ['updated' => false, 'message' => 'This page does not exist'];
        }
    }

    /**
     * Delete page.
     *
     * @param string $pageId
     * @return array
     */
    public function deletePage(string $pageId): array
    {
        $page = Page::with(['store'])->find($pageId);

        if($page) {
            $store = $page->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete page'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $page->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Page deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Page delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This page does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query page by ID.
     *
     * @param Page|string $pageId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryPageById(Page|string $pageId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('pages.id', $pageId)->with($relationships);
    }

    /**
     * Get page by ID.
     *
     * @param Page|string $pageId
     * @param array $relationships
     * @return Page|null
     */
    public function getPageById(Page|string $pageId, array $relationships = []): Page|null
    {
        return $this->queryPageById($pageId, $relationships)->first();
    }

    /**
     * Query pages by IDs.
     *
     * @param array<string> $pageId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryPagesByIds($pageIds): Builder|Relation
    {
        return $this->query->whereIn('pages.id', $pageIds);
    }

    /**
     * Get pages by IDs.
     *
     * @param array<string> $pageId
     * @param string $relationships
     * @return Collection
     */
    public function getPagesByIds($pageIds): Collection
    {
        return $this->queryPagesByIds($pageIds)->get();
    }

    /**
     * Save sections efficiently, including pivot data.
     *
     * @param Page $page
     * @param array $sectionsData
     * @return void
     */
    public function saveSections(Page $page, array $sectionsData): void
    {
        $page->loadMissing('sections.rows.columns.modules');

        $newSectionIds = [];
        $sectionUpdates = [];
        $sectionBulkInsert = [];
        $sectionPivotInsert = [];
        $existingSectionIds = collect($page->sections)->pluck('id')->toArray();

        // Get the fillable fields from the Section model
        $fillableFields = (new Section())->getFillable();

        foreach ($sectionsData as $index => $sectionData) {

            // Filter only the fillable fields
            $filteredSectionData = array_intersect_key($sectionData, array_flip($fillableFields));

            if (isset($sectionData['id']) && in_array($sectionData['id'], $existingSectionIds)) {

                $sectionUpdates[] = array_merge($filteredSectionData, [
                    'updated_at' => now(),
                    'id' => $sectionData['id'],
                    'store_id' => $page->store_id
                ]);

                $newSectionIds[] = $sectionData['id'];

            } else {

                $newId = Str::uuid();
                $sectionsData[$index]['id'] = $newId;

                $sectionBulkInsert[] = array_merge($filteredSectionData, [
                    'id' => $newId,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'store_id' => $page->store_id
                ]);

                $newSectionIds[] = $newId;
            }

            $sectionPivotInsert[] = [
                'id' => Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'page_id' => $page->id,
                'position' => $index + 1,
                'visible' => $sectionData['visible'] ?? false,
                'section_id' => $sectionsData[$index]['id'] ?? $newId
            ];
        }

        // Bulk insert new sections
        if (!empty($sectionBulkInsert)) {
            Section::insert($sectionBulkInsert);
        }

        // Bulk update existing sections
        if (!empty($sectionUpdates)) {
            Section::upsert($sectionUpdates, ['id'], collect($sectionUpdates[0])->keys()->toArray());
        }

        // Bulk delete removed sections
        $sectionsToDelete = array_diff($existingSectionIds, $newSectionIds);

        if (!empty($sectionsToDelete)) {
            Section::whereIn('id', $sectionsToDelete)->delete();
        }

        // Bulk insert pivot data for page-section relation
        DB::table('page_section')->where('page_id', $page->id)->delete();
        DB::table('page_section')->insert($sectionPivotInsert);

        // Process rows
        $sections = Section::whereIn('id', $newSectionIds)->get();

        foreach ($sections as $section) {
            $sectionData = collect($sectionsData)->firstWhere('id', $section->id);
            $this->saveRows($section, $sectionData['rows'] ?? []);
        }
    }

    /**
     * Save rows efficiently, including pivot data.
     *
     * @param Section $section
     * @param array $rowsData
     * @return void
     */
    public function saveRows(Section $section, array $rowsData): void
    {
        $newRowIds = [];
        $rowUpdates = [];
        $rowBulkInsert = [];
        $rowPivotInsert = [];
        $existingRowIds = collect($section->rows)->pluck('id')->toArray();

        // Get the fillable fields from the Row model
        $fillableFields = (new Row())->getFillable();

        foreach ($rowsData as $index => $rowData) {

            // Filter only the fillable fields
            $filteredRowData = array_intersect_key($rowData, array_flip($fillableFields));

            if (isset($rowData['id']) && in_array($rowData['id'], $existingRowIds)) {

                $rowUpdates[] = array_merge($filteredRowData, [
                    'updated_at' => now(),
                    'id' => $rowData['id'],
                    'store_id' => $section->store_id
                ]);

                $newRowIds[] = $rowData['id'];

            } else {

                $newId = Str::uuid();
                $rowsData[$index]['id'] = $newId;

                $rowBulkInsert[] = array_merge($filteredRowData, [
                    'id' => $newId,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'store_id' => $section->store_id
                ]);

                $newRowIds[] = $newId;
            }

            $rowPivotInsert[] = [
                'id' => Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'position' => $index + 1,
                'section_id' => $section->id,
                'visible' => $rowData['visible'] ?? false,
                'row_id' => $rowsData[$index]['id'] ?? $newId,
            ];
        }

        // Bulk insert new rows
        if (!empty($rowBulkInsert)) {
            Row::insert($rowBulkInsert);
        }

        // Bulk update existing rows
        if (!empty($rowUpdates)) {
            Row::upsert($rowUpdates, ['id'], collect($rowUpdates[0])->keys()->toArray());
        }

        // Bulk delete removed rows
        $rowsToDelete = array_diff($existingRowIds, $newRowIds);

        if (!empty($rowsToDelete)) {
            Row::whereIn('id', $rowsToDelete)->delete();
        }

        // Bulk insert pivot data for section-row relation
        DB::table('section_row')->where('section_id', $section->id)->delete();
        DB::table('section_row')->insert($rowPivotInsert);

        // Process columns
        $rows = Row::whereIn('id', $newRowIds)->get();

        foreach ($rows as $row) {
            $rowData = collect($rowsData)->firstWhere('id', $row->id);
            $this->saveColumns($row, $rowData['columns'] ?? []);
        }
    }

    /**
     * Save columns efficiently, including pivot data.
     *
     * @param Row $row
     * @param array $columnsData
     * @return void
     */
    public function saveColumns(Row $row, array $columnsData): void
    {
        $newColumnIds = [];
        $columnUpdates = [];
        $columnBulkInsert = [];
        $columnPivotInsert = [];
        $existingColumnIds = $row->columns()->pluck('column_id')->toArray();

        // Get the fillable fields from the Column model
        $fillableFields = (new Column())->getFillable();

        foreach ($columnsData as $index => $columnData) {

            // Filter only the fillable fields
            $filteredColumnData = array_intersect_key($columnData, array_flip($fillableFields));

            if(isset($filteredColumnData['settings'])) {
                $filteredColumnData['settings'] = json_encode($filteredColumnData['settings']);
            }

            if (isset($columnData['id']) && in_array($columnData['id'], $existingColumnIds)) {

                $columnUpdates[] = array_merge($filteredColumnData, [
                    'updated_at' => now(),
                    'id' => $columnData['id'],
                    'store_id' => $row->store_id
                ]);

                $newColumnIds[] = $columnData['id'];

            } else {

                $newId = Str::uuid();
                $columnsData[$index]['id'] = $newId;

                $columnBulkInsert[] = array_merge($filteredColumnData, [
                    'id' => $newId,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'store_id' => $row->store_id
                ]);

                $newColumnIds[] = $newId;

            }

            $columnPivotInsert[] = [
                'id' => Str::uuid(),
                'row_id' => $row->id,
                'created_at' => now(),
                'updated_at' => now(),
                'position' => $index + 1,
                'visible' => $columnData['visible'] ?? false,
                'column_id' => $columnsData[$index]['id'] ?? $newId,
            ];
        }

        // Bulk insert new columns
        if (!empty($columnBulkInsert)) {
            Column::insert($columnBulkInsert);
        }

        // Bulk update existing columns
        if (!empty($columnUpdates)) {
            Column::upsert($columnUpdates, ['id'], collect($columnUpdates[0])->keys()->toArray());
        }

        // Bulk delete removed columns
        $columnsToDelete = array_diff($existingColumnIds, $newColumnIds);

        if (!empty($columnsToDelete)) {
            Column::whereIn('id', $columnsToDelete)->delete();
        }

        // Bulk insert pivot data for row-column relation
        DB::table('row_column')->where('row_id', $row->id)->delete();
        DB::table('row_column')->insert($columnPivotInsert);

        // Process modules
        $columns = Column::whereIn('id', $newColumnIds)->get();

        foreach ($columns as $column) {
            $columnData = collect($columnsData)->firstWhere('id', $column->id);
            $this->saveModules($column, $columnData['modules'] ?? []);
        }
    }

    /**
     * Save modules efficiently, including pivot data.
     *
     * @param Column $column
     * @param array $modulesData
     * @return void
     */
    public function saveModules(Column $column, array $modulesData): void
    {
        $newModuleIds = [];
        $moduleUpdates = [];
        $moduleBulkInsert = [];
        $modulePivotInsert = [];
        $existingModuleIds = $column->modules()->pluck('module_id')->toArray();

        // Get the fillable fields from the Module model
        $fillableFields = (new Module())->getFillable();

        foreach ($modulesData as $index => $moduleData) {

            // Filter only the fillable fields
            $filteredModuleData = array_intersect_key($moduleData, array_flip($fillableFields));

            if(isset($filteredModuleData['settings'])) {
                $filteredModuleData['settings'] = json_encode($filteredModuleData['settings']);
            }

            if (isset($moduleData['id']) && in_array($moduleData['id'], $existingModuleIds)) {

                $moduleUpdates[] = array_merge($filteredModuleData, [
                    'updated_at' => now(),
                    'id' => $moduleData['id'],
                    'store_id' => $column->store_id
                ]);

                $newModuleIds[] = $moduleData['id'];

            } else {

                $newId = Str::uuid();
                $modulesData[$index]['id'] = $newId;

                $moduleBulkInsert[] = array_merge($filteredModuleData, [
                    'id' => $newId,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'store_id' => $column->store_id
                ]);

                $newModuleIds[] = $newId;

            }

            $modulePivotInsert[] = [
                'id' => Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'position' => $index + 1,
                'column_id' => $column->id,
                'visible' => $moduleData['visible'] ?? false,
                'module_id' => $modulesData[$index]['id'] ?? $newId,
            ];
        }

        // Bulk insert new modules
        if (!empty($moduleBulkInsert)) {
            Module::insert($moduleBulkInsert);
        }

        // Bulk update existing modules
        if (!empty($moduleUpdates)) {
            Module::upsert($moduleUpdates, ['id'], collect($moduleUpdates[0])->keys()->toArray());
        }

        // Bulk delete removed modules
        $modulesToDelete = array_diff($existingModuleIds, $newModuleIds);

        if (!empty($modulesToDelete)) {
            Module::whereIn('id', $modulesToDelete)->delete();
        }

        // Bulk insert pivot data for column-module relation
        DB::table('column_module')->where('column_id', $column->id)->delete();
        DB::table('column_module')->insert($modulePivotInsert);
    }
}
