<?php

namespace App\Repositories;

use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use App\Models\AiMessageCategory;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\AiMessageCategoryResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class AiMessageCategoryRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show AI message categories.
     *
     * @return AiMessageCategoryResources|array
     */
    public function showAiMessageCategories(): AiMessageCategoryResources|array
    {
        $this->setQuery(AiMessageCategory::query()->latest());
        return $this->getOutput();
    }

    /**
     * Create AI message category.
     *
     * @param array $data
     * @return AiMessageCategory|array
     */
    public function createAiMessageCategory(array $data): AiMessageCategory|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create AI message categories'];

        $aiMessageCategory = AiMessageCategory::create($data);
        return $this->showCreatedResource($aiMessageCategory);
    }

    /**
     * Delete AI message categories.
     *
     * @param array $aiMessageCategoryIds
     * @return array
     */
    public function deleteAiMessageCategories(array $aiMessageCategoryIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete AI message categories'];

        $aiMessageCategories = $this->setQuery(AiMessageCategory::query())->getAiMessageCategoriesByIds($aiMessageCategoryIds);

        if($totalAiMessageCategories = $aiMessageCategories->count()) {

            foreach($aiMessageCategories as $aiMessageCategory) {
                $aiMessageCategory->delete();
            }

            return ['deleted' => true, 'message' => $totalAiMessageCategories  .($totalAiMessageCategories  == 1 ? ' AI message category': ' AI message categories') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No AI message categories deleted'];
        }
    }

    /**
     * Show AI message category.
     *
     * @param AiMessageCategory|string|null $aiMessageCategoryId
     * @return AiMessageCategory|array|null
     */
    public function showAiMessageCategory(AiMessageCategory|string|null $aiMessageCategoryId = null): AiMessageCategory|array|null
    {
        if(($aiMessageCategory = $aiMessageCategoryId) instanceof AiMessageCategory) {
            $aiMessageCategory = $this->applyEagerLoadingOnModel($aiMessageCategory);
        }else {
            $query = $this->getQuery() ?? AiMessageCategory::query();
            if($aiMessageCategoryId) $query = $query->where('ai_message_categories.id', $aiMessageCategoryId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $aiMessageCategory = $this->query->first();
        }

        return $this->showResourceExistence($aiMessageCategory);
    }

    /**
     * Update AI message category.
     *
     * @param AiMessageCategory|string $aiMessageCategoryId
     * @param array $data
     * @return AiMessageCategory|array
     */
    public function updateAiMessageCategory(AiMessageCategory|string $aiMessageCategoryId, array $data): AiMessageCategory|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update AI message category'];

        $aiMessageCategory = $aiMessageCategoryId instanceof AiMessageCategory ? $aiMessageCategoryId : AiMessageCategory::find($aiMessageCategoryId);

        if($aiMessageCategory) {

            $aiMessageCategory->update($data);
            return $this->showUpdatedResource($aiMessageCategory);

        }else{
            return ['updated' => false, 'message' => 'This AI message category does not exist'];
        }
    }

    /**
     * Delete AI message category.
     *
     * @param AiMessageCategory|string $aiMessageCategoryId
     * @return array
     */
    public function deleteAiMessageCategory(AiMessageCategory|string $aiMessageCategoryId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete AI message category'];

        $aiMessageCategory = $aiMessageCategoryId instanceof AiMessageCategory ? $aiMessageCategoryId : AiMessageCategory::find($aiMessageCategoryId);

        if($aiMessageCategory) {

            $deleted = $aiMessageCategory->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'AI message category deleted'];
            }else{
                return ['deleted' => false, 'message' => 'AI message category delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This AI message category does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query AI message category by ID.
     *
     * @param string $aiMessageCategoryId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryAiMessageCategoryById(string $aiMessageCategoryId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('ai_message_categories.id', $aiMessageCategoryId)->with($relationships);
    }

    /**
     * Get AI message category by ID.
     *
     * @param string $aiMessageCategoryId
     * @param array $relationships
     * @return AiMessageCategory|null
     */
    public function getAiMessageCategoryById(string $aiMessageCategoryId, array $relationships = []): AiMessageCategory|null
    {
        return $this->queryAiMessageCategoryById($aiMessageCategoryId, $relationships)->first();
    }

    /**
     * Query AI message categories by IDs.
     *
     * @param array<string> $aiMessageCategoryId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryAiMessageCategoriesByIds($aiMessageCategoryIds): Builder|Relation
    {
        return $this->query->whereIn('ai_message_categories.id', $aiMessageCategoryIds);
    }

    /**
     * Get AI message categories by IDs.
     *
     * @param array<string> $aiMessageCategoryId
     * @param string $relationships
     * @return Collection
     */
    public function getAiMessageCategoriesByIds($aiMessageCategoryIds): Collection
    {
        return $this->queryAiMessageCategoriesByIds($aiMessageCategoryIds)->get();
    }
}
