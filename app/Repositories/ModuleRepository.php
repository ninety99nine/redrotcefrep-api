<?php

namespace App\Repositories;

use App\Models\Column;
use App\Models\Module;
use App\Traits\AuthTrait;
use Illuminate\Support\Str;
use App\Traits\Base\BaseTrait;
use App\Enums\RequestFileName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Http\Resources\ModuleResources;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\MediaFileResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class ModuleRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show modules.
     *
     * @param array $data
     * @return ModuleResources|array
     */
    public function showModules(array $data = []): ModuleResources|array
    {
        if($this->getQuery() == null) {

            $columnId = isset($data['column_id']) ? $data['column_id'] : null;

            if(is_null($columnId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show modules'];
                $this->setQuery(Module::latest());
            }else{

                $column = Column::whereId($columnId)->with(['store'])->first();

                if($column) {

                    $store = $column->store;

                    if($store) {

                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show modules'];
                        $this->setQuery($column->modules()->orderByPivot('position'));

                    }else{
                        return ['message' => 'This store does not exist'];
                    }

                }else{
                    return ['message' => 'This column does not exist'];
                }
            }
        }

        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /**
     * Create module.
     *
     * @param array $data
     * @return Module|array
     */
    public function createModule(array $data): Module|array
    {
        $columnId = $data['column_id'];
        $column = Column::whereId($columnId)->with(['store'])->first();

        if($column) {

            $store = $column->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create modules'];
            }else{
                return ['created' => false, 'message' => 'This store does not exist'];
            }

        }else{
            return ['created' => false, 'message' => 'This column does not exist'];
        }

        $data = [
            ...$data,
            'store_id' => $column->store_id
        ];

        $module = Module::create($data);

        $column->modules()->attach($module->id, [
            'id' => Str::uuid(),
            'visible' => $data['visible'] ?? 0
        ]);

        return $this->showCreatedResource($module);
    }

    /**
     * Delete modules.
     *
     * @param array $data
     * @return array
     */
    public function deleteModules(array $data): array
    {
        $columnId = $data['column_id'];

        if(is_null($columnId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete modules'];
            $this->setQuery(Module::query());
        }else{

            $column = Column::whereId($columnId)->with(['store'])->first();

            if($column) {

                $store = $column->store;

                if($store) {

                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete modules'];
                    $this->setQuery($column->modules());

                }else{
                    return ['deleted' => false, 'message' => 'This store does not exist'];
                }

            }else{
                return ['deleted' => false, 'message' => 'This column does not exist'];
            }
        }

        $moduleIds = $data['module_ids'];
        $modules = $this->getModulesByIds($moduleIds);

        if($totalModules = $modules->count()) {

            foreach($modules as $module) {
                $module->delete();
            }

            return ['deleted' => true, 'message' => $totalModules . ($totalModules == 1 ? ' module': ' modules') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No modules deleted'];
        }
    }

    /**
     * Update module visibility
     *
     * @param array $data
     * @return array
     */
    public function updateModuleVisibility(array $data): array
    {
        $columnId = $data['column_id'];
        $column = Column::whereId($columnId)->with(['store'])->first();

        if ($column) {
            $store = $column->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if (!$isAuthourized) {
                    return ['message' => 'You do not have permission to update module visibility'];
                }
                $this->setQuery($column->modules());
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This column does not exist'];
        }

        $modules = $this->query->get();
        $moduleIdsAndVisibility = $data['visibility'];

        $existingModuleIdsAndVisibility = $modules->map(function ($module) {
            return ['id' => $module->id, 'visible' => $module->pivot->visible];
        });

        $newModuleIdsAndVisibility = collect($moduleIdsAndVisibility)->filter(function ($item) use ($existingModuleIdsAndVisibility) {
            return $existingModuleIdsAndVisibility->contains('id', $item['id']);
        })->toArray();

        $oldModuleIdsAndVisibility = collect($existingModuleIdsAndVisibility)->filter(function ($item) use ($moduleIdsAndVisibility) {
            return collect($moduleIdsAndVisibility)->doesntContain('id', $item['id']);
        })->toArray();

        $finalModuleIdsAndVisibility = $newModuleIdsAndVisibility + $oldModuleIdsAndVisibility;
        $finalModuleIdsAndVisibility = collect($finalModuleIdsAndVisibility)->mapWithKeys(fn($item) => [$item['id'] => $item['visible'] ? 1 : 0])->toArray();

        if (count($finalModuleIdsAndVisibility)) {
            foreach ($finalModuleIdsAndVisibility as $moduleId => $visibility) {
                DB::table('column_module')
                    ->where('column_id', $column->id)
                    ->where('module_id', $moduleId)
                    ->update(['visible' => $visibility]);
            }

            return ['updated' => true, 'message' => 'Module visibility has been updated'];
        }

        return ['updated' => false, 'message' => 'No matching modules to update'];
    }

    /**
     * Update module arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateModuleArrangement(array $data): array
    {
        $columnId = $data['column_id'];
        $column = Column::whereId($columnId)->with(['store'])->first();

        if ($column) {
            $store = $column->store;
            if ($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to update module arrangement'];
                $this->setQuery($column->modules()->orderByPivot('position', 'asc'));
            } else {
                return ['message' => 'This store does not exist'];
            }
        } else {
            return ['message' => 'This column does not exist'];
        }

        $modules = $this->query->get();
        $originalModulePositions = $modules->pluck('pivot.position', 'id');

        $moduleIds = $data['module_ids'];

        $arrangement = collect($moduleIds)->filter(function ($moduleId) use ($originalModulePositions) {
            return collect($originalModulePositions)->keys()->contains($moduleId);
        })->toArray();

        $movedModulePositions = collect($arrangement)->mapWithKeys(function ($moduleId, $newPosition) use ($originalModulePositions) {
            return [$moduleId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalModulePositions = $originalModulePositions->except(collect($movedModulePositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedModulePositions) {
            return [$id => count($movedModulePositions) + $index + 1];
        })->toArray();

        $modulePositions = $movedModulePositions + $adjustedOriginalModulePositions;

        if(count($modulePositions)) {

            foreach ($modulePositions as $moduleId => $position) {
                DB::table('column_module')
                    ->where('column_id', $column->id)
                    ->where('module_id', $moduleId)
                    ->update(['position' => $position]);
            }

            return ['updated' => true, 'message' => 'Module arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching modules to update'];
    }

    /**
     * Show module.
     *
     * @param string $moduleId
     * @return Module|array|null
     */
    public function showModule(string $moduleId): Module|array|null
    {
        $module = Module::find($moduleId);
        return $this->showResourceExistence($module);
    }

    /**
     * Update module.
     *
     * @param string $moduleId
     * @param array $data
     * @return Module|array
     */
    public function updateModule(string $moduleId, array $data): Module|array
    {
        $module = Module::with(['store'])->find($moduleId);

        if($module) {
            $store = $module->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update module'];
                if(!$this->checkIfHasRelationOnRequest('store')) $module->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $module->update($data);
            return $this->showUpdatedResource($module);

        }else{
            return ['updated' => false, 'message' => 'This module does not exist'];
        }
    }

    /**
     * Delete module.
     *
     * @param string $moduleId
     * @return array
     */
    public function deleteModule(string $moduleId): array
    {
        $module = Module::with(['store'])->find($moduleId);

        if($module) {
            $store = $module->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete module'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $module->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Module deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Module delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This module does not exist'];
        }
    }

    /**
     * Show module media files.
     *
     * @param string $moduleId
     * @return MediaFileResources|array
     */
    public function showModuleMediaFiles(string $moduleId): MediaFileResources|array
    {
        $module = Module::with(['store'])->find($moduleId);

        if($module) {
            $store = $module->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show module media files'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($module->mediaFiles())->showMediaFiles();
        }else{
            return ['message' => 'This module does not exist'];
        }
    }

    /**
     * Create module media file(s).
     *
     * @param string $moduleId
     * @return array
     */
    public function createModuleMediaFile(string $moduleId): array
    {
        $module = Module::with(['store'])->find($moduleId);

        if($module) {
            $store = $module->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to create module media files'];
            }else{
                return ['created' => false, 'message' => 'This store does not exist'];
            }

            return $this->getMediaFileRepository()->authourize()->createMediaFile(RequestFileName::MODULE_FILE, $module);

        }else{
            return ['created' => false, 'message' => 'This module does not exist'];
        }
    }

    /**
     * Show module media file.
     *
     * @param string $moduleId
     * @param string $mediaFileId
     * @return array
     */
    public function showModuleMediaFile(string $moduleId, string $mediaFileId): array
    {
        $module = Module::with(['store'])->find($moduleId);

        if($module) {
            $store = $module->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show module media file'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($module->mediaFiles())->showMediaFile($mediaFileId);
        }else{
            return ['message' => 'This module does not exist'];
        }
    }

    /**
     * Update module media file.
     *
     * @param string $moduleId
     * @param string $mediaFileId
     * @return array
     */
    public function updateModuleMediaFile(string $moduleId, string $mediaFileId): array
    {
        $module = Module::with(['store'])->find($moduleId);

        if($module) {
            $store = $module->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to update module media file'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->authourize()->setQuery($module->mediaFiles())->updateMediaFile($mediaFileId);
        }else{
            return ['message' => 'This module does not exist'];
        }
    }

    /**
     * Delete module media file.
     *
     * @param string $moduleId
     * @param string $mediaFileId
     * @return array
     */
    public function deleteModuleMediaFile(string $moduleId, string $mediaFileId): array
    {
        $module = Module::with(['store'])->find($moduleId);

        if($module) {
            $store = $module->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to delete module media file'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($module->mediaFiles())->deleteMediaFile($mediaFileId);
        }else{
            return ['message' => 'This module does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query module by ID.
     *
     * @param Module|string $moduleId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryModuleById(Module|string $moduleId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('modules.id', $moduleId)->with($relationships);
    }

    /**
     * Get module by ID.
     *
     * @param Module|string $moduleId
     * @param array $relationships
     * @return Module|null
     */
    public function getModuleById(Module|string $moduleId, array $relationships = []): Module|null
    {
        return $this->queryModuleById($moduleId, $relationships)->first();
    }

    /**
     * Query modules by IDs.
     *
     * @param array<string> $moduleId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryModulesByIds($moduleIds): Builder|Relation
    {
        return $this->query->whereIn('modules.id', $moduleIds);
    }

    /**
     * Get modules by IDs.
     *
     * @param array<string> $moduleId
     * @param string $relationships
     * @return Collection
     */
    public function getModulesByIds($moduleIds): Collection
    {
        return $this->queryModulesByIds($moduleIds)->get();
    }
}
