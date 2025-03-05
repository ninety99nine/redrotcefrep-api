<?php

namespace App\Repositories;

use App\Models\AiLesson;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\AiLessonResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class AiLessonRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show AI lessons.
     *
     * @return AiLessonResources|array
     */
    public function showAiLessons(array $data = []): AiLessonResources|array
    {
        if($this->getQuery() == null) {
            $this->setQuery(AiLesson::latest());
        }

        return $this->getOutput();
    }

    /**
     * Create AI lesson.
     *
     * @param array $data
     * @return AiLesson|array
     */
    public function createAiLesson(array $data): AiLesson|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create AI lessons'];

        $aiLesson = AiLesson::create($data);
        return $this->showCreatedResource($aiLesson);
    }

    /**
     * Delete AI lessons.
     *
     * @param array $aiLessonIds
     * @return array
     */
    public function deleteAiLessons(array $aiLessonIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'lesson' => 'You do not have permission to delete AI lessons'];
        $aiLessons = $this->setQuery(AiLesson::query())->getAiLessonsByIds($aiLessonIds);

        if($totalAiLessons = $aiLessons->count()) {

            foreach($aiLessons as $aiLesson) {
                $aiLesson->delete();
            }

            return ['deleted' => true, 'lesson' => $totalAiLessons  .($totalAiLessons  == 1 ? ' AI lesson': ' AI lessons') . ' deleted'];

        }else{
            return ['deleted' => false, 'lesson' => 'No AI lessons deleted'];
        }
    }

    /**
     * Show AI lesson.
     *
     * @param AiLesson|string|null $aiLessonId
     * @return AiLesson|array|null
     */
    public function showAiLesson(AiLesson|string|null $aiLessonId = null): AiLesson|array|null
    {
        if(($aiLesson = $aiLessonId) instanceof AiLesson) {
            $aiLesson = $this->applyEagerLoadingOnModel($aiLesson);
        }else {
            $query = $this->getQuery() ?? AiLesson::query();
            if($aiLessonId) $query = $query->where('ai_lessons.id', $aiLessonId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $aiLesson = $this->query->first();
        }

        return $this->showResourceExistence($aiLesson);
    }

    /**
     * Update AI lesson.
     *
     * @param string $aiLessonId
     * @param array $data
     * @return AiLesson|array
     */
    public function updateAiLesson(string $aiLessonId, array $data): AiLesson|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update AI lesson'];
        $aiLesson = AiLesson::find($aiLessonId);

        if($aiLesson) {

            $aiLesson->update($data);
            return $this->showUpdatedResource($aiLesson);

        }else{
            return ['updated' => false, 'message' => 'This AI lesson does not exist'];
        }
    }

    /**
     * Delete AI lesson.
     *
     * @param string $aiLessonId
     * @return array
     */
    public function deleteAiLesson(string $aiLessonId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'lesson' => 'You do not have permission to delete AI lesson'];
        $aiLesson = AiLesson::find($aiLessonId);

        if($aiLesson) {
            $deleted = $aiLesson->delete();

            if ($deleted) {
                return ['deleted' => true, 'lesson' => 'AI lesson deleted'];
            }else{
                return ['deleted' => false, 'lesson' => 'AI lesson delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'lesson' => 'This AI lesson does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query AI lesson by ID.
     *
     * @param string $aiLessonId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryAiLessonById(string $aiLessonId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('ai_lessons.id', $aiLessonId)->with($relationships);
    }

    /**
     * Get AI lesson by ID.
     *
     * @param string $aiLessonId
     * @param array $relationships
     * @return AiLesson|null
     */
    public function getAiLessonById(string $aiLessonId, array $relationships = []): AiLesson|null
    {
        return $this->queryAiLessonById($aiLessonId, $relationships)->first();
    }

    /**
     * Query AI lessons by IDs.
     *
     * @param array<string> $aiLessonId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryAiLessonsByIds($aiLessonIds): Builder|Relation
    {
        return $this->query->whereIn('ai_lessons.id', $aiLessonIds);
    }

    /**
     * Get AI lessons by IDs.
     *
     * @param array<string> $aiLessonId
     * @param string $relationships
     * @return Collection
     */
    public function getAiLessonsByIds($aiLessonIds): Collection
    {
        return $this->queryAiLessonsByIds($aiLessonIds)->get();
    }
}
