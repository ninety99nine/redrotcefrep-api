<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\Review;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use App\Http\Resources\ReviewResources;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ReviewRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show reviews.
     *
     * @param array $data
     * @return ReviewResources|array
     */
    public function showReviews(array $data = []): ReviewResources|array
    {
        if($this->getQuery() == null) {

            $userId = isset($data['user_id']) ? $data['user_id'] : null;
            $storeId = isset($data['store_id']) ? $data['store_id'] : null;
            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show reviews'];
                $this->setQuery(Review::latest());
            }else if($storeId) {
                $store = Store::find($storeId);
                if($store) {
                    $this->setQuery($store->reviews()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }else {
                $user = in_array($userId, [request()->current_user->id, null]) ? request()->current_user : User::find($userId);

                if($user) {
                    $isAuthourized = $this->isAuthourized() || $user->id == request()->auth_user->id;
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show reviews'];
                }else{
                    return ['message' => 'This user does not exist'];
                }

                if($association == Association::TEAM_MEMBER) {
                    $this->setQuery(Review::whereHas('store.teamMembersWhoJoined', function ($query) use ($user) {
                        $query->where('user_store_association.user_id', $user->id);
                    }));
                }else{
                    $this->setQuery($user->reviews()->latest());
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create review.
     *
     * @param array $data
     * @return Review|array
     */
    public function createReview(array $data): Review|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create reviews'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'user_id' => request()->current_user->id,
            'store_id' => $storeId
        ]);

        $review = Review::create($data);
        return $this->showCreatedResource($review);
    }

    /**
     * Delete reviews.
     *
     * @param array $data
     * @return array
     */
    public function deleteReviews(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete reviews'];
            $this->setQuery(Review::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete reviews'];
                $this->setQuery($store->reviews());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $reviewIds = $data['review_ids'];
        $reviews = $this->getReviewsByIds($reviewIds);

        if($totalReviews = $reviews->count()) {

            foreach($reviews as $review) {
                $review->delete();
            }

            return ['deleted' => true, 'message' => $totalReviews . ($totalReviews == 1 ? ' review': ' reviews') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No reviews deleted'];
        }
    }

    /**
     * Show review rating options.
     *
     * @return array
     */
    public function showReviewRatingOptions(): array
    {
        $ratingOptions = ['very bad', 'bad', 'ok', 'good', 'very good'];

        $ratingOptions = collect($ratingOptions)->map(function($name, $index) {
            return [
                'name' => ucwords($name),
                'rating' => $index + 1
            ];
        });

        return [
            'rating_subjects' => Review::SUBJECTS(),
            'rating_options' => $ratingOptions
        ];
    }

    /**
     * Show review.
     *
     * @param string $reviewId
     * @return Review|array|null
     */
    public function showReview(string $reviewId): Review|array|null
    {
        $review = $this->setQuery(Review::with(['store'])->whereId($reviewId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($review) {
            $store = $review->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show review'];
                if(!$this->checkIfHasRelationOnRequest('store')) $review->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($review);
    }

    /**
     * Update review.
     *
     * @param string $reviewId
     * @param array $data
     * @return Review|array
     */
    public function updateReview(string $reviewId, array $data): Review|array
    {
        $review = Review::with(['store'])->find($reviewId);

        if($review) {
            $store = $review->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update review'];
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $review->update($data);
            return $this->showUpdatedResource($review);

        }else{
            return ['updated' => false, 'message' => 'This review does not exist'];
        }
    }

    /**
     * Delete review.
     *
     * @param string $reviewId
     * @return array
     */
    public function deleteReview(string $reviewId): array
    {
        $review = Review::with(['store'])->find($reviewId);

        if($review) {
            $store = $review->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete review'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $review->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Review deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Review delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This review does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query review by ID.
     *
     * @param Review|string $reviewId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryReviewById(Review|string $reviewId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('reviews.id', $reviewId)->with($relationships);
    }

    /**
     * Get review by ID.
     *
     * @param Review|string $reviewId
     * @param array $relationships
     * @return Review|null
     */
    public function getReviewById(Review|string $reviewId, array $relationships = []): Review|null
    {
        return $this->queryReviewById($reviewId, $relationships)->first();
    }

    /**
     * Query reviews by IDs.
     *
     * @param array<string> $reviewId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryReviewsByIds($reviewIds): Builder|Relation
    {
        return $this->query->whereIn('reviews.id', $reviewIds);
    }

    /**
     * Get reviews by IDs.
     *
     * @param array<string> $reviewId
     * @param string $relationships
     * @return Collection
     */
    public function getReviewsByIds($reviewIds): Collection
    {
        return $this->queryReviewsByIds($reviewIds)->get();
    }
}
