<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\Workflow;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\WorkflowResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class WorkflowRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show workflows.
     *
     * @param Store|string|null $storeId
     * @return WorkflowResources|array
     */
    public function showWorkflows(Store|string|null $storeId = null): WorkflowResources|array
    {
        if($this->getQuery() == null) {
            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show workflows'];
                $this->setQuery(Workflow::orderBy('position'));
            }else{
                $store = $storeId instanceof Store ? $storeId : Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show workflows'];
                    $this->setQuery($store->workflows()->orderBy('position'));
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create workflow.
     *
     * @param array $data
     * @return Workflow|array
     */
    public function createWorkflow(array $data): Workflow|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create workflows'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'store_id' => $storeId
        ]);

        $workflow = Workflow::create($data);

        $this->updateWorkflowArrangement([
            'store_id' => $storeId,
            'workflow_ids' => [
                $workflow->id
            ]
        ]);

        return $this->showCreatedResource($workflow);
    }

    /**
     * Delete workflows.
     *
     * @param array $data
     * @return array
     */
    public function deleteWorkflows(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete workflows'];
            $this->setQuery(Workflow::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete workflows'];
                $this->setQuery($store->workflows());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $workflowIds = $data['workflow_ids'];
        $workflows = $this->getWorkflowsByIds($workflowIds);

        if($totalWorkflows = $workflows->count()) {

            foreach($workflows as $workflow) {
                $workflow->delete();
            }

            return ['deleted' => true, 'message' => $totalWorkflows . ($totalWorkflows == 1 ? ' workflow': ' workflows') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No workflows deleted'];
        }
    }

    /**
     * Show workflow options.
     *
     * @return array
     */
    public function showWorkflowOptions(): array
    {
        return [

        ];
    }

    /**
     * Update workflow arrangement.
     *
     * @param array $data
     * @return array
     */
    public function updateWorkflowArrangement(array $data): array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update workflow arrangement'];
            $this->setQuery($store->workflows()->orderBy('position', 'asc'));
        }else{
            return ['message' => 'This store does not exist'];
        }

        $workflowIds = $data['workflow_ids'];

        $workflows = $this->query->get();
        $originalWorkflowPositions = $workflows->pluck('position', 'id');

        $arrangement = collect($workflowIds)->filter(function ($WorkflowId) use ($originalWorkflowPositions) {
            return collect($originalWorkflowPositions)->keys()->contains($WorkflowId);
        })->toArray();

        $movedWorkflowPositions = collect($arrangement)->mapWithKeys(function ($WorkflowId, $newPosition) use ($originalWorkflowPositions) {
            return [$WorkflowId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalWorkflowPositions = $originalWorkflowPositions->except(collect($movedWorkflowPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedWorkflowPositions) {
            return [$id => count($movedWorkflowPositions) + $index + 1];
        })->toArray();

        $workflowPositions = $movedWorkflowPositions + $adjustedOriginalWorkflowPositions;

        if(count($workflowPositions)) {

            DB::table('workflows')
                ->where('store_id', $store->id)
                ->whereIn('id', array_keys($workflowPositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($workflowPositions), $workflowPositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Workflow arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching workflows to update'];
    }

    /**
     * Show workflow.
     *
     * @param string $workflowId
     * @return Workflow|array|null
     */
    public function showWorkflow(string $workflowId): Workflow|array|null
    {
        $workflow = $this->setQuery(Workflow::with(['store'])->whereId($workflowId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($workflow) {
            $store = $workflow->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show workflow'];
                if(!$this->checkIfHasRelationOnRequest('store')) $workflow->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($workflow);
    }

    /**
     * Update workflow.
     *
     * @param string $workflowId
     * @param array $data
     * @return Workflow|array
     */
    public function updateWorkflow(string $workflowId, array $data): Workflow|array
    {
        $workflow = Workflow::with(['store'])->find($workflowId);

        if($workflow) {
            $store = $workflow->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update workflow'];
                if(!$this->checkIfHasRelationOnRequest('store')) $workflow->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $workflow->update($data);
            return $this->showUpdatedResource($workflow);

        }else{
            return ['updated' => false, 'message' => 'This workflow does not exist'];
        }
    }

    /**
     * Delete workflow.
     *
     * @param string $workflowId
     * @return array
     */
    public function deleteWorkflow(string $workflowId): array
    {
        $workflow = Workflow::with(['store'])->find($workflowId);

        if($workflow) {
            $store = $workflow->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete workflow'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $workflow->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Workflow deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Workflow delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This workflow does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query workflow by ID.
     *
     * @param Workflow|string $workflowId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryWorkflowById(Workflow|string $workflowId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('workflows.id', $workflowId)->with($relationships);
    }

    /**
     * Get workflow by ID.
     *
     * @param Workflow|string $workflowId
     * @param array $relationships
     * @return Workflow|null
     */
    public function getWorkflowById(Workflow|string $workflowId, array $relationships = []): Workflow|null
    {
        return $this->queryWorkflowById($workflowId, $relationships)->first();
    }

    /**
     * Query workflows by IDs.
     *
     * @param array<string> $workflowId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryWorkflowsByIds($workflowIds): Builder|Relation
    {
        return $this->query->whereIn('workflows.id', $workflowIds);
    }

    /**
     * Get workflows by IDs.
     *
     * @param array<string> $workflowId
     * @param string $relationships
     * @return Collection
     */
    public function getWorkflowsByIds($workflowIds): Collection
    {
        return $this->queryWorkflowsByIds($workflowIds)->get();
    }
}
