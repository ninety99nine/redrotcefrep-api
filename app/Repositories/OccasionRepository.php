<?php

namespace App\Repositories;

use App\Models\Occasion;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\OccasionResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class OccasionRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show occasions.
     *
     * @return OccasionResources|array
     */
    public function showOccasions(): OccasionResources|array
    {
        $this->setQuery(Occasion::query()->latest());
        return $this->getOutput();
    }

    /**
     * Create occasion.
     *
     * @param array $data
     * @return Occasion|array
     */
    public function createOccasion(array $data): Occasion|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create occasions'];

        $occasion = Occasion::create($data);
        return $this->showCreatedResource($occasion);
    }

    /**
     * Delete occasions.
     *
     * @param array $occasionIds
     * @return array
     */
    public function deleteOccasions(array $occasionIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete occasions'];

        $occasions = $this->setQuery(Occasion::query())->getOccasionsByIds($occasionIds);

        if($totalOccasions = $occasions->count()) {

            foreach($occasions as $occasion) {
                $occasion->delete();
            }

            return ['deleted' => true, 'message' => $totalOccasions  .($totalOccasions  == 1 ? ' occasion': ' occasions') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No occasions deleted'];
        }
    }

    /**
     * Show occasion.
     *
     * @param Occasion|string|null $occasionId
     * @return Occasion|array|null
     */
    public function showOccasion(Occasion|string|null $occasionId = null): Occasion|array|null
    {
        if(($occasion = $occasionId) instanceof Occasion) {
            $occasion = $this->applyEagerLoadingOnModel($occasion);
        }else {
            $query = $this->getQuery() ?? Occasion::query();
            if($occasionId) $query = $query->where('occasions.id', $occasionId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $occasion = $this->query->first();
        }

        return $this->showResourceExistence($occasion);
    }

    /**
     * Update occasion.
     *
     * @param Occasion|string $occasionId
     * @param array $data
     * @return Occasion|array
     */
    public function updateOccasion(Occasion|string $occasionId, array $data): Occasion|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update occasion'];

        $occasion = $occasionId instanceof Occasion ? $occasionId : Occasion::find($occasionId);

        if($occasion) {

            $occasion->update($data);
            return $this->showUpdatedResource($occasion);

        }else{
            return ['updated' => false, 'message' => 'This occasion does not exist'];
        }
    }

    /**
     * Delete occasion.
     *
     * @param Occasion|string $occasionId
     * @return array
     */
    public function deleteOccasion(Occasion|string $occasionId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete occasion'];

        $occasion = $occasionId instanceof Occasion ? $occasionId : Occasion::find($occasionId);

        if($occasion) {

            $deleted = $occasion->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'occasion deleted'];
            }else{
                return ['deleted' => false, 'message' => 'occasion delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This occasion does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query occasion by ID.
     *
     * @param string $occasionId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryOccasionById(string $occasionId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('occasions.id', $occasionId)->with($relationships);
    }

    /**
     * Get occasion by ID.
     *
     * @param string $occasionId
     * @param array $relationships
     * @return Occasion|null
     */
    public function getOccasionById(string $occasionId, array $relationships = []): Occasion|null
    {
        return $this->queryOccasionById($occasionId, $relationships)->first();
    }

    /**
     * Query occasions by IDs.
     *
     * @param array<string> $occasionId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryOccasionsByIds($occasionIds): Builder|Relation
    {
        return $this->query->whereIn('occasions.id', $occasionIds);
    }

    /**
     * Get occasions by IDs.
     *
     * @param array<string> $occasionId
     * @param string $relationships
     * @return Collection
     */
    public function getOccasionsByIds($occasionIds): Collection
    {
        return $this->queryOccasionsByIds($occasionIds)->get();
    }
}
