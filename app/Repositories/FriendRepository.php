<?php

namespace App\Repositories;

use App\Models\Friend;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use App\Http\Resources\FriendResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class FriendRepository extends UserRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show friends.
     *
     * @param array $data
     * @return FriendResources|array
     */
    public function showFriends(array $data = []): FriendResources|array
    {
        if($this->getQuery() == null) {

            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {
                if(!$this->isAuthourized()); return ['message' => 'You do not have permission to show friends'];
                $this->setQuery(Friend::query()->latest());
            }else {
                $this->setQuery(request()->current_user->friends()->orderBy('last_selected_at', 'DESC'));
            }

        }

        return $this->getOutput();
    }

    /**
     * Add friend.
     *
     * @param array $data
     * @return Friend|array
     */
    public function addFriend(array $data): Friend|array
    {
        $data = array_merge($data, [
            'user_id' => request()->current_user->id
        ]);

        $friend = Friend::create($data);
        return $this->showSavedResource($friend, 'added');
    }

    /**
     * Remove friends.
     *
     * @param array $friendIds
     * @return array
     */
    public function removeFriends(array $friendIds): array
    {
        if($this->getQuery() == null) {
            if($this->isAuthourized()) {
                $this->setQuery(Friend::query());
            }else {
                $this->setQuery(request()->current_user->friends());
            }
        }

        $friends = $this->queryFriendsByIds($friendIds)->get();

        if($totalFriends = $friends->count()) {

            foreach($friends as $friend) {

                $deleted = $friend->delete();

                if ($deleted) {
                    return ['removed' => true, 'message' => 'Friend removed'];
                }else{
                    return ['removed' => false, 'message' => 'Friend removal unsuccessful'];
                }

            }

            return ['removed' => true, 'message' => $totalFriends . ($totalFriends == 1 ? ' friend': ' friends') . ' removed'];

        }else{
            return ['removed' => false, 'message' => 'No friends removed'];
        }
    }

    /**
     * Show last selected friend.
     *
     * @return Friend|array|null
     */
    public function showLastSelectedFriend(): Friend|array|null
    {
        $query = request()->current_user->friends()->orderBy('last_selected_at', 'DESC');
        $lastSelectedFriend = $this->setQuery($query)->applyEagerLoadingOnQuery()->getQuery()->first();

        return $this->showResourceExistence($lastSelectedFriend);
    }

    /**
     * Update last selected friends.
     *
     * @param array $friendIds
     * @return array
     */
    public function updateLastSelectedFriends(array $friendIds): array
    {
        if($friendIds) {

            request()->current_user->friends()
                ->whereIn('id', $friendIds)
                ->update(['last_selected_at' => now()]);

            return ['message' => 'Updated successfully'];

        }
    }

    /**
     * Show friend.
     *
     * @param string|null $friendId
     * @return array|self
     */
    public function showFriend(string|null $friendId = null): array|self
    {
        if(($friend = $friendId) instanceof Friend) {
            $friend = $this->applyEagerLoadingOnModel($friend);
        }else {
            $query = $this->getQuery() ?? Friend::query();
            if($friendId) $query = $query->where('friends.id', $friendId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $friend = $this->query->first();
        }

        return $this->showResourceExistence($friend);
    }

    /**
     * Update friend.
     *
     * @param string $friendId
     * @param array $data
     * @return Friend|array
     */
    public function updateFriend(string $friendId, array $data): Friend|array
    {
        $friend = Friend::find($friendId);

        if($friend) {

            $isAuthourized = $this->isAuthourized() || request()->current_user->id == $friend->user_id;

            if ($isAuthourized) {

                $friend->update($data);
                return $this->showUpdatedResource($friend);

            }else{
                return ['updated' => false, 'message' => 'You do not have permission to update this friend'];
            }

        }else{
            return ['updated' => false, 'message' => 'This friend does not exist'];
        }
    }

    /**
     * Remove friend.
     *
     * @param string $friendId
     * @return array
     */
    public function removeFriend(string $friendId): array
    {
        if($this->getQuery() == null) {
            if($this->isAuthourized()) {
                $this->setQuery(Friend::query());
            }else {
                $this->setQuery(request()->current_user->friends());
            }
        }

        $friend = $this->getFriendById($friendId);
        $deleted = $friend->delete();

        if ($deleted) {
            return ['removed' => true, 'message' => 'Friend group removed'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query friend by ID.
     *
     * @param Friend|string $friendId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryFriendById(Friend|string $friendId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('friends.id', $friendId)->with($relationships);
    }

    /**
     * Get friend by ID.
     *
     * @param Friend|string $friendId
     * @param array $relationships
     * @return Friend|null
     */
    public function getFriendById(Friend|string $friendId, array $relationships = []): Friend|null
    {
        return $this->queryFriendById($friendId, $relationships)->first();
    }

    /**
     * Query friends by IDs.
     *
     * @param array<string> $friendId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryFriendsByIds($friendIds): Builder|Relation
    {
        return $this->query->whereIn('friends.id', $friendIds);
    }

    /**
     * Get friends by IDs.
     *
     * @param array<string> $friendId
     * @param string $relationships
     * @return Collection
     */
    public function getFriendsByIds($friendIds): Collection
    {
        return $this->queryFriendsByIds($friendIds)->get();
    }
}
