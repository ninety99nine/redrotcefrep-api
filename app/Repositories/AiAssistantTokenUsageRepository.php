<?php

namespace App\Repositories;

use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use App\Repositories\BaseRepository;
use App\Models\AiAssistantTokenUsage;
use App\Http\Resources\AiAssistantTokenUsageResources;

class AiAssistantTokenUsageRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show AI Assistant token usages.
     *
     * @param array $data
     * @return AiAssistantTokenUsageResources|array
     */
    public function showAiAssistantTokenUsages(array $data = []): AiAssistantTokenUsageResources|array
    {
        if($this->getQuery() == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show AI Assistant token usage'];
            $this->setQuery(AiAssistantTokenUsage::query()->latest());
        }

        return $this->getOutput();
    }

    /**
     * Show AI Assistant token usage.
     *
     * @param AiAssistantTokenUsage|string|null $aiAssistantTokenUsageId
     * @return AiAssistantTokenUsage|array|null
     */
    public function showAiAssistantTokenUsage(AiAssistantTokenUsage|string|null $aiAssistantTokenUsageId = null): AiAssistantTokenUsage|array|null
    {
        if(($aiAssistantTokenUsage = $aiAssistantTokenUsageId) instanceof AiAssistantTokenUsage) {
            $aiAssistantTokenUsage = $this->applyEagerLoadingOnModel($aiAssistantTokenUsage);
        }else {
            $query = $this->getQuery() ?? AiAssistantTokenUsage::query();
            if($aiAssistantTokenUsageId) $query = $query->where('ai_assistant_token_usage.id', $aiAssistantTokenUsageId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $aiAssistantTokenUsage = $this->query->first();
        }

        return $this->showResourceExistence($aiAssistantTokenUsage);
    }

    /***********************************************
     *            MISCELLANEOUS METHODS           *
     **********************************************/
}
