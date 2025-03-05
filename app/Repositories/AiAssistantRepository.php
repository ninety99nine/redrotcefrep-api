<?php

namespace App\Repositories;

use stdClass;
use App\Traits\AuthTrait;
use App\Models\AiAssistant;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\AiAssistantResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class AiAssistantRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show AI assistants.
     *
     * @return AiAssistantResources|array
     */
    public function showAiAssistants(): AiAssistantResources|array
    {
        if($this->getQuery() == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show AI assistants'];
            $this->setQuery(AiAssistant::query()->latest());
        }

        return $this->getOutput();
    }

    /**
     * Create AI assistant.
     *
     * @param array $data
     * @return AiAssistant|array
     */
    public function createAiAssistant(array $data): AiAssistant|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create AI assistants'];

        $aiAssistantExists = AiAssistant::whereUserId($data['user_id'])->exists();
        if($aiAssistantExists) return ['created' => false, 'message' => 'This user already has an AI assistant'];

        $aiAssistant = AiAssistant::create($data);
        return $this->showCreatedResource($aiAssistant);
    }

    /**
     * Delete AI assistants.
     *
     * @param array $aiAssistantIds
     * @return array
     */
    public function deleteAiAssistants(array $aiAssistantIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete AI assistants'];
        $aiAssistants = $this->setQuery(AiAssistant::query())->getAiAssistantsByIds($aiAssistantIds);

        if($totalAiAssistants = $aiAssistants->count()) {

            foreach($aiAssistants as $aiAssistant) {
                $aiAssistant->delete();
            }

            return ['deleted' => true, 'message' => $totalAiAssistants  .($totalAiAssistants  == 1 ? ' AI assistant': ' AI assistants') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No AI assistants deleted'];
        }
    }

    /**
     * Show AI assistant.
     *
     * @param AiAssistant|string|null $aiAssistantId
     * @return AiAssistant|array|null
     */
    public function showAiAssistant(AiAssistant|string|null $aiAssistantId = null): AiAssistant|array|null
    {
        if(($aiAssistant = $aiAssistantId) instanceof AiAssistant) {
            $aiAssistant = $this->applyEagerLoadingOnModel($aiAssistant);
        }else {
            $query = $this->getQuery() ?? AiAssistant::query();
            if($aiAssistantId) $query = $query->where('ai_assistants.id', $aiAssistantId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $aiAssistant = $this->query->first();
        }

        return $this->showResourceExistence($aiAssistant);
    }

    /**
     * Update AI assistant.
     *
     * @param string $aiAssistantId
     * @param array $data
     * @return AiAssistant|array
     */
    public function updateAiAssistant(string $aiAssistantId, array $data): AiAssistant|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update AI assistant'];
        $aiAssistant = AiAssistant::find($aiAssistantId);

        if($aiAssistant) {

            $aiAssistant->update($data);
            return $this->showUpdatedResource($aiAssistant);

        }else{
            return ['updated' => false, 'message' => 'This AI assistant does not exist'];
        }
    }

    /**
     * Delete AI assistant.
     *
     * @param string $aiAssistantId
     * @return array
     */
    public function deleteAiAssistant(string $aiAssistantId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete AI assistant'];
        $aiAssistant = AiAssistant::find($aiAssistantId);

        if($aiAssistant) {
            $deleted = $aiAssistant->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'AI assistant deleted'];
            }else{
                return ['deleted' => false, 'message' => 'AI assistant delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This AI assistant does not exist'];
        }
    }

    /**
     * Assess AI assistant usage eligibility.
     *
     * @param string $aiAssistantId
     * @return AiAssistant|array
     */
    public function assessAiAssistantUsageEligibility(string $aiAssistantId): AiAssistant|array
    {
        $aiAssistant = AiAssistant::find($aiAssistantId);

        if($aiAssistant) {

            $usageEligibility = $this->getAiMessageRepository()->assessUsageEligibility($aiAssistant);

            return [
                'message' => $usageEligibility->message,
                'can_top_up' => $usageEligibility->can_top_up,
                'can_subscribe' => $usageEligibility->can_subscribe
            ];

        }else{
            return ['message' => 'This AI assistant does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query AI assistant by ID.
     *
     * @param string $aiAssistantId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryAiAssistantById(string $aiAssistantId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('ai_assistants.id', $aiAssistantId)->with($relationships);
    }

    /**
     * Get AI assistant by ID.
     *
     * @param string $aiAssistantId
     * @param array $relationships
     * @return AiAssistant|null
     */
    public function getAiAssistantById(string $aiAssistantId, array $relationships = []): AiAssistant|null
    {
        return $this->queryAiAssistantById($aiAssistantId, $relationships)->first();
    }

    /**
     * Query AI assistants by IDs.
     *
     * @param array<string> $aiAssistantId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryAiAssistantsByIds($aiAssistantIds): Builder|Relation
    {
        return $this->query->whereIn('ai_assistants.id', $aiAssistantIds);
    }

    /**
     * Get AI assistants by IDs.
     *
     * @param array<string> $aiAssistantId
     * @param string $relationships
     * @return Collection
     */
    public function getAiAssistantsByIds($aiAssistantIds): Collection
    {
        return $this->queryAiAssistantsByIds($aiAssistantIds)->get();
    }
}
