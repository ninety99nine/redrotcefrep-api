<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\Workflow;
use App\Traits\AuthTrait;
use App\Models\WorkflowStep;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\WorkflowStepResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class WorkflowStepRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show workflow steps.
     *
     * @param Workflow|string|null $storeId
     * @return WorkflowStepResources|workflowId
     */
    public function showWorkflowSteps(Workflow|string|null $workflowId = null): WorkflowStepResources|array
    {
        if($this->getQuery() == null) {
            if(is_null($workflowId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show workflow steps'];
                $this->setQuery(WorkflowStep::orderBy('position'));
            }else{
                $workflow = $workflowId instanceof Workflow ? $workflowId : Workflow::with(['store'])->find($workflowId);
                $store = $workflow->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show workflow steps'];
                    $this->setQuery($workflow->workflowSteps()->orderBy('position'));
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create workflow step.
     *
     * @param array $data
     * @return WorkflowStep|array
     */
    public function createWorkflowStep(array $data): WorkflowStep|array
    {
        $workflowId = $data['workflow_id'];
        $workflow = Workflow::with(['store'])->find($workflowId);
        $store = $workflow->store;

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create workflow steps'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'workflow_id' => $workflowId
        ]);

        $workflowStep = WorkflowStep::create($data);
        return $this->showCreatedResource($workflowStep);
    }

    /**
     * Delete workflow steps.
     *
     * @param array $data
     * @return array
     */
    public function deleteWorkflowSteps(array $data): array
    {
        $workflowId = $data['workflow_id'];

        if(is_null($workflowId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete workflow steps'];
            $this->setQuery(WorkflowStep::query());
        }else{

            $workflow = Workflow::with(['store'])->find($workflowId);
            $store = $workflow->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete workflow steps'];
                $this->setQuery($workflow->workflowSteps());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $workflowStepIds = $data['workflow_step_ids'];
        $workflowSteps = $this->getWorkflowsByIds($workflowStepIds);

        if($totalWorkflowSteps = $workflowSteps->count()) {

            foreach($workflowSteps as $workflowStep) {
                $workflowStep->delete();
            }

            return ['deleted' => true, 'message' => $totalWorkflowSteps . ($totalWorkflowSteps == 1 ? ' workflow step': ' workflow steps') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No workflow steps deleted'];
        }
    }

    /**
     * Update workflow step arrangement.
     *
     * @param array $data
     * @return array
     */
    public function updateWorkflowStepArrangement(array $data): array
    {
        $workflowId = $data['workflow_id'];
        $workflow = Workflow::with(['store'])->find($workflowId);
        $store = $workflow->store;

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update workflow step arrangement'];
            $this->setQuery($workflow->workflowSteps()->orderBy('position', 'asc'));
        }else{
            return ['message' => 'This store does not exist'];
        }

        $workflowStepIds = $data['workflow_step_ids'];

        $workflowSteps = $this->query->get();
        $originalWorkflowStepPositions = $workflowSteps->pluck('position', 'id');

        $arrangement = collect($workflowStepIds)->filter(function ($workflowId) use ($originalWorkflowStepPositions) {
            return collect($originalWorkflowStepPositions)->keys()->contains($workflowId);
        })->toArray();

        $movedWorkflowStepPositions = collect($arrangement)->mapWithKeys(function ($workflowId, $newPosition) use ($originalWorkflowStepPositions) {
            return [$workflowId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalWorkflowStepPositions = $originalWorkflowStepPositions->except(collect($movedWorkflowStepPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedWorkflowStepPositions) {
            return [$id => count($movedWorkflowStepPositions) + $index + 1];
        })->toArray();

        $workflowStepPositions = $movedWorkflowStepPositions + $adjustedOriginalWorkflowStepPositions;

        if(count($workflowStepPositions)) {

            DB::table('workflow_steps')
                ->where('workflow_id', $workflow->id)
                ->whereIn('id', array_keys($workflowStepPositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($workflowStepPositions), $workflowStepPositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Workflow step arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching workflow steps to update'];
    }

    /**
     * Show workflow step.
     *
     * @param string $workflowStepId
     * @return WorkflowStep|array|null
     */
    public function showWorkflowStep(string $workflowStepId): WorkflowStep|array|null
    {
        $workflowStep = $this->setQuery(WorkflowStep::with(['workflow.store'])->whereId($workflowStepId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($workflowStep) {
            $workflow = $workflowStep->workflow;
            if($workflow) {
                $store = $workflow->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show workflow step'];
                    if(!$this->checkIfHasRelationOnRequest('workflow')) $workflowStep->unsetRelation('workflow');
                    if(!$this->checkIfHasRelationOnRequest('store')) $workflowStep->unsetRelation('store');
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }else{
                return ['message' => 'This workflow does not exist'];
            }
        }

        return $this->showResourceExistence($workflowStep);
    }

    /**
     * Update workflow step.
     *
     * @param string $workflowStepId
     * @param array $data
     * @return WorkflowStep|array
     */
    public function updateWorkflowStep(string $workflowStepId, array $data): WorkflowStep|array
    {
        $workflowStep = WorkflowStep::with(['workflow.store'])->find($workflowStepId);

        if($workflowStep) {
            $workflow = $workflowStep->workflow;
            if($workflow) {
                $store = $workflow->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update workflow step'];
                    if(!$this->checkIfHasRelationOnRequest('workflow')) $workflowStep->unsetRelation('workflow');
                    if(!$this->checkIfHasRelationOnRequest('store')) $workflowStep->unsetRelation('store');
                }else{
                    return ['updated' => false, 'message' => 'This store does not exist'];
                }
            }else{
                return ['updated' => false, 'message' => 'This workflow does not exist'];
            }

            $workflowStep->update($data);
            return $this->showUpdatedResource($workflowStep);

        }else{
            return ['updated' => false, 'message' => 'This workflow step does not exist'];
        }
    }

    /**
     * Delete workflow step.
     *
     * @param string $workflowStepId
     * @return array
     */
    public function deleteWorkflowStep(string $workflowStepId): array
    {
        $workflowStep = WorkflowStep::with(['workflow.store'])->find($workflowStepId);

        if($workflowStep) {
            $workflow = $workflowStep->workflow;
            if($workflow) {
                $store = $workflow->store;
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete workflow step'];
                    if(!$this->checkIfHasRelationOnRequest('workflow')) $workflowStep->unsetRelation('workflow');
                    if(!$this->checkIfHasRelationOnRequest('store')) $workflowStep->unsetRelation('store');
                }else{
                    return ['deleted' => false, 'message' => 'This store does not exist'];
                }
            }else{
                return ['deleted' => false, 'message' => 'This workflow does not exist'];
            }

            $deleted = $workflowStep->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Workflow step deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Workflow step delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This workflow step does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query workflow step by ID.
     *
     * @param WorkflowStep|string $workflowStepId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryWorkflowById(WorkflowStep|string $workflowStepId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('workflow_steps.id', $workflowStepId)->with($relationships);
    }

    /**
     * Get workflow step by ID.
     *
     * @param WorkflowStep|string $workflowStepId
     * @param array $relationships
     * @return WorkflowStep|null
     */
    public function getWorkflowById(WorkflowStep|string $workflowStepId, array $relationships = []): WorkflowStep|null
    {
        return $this->queryWorkflowById($workflowStepId, $relationships)->first();
    }

    /**
     * Query workflow steps by IDs.
     *
     * @param array<string> $workflowStepId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryWorkflowsByIds($workflowStepIds): Builder|Relation
    {
        return $this->query->whereIn('workflow_steps.id', $workflowStepIds);
    }

    /**
     * Get workflow steps by IDs.
     *
     * @param array<string> $workflowStepId
     * @param string $relationships
     * @return Collection
     */
    public function getWorkflowsByIds($workflowStepIds): Collection
    {
        return $this->queryWorkflowsByIds($workflowStepIds)->get();
    }
}
