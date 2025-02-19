<?php

namespace App\Repositories;

use App\Models\Row;
use App\Models\Section;
use App\Traits\AuthTrait;
use Illuminate\Support\Str;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Http\Resources\RowResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class RowRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show rows.
     *
     * @param array $data
     * @return RowResources|array
     */
    public function showRows(array $data = []): RowResources|array
    {
        if($this->getQuery() == null) {

            $sectionId = isset($data['section_id']) ? $data['section_id'] : null;

            if(is_null($sectionId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show rows'];
                $this->setQuery(Row::latest());
            }else{

                $section = Section::whereId($sectionId)->with(['store'])->first();

                if($section) {

                    $store = $section->store;

                    if($store) {

                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show rows'];
                        $this->setQuery($section->rows()->orderByPivot('position'));

                    }else{
                        return ['message' => 'This store does not exist'];
                    }

                }else{
                    return ['message' => 'This section does not exist'];
                }
            }
        }

        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /**
     * Create row.
     *
     * @param array $data
     * @return Row|array
     */
    public function createRow(array $data): Row|array
    {
        $sectionId = $data['section_id'];
        $section = Section::whereId($sectionId)->with(['store'])->first();

        if($section) {

            $store = $section->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create rows'];
            }else{
                return ['created' => false, 'message' => 'This store does not exist'];
            }

        }else{
            return ['created' => false, 'message' => 'This section does not exist'];
        }

        $data = [
            ...$data,
            'store_id' => $section->store_id
        ];

        $row = Row::create($data);

        $section->rows()->attach($row->id, [
            'id' => Str::uuid(),
            'visible' => $data['visible'] ?? 0
        ]);

        return $this->showCreatedResource($row);
    }

    /**
     * Delete rows.
     *
     * @param array $data
     * @return array
     */
    public function deleteRows(array $data): array
    {
        $sectionId = $data['section_id'];

        if(is_null($sectionId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete rows'];
            $this->setQuery(Row::query());
        }else{

            $section = Section::whereId($sectionId)->with(['store'])->first();

            if($section) {

                $store = $section->store;

                if($store) {

                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete rows'];
                    $this->setQuery($section->rows());

                }else{
                    return ['deleted' => false, 'message' => 'This store does not exist'];
                }

            }else{
                return ['deleted' => false, 'message' => 'This section does not exist'];
            }
        }

        $rowIds = $data['row_ids'];
        $rows = $this->getRowsByIds($rowIds);

        if($totalRows = $rows->count()) {

            foreach($rows as $row) {
                $row->delete();
            }

            return ['deleted' => true, 'message' => $totalRows . ($totalRows == 1 ? ' row': ' rows') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No rows deleted'];
        }
    }

    /**
     * Update row visibility
     *
     * @param array $data
     * @return array
     */
    public function updateRowVisibility(array $data): array
    {
        $sectionId = $data['section_id'];
        $section = Section::whereId($sectionId)->with(['store'])->first();

        if ($section) {
            $store = $section->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if (!$isAuthourized) {
                    return ['message' => 'You do not have permission to update row visibility'];
                }
                $this->setQuery($section->rows());
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This section does not exist'];
        }

        $rows = $this->query->get();
        $rowIdsAndVisibility = $data['visibility'];

        $existingRowIdsAndVisibility = $rows->map(function ($row) {
            return ['id' => $row->id, 'visible' => $row->pivot->visible];
        });

        $newRowIdsAndVisibility = collect($rowIdsAndVisibility)->filter(function ($item) use ($existingRowIdsAndVisibility) {
            return $existingRowIdsAndVisibility->contains('id', $item['id']);
        })->toArray();

        $oldRowIdsAndVisibility = collect($existingRowIdsAndVisibility)->filter(function ($item) use ($rowIdsAndVisibility) {
            return collect($rowIdsAndVisibility)->doesntContain('id', $item['id']);
        })->toArray();

        $finalRowIdsAndVisibility = $newRowIdsAndVisibility + $oldRowIdsAndVisibility;
        $finalRowIdsAndVisibility = collect($finalRowIdsAndVisibility)->mapWithKeys(fn($item) => [$item['id'] => $item['visible'] ? 1 : 0])->toArray();

        if (count($finalRowIdsAndVisibility)) {
            foreach ($finalRowIdsAndVisibility as $rowId => $visibility) {
                DB::table('section_row')
                    ->where('section_id', $section->id)
                    ->where('row_id', $rowId)
                    ->update(['visible' => $visibility]);
            }

            return ['updated' => true, 'message' => 'Row visibility has been updated'];
        }

        return ['updated' => false, 'message' => 'No matching rows to update'];
    }

    /**
     * Update row arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateRowArrangement(array $data): array
    {
        $sectionId = $data['section_id'];
        $section = Section::whereId($sectionId)->with(['store'])->first();

        if ($section) {
            $store = $section->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to update row arrangement'];
                $this->setQuery($section->rows()->orderByPivot('position', 'asc'));
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This section does not exist'];
        }

        $rows = $this->query->get();
        $originalRowPositions = $rows->pluck('pivot.position', 'id');

        $rowIds = $data['row_ids'];

        $arrangement = collect($rowIds)->filter(function ($rowId) use ($originalRowPositions) {
            return collect($originalRowPositions)->keys()->contains($rowId);
        })->toArray();

        $movedRowPositions = collect($arrangement)->mapWithKeys(function ($rowId, $newPosition) use ($originalRowPositions) {
            return [$rowId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalRowPositions = $originalRowPositions->except(collect($movedRowPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedRowPositions) {
            return [$id => count($movedRowPositions) + $index + 1];
        })->toArray();

        $rowPositions = $movedRowPositions + $adjustedOriginalRowPositions;

        if(count($rowPositions)) {

            foreach ($rowPositions as $rowId => $position) {
                DB::table('section_row')
                    ->where('section_id', $section->id)
                    ->where('row_id', $rowId)
                    ->update(['position' => $position]);
            }

            return ['updated' => true, 'message' => 'Row arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching rows to update'];
    }

    /**
     * Show row.
     *
     * @param string $rowId
     * @return Row|array|null
     */
    public function showRow(string $rowId): Row|array|null
    {
        $row = Row::find($rowId);
        return $this->showResourceExistence($row);
    }

    /**
     * Update row.
     *
     * @param string $rowId
     * @param array $data
     * @return Row|array
     */
    public function updateRow(string $rowId, array $data): Row|array
    {
        $row = Row::with(['store'])->find($rowId);

        if($row) {
            $store = $row->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update row'];
                if(!$this->checkIfHasRelationOnRequest('store')) $row->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $row->update($data);
            return $this->showUpdatedResource($row);

        }else{
            return ['updated' => false, 'message' => 'This row does not exist'];
        }
    }

    /**
     * Delete row.
     *
     * @param string $rowId
     * @return array
     */
    public function deleteRow(string $rowId): array
    {
        $row = Row::with(['store'])->find($rowId);

        if($row) {
            $store = $row->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete row'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $row->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Row deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Row delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This row does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query row by ID.
     *
     * @param Row|string $rowId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryRowById(Row|string $rowId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('rows.id', $rowId)->with($relationships);
    }

    /**
     * Get row by ID.
     *
     * @param Row|string $rowId
     * @param array $relationships
     * @return Row|null
     */
    public function getRowById(Row|string $rowId, array $relationships = []): Row|null
    {
        return $this->queryRowById($rowId, $relationships)->first();
    }

    /**
     * Query rows by IDs.
     *
     * @param array<string> $rowId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryRowsByIds($rowIds): Builder|Relation
    {
        return $this->query->whereIn('rows.id', $rowIds);
    }

    /**
     * Get rows by IDs.
     *
     * @param array<string> $rowId
     * @param string $relationships
     * @return Collection
     */
    public function getRowsByIds($rowIds): Collection
    {
        return $this->queryRowsByIds($rowIds)->get();
    }
}
