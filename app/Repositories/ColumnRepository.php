<?php

namespace App\Repositories;

use App\Models\Row;
use App\Models\Column;
use App\Traits\AuthTrait;
use Illuminate\Support\Str;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Http\Resources\ColumnResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ColumnRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show columns.
     *
     * @param array $data
     * @return ColumnResources|array
     */
    public function showColumns(array $data = []): ColumnResources|array
    {
        if($this->getQuery() == null) {

            $rowId = isset($data['row_id']) ? $data['row_id'] : null;

            if(is_null($rowId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show columns'];
                $this->setQuery(Column::latest());
            }else{

                $row = Row::whereId($rowId)->with(['store'])->first();

                if($row) {

                    $store = $row->store;

                    if($store) {

                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show columns'];
                        $this->setQuery($row->columns()->orderByPivot('position'));

                    }else{
                        return ['message' => 'This store does not exist'];
                    }

                }else{
                    return ['message' => 'This row does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create column.
     *
     * @param array $data
     * @return Column|array
     */
    public function createColumn(array $data): Column|array
    {
        $rowId = $data['row_id'];
        $row = Row::whereId($rowId)->with(['store'])->first();

        if($row) {

            $store = $row->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create columns'];
            }else{
                return ['created' => false, 'message' => 'This store does not exist'];
            }

        }else{
            return ['created' => false, 'message' => 'This row does not exist'];
        }

        $data = [
            ...$data,
            'store_id' => $row->store_id
        ];

        $column = Column::create($data);

        $row->columns()->attach($column->id, [
            'id' => Str::uuid(),
            'visible' => $data['visible'] ?? 0
        ]);

        return $this->showCreatedResource($column);
    }

    /**
     * Delete columns.
     *
     * @param array $data
     * @return array
     */
    public function deleteColumns(array $data): array
    {
        $rowId = $data['row_id'];

        if(is_null($rowId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete columns'];
            $this->setQuery(Column::query());
        }else{

            $row = Row::whereId($rowId)->with(['store'])->first();

            if($row) {

                $store = $row->store;

                if($store) {

                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete columns'];
                    $this->setQuery($row->columns());

                }else{
                    return ['deleted' => false, 'message' => 'This store does not exist'];
                }

            }else{
                return ['deleted' => false, 'message' => 'This row does not exist'];
            }
        }

        $columnIds = $data['column_ids'];
        $columns = $this->getColumnsByIds($columnIds);

        if($totalColumns = $columns->count()) {

            foreach($columns as $column) {
                $column->delete();
            }

            return ['deleted' => true, 'message' => $totalColumns . ($totalColumns == 1 ? ' column': ' columns') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No columns deleted'];
        }
    }

    /**
     * Update column visibility
     *
     * @param array $data
     * @return array
     */
    public function updateColumnVisibility(array $data): array
    {
        $rowId = $data['row_id'];
        $row = Row::whereId($rowId)->with(['store'])->first();

        if ($row) {
            $store = $row->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if (!$isAuthourized) {
                    return ['message' => 'You do not have permission to update column visibility'];
                }
                $this->setQuery($row->columns());
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This row does not exist'];
        }

        $columns = $this->query->get();
        $columnIdsAndVisibility = $data['visibility'];

        $existingColumnIdsAndVisibility = $columns->map(function ($column) {
            return ['id' => $column->id, 'visible' => $column->pivot->visible];
        });

        $newColumnIdsAndVisibility = collect($columnIdsAndVisibility)->filter(function ($item) use ($existingColumnIdsAndVisibility) {
            return $existingColumnIdsAndVisibility->contains('id', $item['id']);
        })->toArray();

        $oldColumnIdsAndVisibility = collect($existingColumnIdsAndVisibility)->filter(function ($item) use ($columnIdsAndVisibility) {
            return collect($columnIdsAndVisibility)->doesntContain('id', $item['id']);
        })->toArray();

        $finalColumnIdsAndVisibility = $newColumnIdsAndVisibility + $oldColumnIdsAndVisibility;
        $finalColumnIdsAndVisibility = collect($finalColumnIdsAndVisibility)->mapWithKeys(fn($item) => [$item['id'] => $item['visible'] ? 1 : 0])->toArray();

        if (count($finalColumnIdsAndVisibility)) {
            foreach ($finalColumnIdsAndVisibility as $columnId => $visibility) {
                DB::table('row_column')
                    ->where('row_id', $row->id)
                    ->where('column_id', $columnId)
                    ->update(['visible' => $visibility]);
            }

            return ['updated' => true, 'message' => 'Column visibility has been updated'];
        }

        return ['updated' => false, 'message' => 'No matching columns to update'];
    }

    /**
     * Update column arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateColumnArrangement(array $data): array
    {
        $rowId = $data['row_id'];
        $row = Row::whereId($rowId)->with(['store'])->first();

        if ($row) {
            $store = $row->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to update column arrangement'];
                $this->setQuery($row->columns()->orderByPivot('position', 'asc'));
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This row does not exist'];
        }

        $columns = $this->query->get();
        $originalColumnPositions = $columns->pluck('pivot.position', 'id');

        $columnIds = $data['column_ids'];

        $arrangement = collect($columnIds)->filter(function ($columnId) use ($originalColumnPositions) {
            return collect($originalColumnPositions)->keys()->contains($columnId);
        })->toArray();

        $movedColumnPositions = collect($arrangement)->mapWithKeys(function ($columnId, $newPosition) use ($originalColumnPositions) {
            return [$columnId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalColumnPositions = $originalColumnPositions->except(collect($movedColumnPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedColumnPositions) {
            return [$id => count($movedColumnPositions) + $index + 1];
        })->toArray();

        $columnPositions = $movedColumnPositions + $adjustedOriginalColumnPositions;

        if(count($columnPositions)) {

            foreach ($columnPositions as $columnId => $position) {
                DB::table('row_column')
                    ->where('row_id', $row->id)
                    ->where('column_id', $columnId)
                    ->update(['position' => $position]);
            }

            return ['updated' => true, 'message' => 'Column arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching columns to update'];
    }

    /**
     * Show column.
     *
     * @param string $columnId
     * @return Column|array|null
     */
    public function showColumn(string $columnId): Column|array|null
    {
        $column = Column::find($columnId);
        return $this->showResourceExistence($column);
    }

    /**
     * Update column.
     *
     * @param string $columnId
     * @param array $data
     * @return Column|array
     */
    public function updateColumn(string $columnId, array $data): Column|array
    {
        $column = Column::with(['store'])->find($columnId);

        if($column) {
            $store = $column->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update column'];
                if(!$this->checkIfHasRelationOnRequest('store')) $column->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $column->update($data);
            return $this->showUpdatedResource($column);

        }else{
            return ['updated' => false, 'message' => 'This column does not exist'];
        }
    }

    /**
     * Delete column.
     *
     * @param string $columnId
     * @return array
     */
    public function deleteColumn(string $columnId): array
    {
        $column = Column::with(['store'])->find($columnId);

        if($column) {
            $store = $column->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete column'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $column->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Column deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Column delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This column does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query column by ID.
     *
     * @param Column|string $columnId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryColumnById(Column|string $columnId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('columns.id', $columnId)->with($relationships);
    }

    /**
     * Get column by ID.
     *
     * @param Column|string $columnId
     * @param array $relationships
     * @return Column|null
     */
    public function getColumnById(Column|string $columnId, array $relationships = []): Column|null
    {
        return $this->queryColumnById($columnId, $relationships)->first();
    }

    /**
     * Query columns by IDs.
     *
     * @param array<string> $columnId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryColumnsByIds($columnIds): Builder|Relation
    {
        return $this->query->whereIn('columns.id', $columnIds);
    }

    /**
     * Get columns by IDs.
     *
     * @param array<string> $columnId
     * @param string $relationships
     * @return Collection
     */
    public function getColumnsByIds($columnIds): Collection
    {
        return $this->queryColumnsByIds($columnIds)->get();
    }
}
