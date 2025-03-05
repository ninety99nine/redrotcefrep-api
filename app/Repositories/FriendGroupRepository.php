<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Store;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Models\FriendGroup;
use Illuminate\Support\Str;
use App\Traits\Base\BaseTrait;
use App\Enums\InvitationResponse;
use App\Enums\UserFriendGroupRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Enums\UserFriendGroupStatus;
use App\Http\Resources\UserResources;
use App\Http\Resources\OrderResources;
use App\Http\Resources\StoreResources;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\FriendGroupResources;
use Illuminate\Support\Facades\Notification;
use App\Services\PhoneNumber\PhoneNumberService;
use App\Models\Pivots\FriendGroupUserAssociation;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Notifications\Users\RemoveFriendGroupMember;
use App\Notifications\FriendGroups\FriendGroupCreated;
use App\Notifications\FriendGroups\FriendGroupDeleted;
use App\Notifications\FriendGroups\FriendGroupStoreAdded;
use App\Notifications\FriendGroups\FriendGroupStoreRemoved;
use App\Notifications\Users\InvitationToJoinFriendGroupCreated;
use App\Notifications\Users\InvitationToJoinFriendGroupAccepted;
use App\Notifications\Users\InvitationToJoinFriendGroupDeclined;

class FriendGroupRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show friend groups.
     *
     * @param array $data
     * @return FriendGroupResources|array
     */
    public function showFriendGroups(array $data = []): FriendGroupResources|array
    {
        if($this->getQuery() == null) {

            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {
                if(!$this->isAuthourized()); return ['message' => 'You do not have permission to show friend groups'];
                $this->setQuery(FriendGroup::query()->latest());
            }else {
                $this->setQuery(request()->current_user->friendGroups()->orderByPivot('last_selected_at', 'DESC'));
            }

        }

        return $this->getOutput();
    }

    /**
     * Create friend group.
     *
     * @param array $data
     * @return FriendGroup|array
     */
    public function createFriendGroup(array $data): FriendGroup|array
    {
        $data = array_merge($data, [
            'created_by_super_admin' => $this->checkIfCreatedBySuperAdmin()
        ]);

        $friendGroup = FriendGroup::create($data);
        $this->addFriendGroupCreator($friendGroup, request()->current_user);
        if(isset($data['mobile_numbers'])) {
            $role = isset($data['role']) ? UserFriendGroupRole::tryFrom($data['role']) : null;
            $this->inviteFriendGroupMembers($friendGroup, $role, $data['mobile_numbers']);
        }
        Notification::send(request()->current_user, new FriendGroupCreated($friendGroup, request()->current_user));
        return $this->showCreatedResource($friendGroup);
    }

    /**
     * Remove friend groups.
     *
     * @param array $friendGroupIds
     * @return array
     */
    public function removeFriendGroups(array $friendGroupIds): array
    {
        if($this->getQuery() == null) {
            if($this->isAuthourized()) {
                $this->setQuery(FriendGroup::query());
            }else {
                $this->setQuery(request()->current_user->friendGroups()->where('friend_group_user_association.role', UserFriendGroupRole::CREATOR->value));
            }
        }

        $friendGroups = $this->queryFriendGroupsById($friendGroupIds)->with(['nonGuestUsers'])->get();

        if($totalFriendGroups = $friendGroups->count()) {

            foreach($friendGroups as $friendGroup) {

                $friendGroup->delete();

                foreach($friendGroup->nonGuestUsers as $user) {
                    Notification::send($user, new FriendGroupDeleted($friendGroup->id, $friendGroup->name_with_emoji, request()->current_user));
                }

            }

            return ['removed' => true, 'message' => $totalFriendGroups . ($totalFriendGroups == 1 ? ' friend group': ' friend groups') . ' removed'];

        }else{
            return ['removed' => false, 'message' => 'No friend groups removed'];
        }
    }

    /**
     * Show first created friend group.
     *
     * @return FriendGroup|array|null
     */
    public function showFirstCreatedFriendGroup(): FriendGroup|array|null
    {
        $query = request()->current_user->friendGroups()->joinedGroupAsCreator()->oldest();
        $firstCreatedFriendGroup = $this->setQuery($query)->applyEagerLoadingOnQuery()->getQuery()->first();

        return $this->showResourceExistence($firstCreatedFriendGroup);
    }

    /**
     * Show last selected friend group.
     *
     * @return FriendGroup|array|null
     */
    public function showLastSelectedFriendGroup(): FriendGroup|array|null
    {
        $query = request()->current_user->friendGroups()->orderByPivot('last_selected_at', 'DESC');
        $lastSelectedFriendGroup = $this->setQuery($query)->applyEagerLoadingOnQuery()->getQuery()->first();

        return $this->showResourceExistence($lastSelectedFriendGroup);
    }

    /**
     * Updated last selected friend group.
     *
     * @param array $friendGroupIds
     * @return array
     */
    public function updateLastSelectedFriendGroups(array $friendGroupIds): array
    {
        DB::table('friend_group_user_association')
            ->whereIn('friend_group_id', $friendGroupIds)
            ->where('user_id', request()->current_user->id)
            ->update(['last_selected_at' => now()]);

        return ['message' => 'Updated successfully'];
    }

    /**
     * Check invitations to join friend groups.
     *
     * @return array
     */
    public function checkInvitationsToJoinFriendGroups(): array
    {
        $invitations = DB::table('friend_group_user_association')
                            ->where('user_id', request()->current_user->id)
                            ->where('status', UserFriendGroupStatus::INVITED)
                            ->get();

        $hasInvitations = ($totalInvitations = count($invitations)) > 0;

        return [
            'has_invitations' => $hasInvitations,
            'total_invitations' => $totalInvitations,
        ];
    }

    /**
     * Accept all invitations to join friend groups.
     *
     * @return array
     */
    public function acceptAllInvitationsToJoinFriendGroups(): array
    {
        $userId = request()->current_user->id;
        $joinedStatus = UserFriendGroupStatus::JOINED;
        $invitedStatus = UserFriendGroupStatus::INVITED;

        // Get the friend groups that the user has been invited to join
        $friendGroups = FriendGroup::with(['users' => fn($query) => $query->joinedGroup()])
            ->whereHas('users', fn($query) => $query->where([
                'status' => $invitedStatus,
                'user_id' => $userId
            ]))->get();

        // Accept the invitations
        DB::table('friend_group_user_association')
            ->where('user_id', $userId)
            ->where('status', $invitedStatus)
            ->update(['status' => $joinedStatus]);

        if ($friendGroups->isNotEmpty()) {
            $this->notifyFriendGroupMembersOnUserResponseToInvitation(InvitationResponse::ACCEPTED, $friendGroups);
        }

        return ['message' => 'Invitations accepted successfully'];
    }

    /**
     * Decline all invitations to join friend groups.
     *
     * @return array
     */
    public function declineAllInvitationsToJoinFriendGroups(): array
    {
        $userId = request()->current_user->id;
        $invitedStatus = UserFriendGroupStatus::INVITED;
        $declinedStatus = UserFriendGroupStatus::DECLINED;

        // Get the friend groups that the user has been invited to join
        $friendGroups = FriendGroup::with(['users' => fn($query) => $query->joinedGroup()])
            ->whereHas('users', fn($query) => $query->where([
                'status' => $invitedStatus,
                'user_id' => $userId
            ]))->get();

        // Decline the invitations
        DB::table('friend_group_user_association')
            ->where('user_id', $userId)
            ->where('status', $invitedStatus)
            ->update(['status' => $declinedStatus]);

        if ($friendGroups->isNotEmpty()) {
            $this->notifyFriendGroupMembersOnUserResponseToInvitation(InvitationResponse::DECLINED, $friendGroups);
        }

        return ['message' => 'Invitations declined successfully'];
    }

    /**
     * Show friend group.
     *
     * @param string|null $friendGroupId
     * @return FriendGroup|array|null
     */
    public function showFriendGroup(string|null $friendGroupId = null): FriendGroup|array|null
    {
        if(($friendGroup = $friendGroupId) instanceof FriendGroup) {
            $friendGroup = $this->applyEagerLoadingOnModel($friendGroup);
        }else {
            $query = $this->getQuery() ?? FriendGroup::query();
            if($friendGroupId) $query = $query->where('friend_groups.id', $friendGroupId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $friendGroup = $this->query->first();
        }

        return $this->showResourceExistence($friendGroup);
    }

    /**
     * Update friend group.
     *
     * @param string $friendGroupId
     * @param array $data
     * @return FriendGroup|array
     */
    public function updateFriendGroup(string $friendGroupId, array $data): FriendGroup|array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupCreatorOrAdmin($friendGroup);

            if ($isAuthourized) {

                $friendGroup->update($data);
                if(isset($data['mobile_numbers'])) {
                    $role = isset($data['role']) ? UserFriendGroupRole::tryFrom($data['role']) : null;
                    $this->inviteFriendGroupMembers($friendGroup, $role, $data['mobile_numbers']);
                }
                return $this->showUpdatedResource($friendGroup);

            }else{
                return ['updated' => false, 'message' => 'You do not have permission to update this friend group'];
            }

        }else{
            return ['updated' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Remove friend group.
     *
     * @param string $friendGroupId
     * @return array
     */
    public function removeFriendGroup(string $friendGroupId): array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupCreatorOrAdmin($friendGroup);

            if ($isAuthourized) {

                $deleted = $friendGroup->delete();

                if ($deleted) {
                    return ['removed' => true, 'message' => 'Friend group removed'];
                }else{
                    return ['removed' => false, 'message' => 'Friend group removal unsuccessful'];
                }

            }else{
                return ['removed' => false, 'message' => 'You do not have permission to remove this friend group'];
            }

        }else{
            return ['removed' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Show friend group members.
     *
     * @param string $friendGroupId
     * @param array $data
     * @return UserResources|array
     */
    public function showFriendGroupMembers(string $friendGroupId, array $data = []): UserResources|array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupMemberWhoJoined($friendGroup);

            if ($isAuthourized) {

                $query = $friendGroup->users()->orderByPivot('created_at', 'DESC');
                return $this->getUserRepository()->setQuery($query)->showUsers($data);

            }else{
                return ['message' => 'You do not have permission to show friend group members'];
            }

        }else{
            return ['message' => 'This friend group does not exist'];
        }
    }

    /**
     * Invite friend group members.
     *
     * @param string|FriendGroup $friendGroupId
     * @param UserFriendGroupRole|null $memberRole
     * @param array $mobileNumbers
     * @return array
     */
    public function inviteFriendGroupMembers(string|FriendGroup $friendGroupId, UserFriendGroupRole|null $memberRole, array $mobileNumbers): array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupCreatorOrAdmin($friendGroup);

            if ($isAuthourized) {

                $assignedUsers = $this->getAssignedUsers($friendGroup, $mobileNumbers);
                $notAssignedUsers = $this->getNotAssignedUsers($friendGroup, $mobileNumbers);
                $mobileNumbersThatDontMatchAnyUserButInvited = $this->getNonExistingUsersButInvited($friendGroup, $mobileNumbers);
                $mobileNumbersThatDontMatchAnyUser = $this->getNonMatchingMobileNumbers($mobileNumbers, array_merge(
                    $mobileNumbersThatDontMatchAnyUserButInvited,
                    collect($assignedUsers)->map(fn(User $assignedUser) => $assignedUser->mobile_number->formatE164())->toArray(),
                    collect($notAssignedUsers)->map(fn(User $notAssignedUser) => $notAssignedUser->mobile_number->formatE164())->toArray()
                ));

                if ($notAssignedUsers->isNotEmpty()) {
                    $this->addFriendGroupMembers($friendGroup, $notAssignedUsers, UserFriendGroupStatus::INVITED, $memberRole);
                }

                if (!empty($mobileNumbersThatDontMatchAnyUser)) {
                    $this->addFriendGroupMembersByMobileNumbers($friendGroup, $mobileNumbersThatDontMatchAnyUser, $memberRole);
                }

                $message = $this->prepareInvitationMessage($mobileNumbers, $assignedUsers);
                $invitations = $this->prepareInvitationSummary($notAssignedUsers, $assignedUsers, $mobileNumbersThatDontMatchAnyUser, $mobileNumbersThatDontMatchAnyUserButInvited);

                return [
                    'invited' => true,
                    'message' => $message,
                    'invitations' => $invitations
                ];

            }else{
                return ['invited' => false, 'message' => 'You do not have permission to invite friend group members'];
            }

        }else{
            return ['invited' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Remove friend group members.
     *
     * @param string $friendGroupId
     * @param array $mobileNumbers
     * @return array
     */
    public function removeFriendGroupMembers(string $friendGroupId, array $mobileNumbers): array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupCreatorOrAdmin($friendGroup);

            if ($isAuthourized) {

                $assignedUsers = $friendGroup->users()
                    ->whereIn('friend_group_user_association.mobile_number', $mobileNumbers)
                    ->orWhereIn('users.mobile_number', $mobileNumbers)
                    ->get();

                $assignedUsers = $assignedUsers->reject(function ($user) {
                    return ($user->id === request()->current_user->id) || $user->friend_group_user_association->is_creator;
                });

                if ($assignedUsers->isEmpty()) {
                    return ['removed' => false, 'message' => 'No group members removed'];
                }

                $userFriendGroupAssociationIds = $assignedUsers->pluck('friend_group_user_association.id')->toArray();

                DB::table('friend_group_user_association')->whereIn('id', $userFriendGroupAssociationIds)->delete();

                $usersWhoJoined = $friendGroup->users()->joinedGroup()->get();

                foreach ($assignedUsers as $removedUser) {
                    Notification::send($usersWhoJoined, new RemoveFriendGroupMember($friendGroup, $removedUser, request()->current_user));
                }

                return ['removed' => true, 'message' => count($userFriendGroupAssociationIds) . ' group ' . (count($userFriendGroupAssociationIds) === 1 ? 'member' : 'members') . ' removed'];

            }else{
                return ['removed' => false, 'message' => 'You do not have permission to remove friend group members'];
            }

        }else{
            return ['removed' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Leave friend group.
     *
     * @param string $friendGroupId
     * @return array
     */
    public function leaveFriendGroup(string $friendGroupId): array
    {
        $friendGroup = $this->setQuery(request()->current_user->friendGroups())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $friendGroupUserAssociation = $this->getFriendGroupUserAssociation($friendGroup);

            if($friendGroupUserAssociation && $friendGroupUserAssociation->is_user_who_has_joined) {

                DB::table('friend_group_user_association')
                    ->where('user_id', request()->current_user->id)
                    ->where('friend_group_id', $friendGroup->id)
                    ->update([
                        'status' => UserFriendGroupStatus::LEFT
                    ]);

                return ['left' => true, 'message' => 'You have left this group'];

            }else{
                return ['left' => false, 'message' => 'You have not joined this group'];
            }

        }else{
            return ['left' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Accept invitation to join friend group.
     *
     * @param string $friendGroupId
     * @return array
     */
    public function acceptInvitationToJoinFriendGroup(string $friendGroupId): array
    {
        $friendGroup = $this->setQuery(request()->current_user->friendGroups())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $friendGroupUserAssociation = $this->getFriendGroupUserAssociation($friendGroup);

            if($friendGroupUserAssociation) {

                if($friendGroupUserAssociation->is_user_who_is_invited) {

                    $this->updateInvitationStatusToJoinFriendGroup($friendGroup, UserFriendGroupStatus::JOINED);
                    $this->notifyFriendGroupMembersOnUserResponseToInvitation(InvitationResponse::ACCEPTED, $friendGroup);
                    return ['accepted' => true, 'message' => 'Invitation accepted successfully'];

                }else if($friendGroupUserAssociation->is_user_who_has_joined) {
                    return ['accepted' => true, 'message' => 'Invitation already accepted'];
                }else if($friendGroupUserAssociation->is_user_who_has_left) {
                    return ['accepted' => false, 'message' => 'You have already left the group'];
                }else if($friendGroupUserAssociation->is_user_who_has_declined) {
                    return ['accepted' => false, 'message' => 'Invitation has already been declined and cannot be accepted. Request the group creator or admin to resend the invitation again.'];
                }

            }else{
                return ['accepted' => false, 'message' => 'You have not been invited to this group'];
            }

        }else{
            return ['accepted' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Decline invitation to join friend group.
     *
     * @param string $friendGroupId
     * @return array
     */
    public function declineInvitationToJoinFriendGroup(string $friendGroupId): array
    {
        $friendGroup = $this->setQuery(request()->current_user->friendGroups())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $friendGroupUserAssociation = $this->getFriendGroupUserAssociation($friendGroup);

            if($friendGroupUserAssociation) {

                if($friendGroupUserAssociation->is_user_who_is_invited) {

                    $this->updateInvitationStatusToJoinFriendGroup($friendGroup, UserFriendGroupStatus::DECLINED);
                    $this->notifyFriendGroupMembersOnUserResponseToInvitation(InvitationResponse::DECLINED, $friendGroup);
                    return ['declined' => true, 'message' => 'Invitation declined successfully'];

                }else if($friendGroupUserAssociation->is_user_who_has_joined) {
                    return ['declined' => false, 'message' => 'Invitation has already been accepted and cannot be declined.'];
                }else if($friendGroupUserAssociation->is_user_who_has_left) {
                    return ['declined' => false, 'message' => 'You have already left the group'];
                }else if($friendGroupUserAssociation->is_user_who_has_declined) {
                    return ['declined' => true, 'message' => 'Invitation already declined'];
                }

            }else{
                return ['declined' => false, 'message' => 'You have not been invited to this group'];
            }

        }else{
            return ['declined' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Show friend group stores.
     *
     * @param string $friendGroupId
     * @return StoreResources|array
     */
    public function showFriendGroupStores(string $friendGroupId): StoreResources|array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupMemberWhoJoined($friendGroup);

            if ($isAuthourized) {

                $query = $friendGroup->stores()->orderByPivot('created_at', 'DESC');
                return $this->getStoreRepository()->setQuery($query)->showStores();

            }else{
                return ['message' => 'You do not have permission to show friend group stores'];
            }

        }else{
            return ['message' => 'This friend group does not exist'];
        }
    }

    /**
     * Add friend group stores.
     *
     * @param string $friendGroupId
     * @param array $storeIds
     * @return array
     */
    public function addFriendGroupStores(string $friendGroupId, array $storeIds): array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupCreatorOrAdmin($friendGroup);

            if ($isAuthourized) {

                $detaching = false;
                $existingStoreIds = Store::whereIn('id', $storeIds)->pluck('id')->toArray();

                if(!empty($existingStoreIds)) {

                    $attachedStores = $friendGroup->stores()->syncWithPivotValues($existingStoreIds, ['added_by_user_id' => request()->current_user->id], $detaching);

                    $attachedStoreIds = $attachedStores['attached'];
                    $totalAddedStores = count($attachedStoreIds);

                    if ($totalAddedStores > 0) {
                        $stores = Store::whereIn('id', $attachedStoreIds)->get();
                        $usersWhoJoined = $friendGroup->users()->joinedGroup()->get();
                        $stores->each(fn($store) => Notification::send($usersWhoJoined, new FriendGroupStoreAdded($friendGroup, $store, request()->current_user)));
                        return ['added' => true, 'message' => $totalAddedStores . ($totalAddedStores == 1 ? ' store' : ' stores') . ' added'];
                    }

                }

                return ['added' => false, 'message' => 'No stores added'];

            }else{
                return ['added' => false, 'message' => 'You do not have permission to add friend group stores'];
            }

        }else{
            return ['added' => false, 'message' => 'This friend group does not exist'];
        }
    }

    /**
     * Remove friend group stores.
     *
     * @param string $friendGroupId
     * @param array $storeIds
     * @return array
     */
    public function removeFriendGroupStores(string $friendGroupId, array $storeIds): array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupCreatorOrAdmin($friendGroup);

            if ($isAuthourized) {

                $matchingStoreIds = DB::table('friend_group_store_association')
                    ->where('friend_group_id', $friendGroup->id)
                    ->whereIn('store_id', $storeIds)
                    ->pluck('store_id')
                    ->toArray();

                if (!empty($matchingStoreIds)) {

                    $totalStores = count($matchingStoreIds);

                    DB::table('friend_group_store_association')
                        ->where('friend_group_id', $friendGroup->id)
                        ->whereIn('store_id', $storeIds)
                        ->delete();

                    $stores = Store::whereIn('id', $matchingStoreIds)->get();
                    $usersWhoJoined = $friendGroup->users()->joinedGroup()->get();

                    $stores->each(fn($store) => Notification::send($usersWhoJoined, new FriendGroupStoreRemoved($friendGroup, $store, request()->current_user)));

                    return ['removed' => true, 'message' => $totalStores . ($totalStores == 1 ? ' store' : ' stores') . ' removed'];

                }

                return ['removed' => false, 'message' => 'No stores removed'];

            }else{
                return ['removed' => false, 'message' => 'You do not have permission to remove stores from this group'];
            }

        }else{
            return ['removed' => false, 'message' => 'This friend group does not exist'];
        }

    }

    /**
     * Show friend group orders.
     *
     * @param string $friendGroupId
     * @param array $data
     * @return OrderResources|array
     */
    public function showFriendGroupOrders(string $friendGroupId, array $data): OrderResources|array
    {
        $friendGroup = $this->setQuery(FriendGroup::query())->getFriendGroupById($friendGroupId);

        if($friendGroup) {

            $isAuthourized = $this->isAuthourized() || $this->checkIfAssociatedAsFriendGroupMemberWhoJoined($friendGroup);

            if ($isAuthourized) {

                $query = $friendGroup->orders()->latest();
                return $this->getOrderRepository()->setQuery($query)->showOrders($data);

            }else{
                return ['message' => 'You do not have permission to show friend group orders'];
            }

        }else{
            return ['message' => 'This friend group does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query friend group by ID.
     *
     * @param string $friendGroupId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryFriendGroupById(string $friendGroupId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('friend_groups.id', $friendGroupId)->with($relationships);
    }

    /**
     * Get friend group by ID.
     *
     * @param string $friendGroupId
     * @param array $relationships
     * @return FriendGroup|null
     */
    public function getFriendGroupById(string $friendGroupId, array $relationships = []): FriendGroup|null
    {
        return $this->queryFriendGroupById($friendGroupId, $relationships)->first();
    }

    /**
     * Get friend groups by IDs.
     *
     * @param array<string> $friendGroupIds
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryFriendGroupsById(array $friendGroupIds): Builder|Relation
    {
        return $this->query->whereIn('friend_groups.id', $friendGroupIds);
    }

    /**
     * Get friend groups by IDs.
     *
     * @param array<string> $friendGroupIds
     * @param string $relationships
     * @return Collection
     */
    public function getFriendGroupsById(array $friendGroupIds): Collection
    {
        return $this->queryFriendGroupsById($friendGroupIds)->get();
    }

    /**
     * Add friend group creator.
     *
     * @param FriendGroup $friendGroup
     * @param User $user
     * @return void
     */
    private function addFriendGroupCreator(FriendGroup $friendGroup, User $user): void
    {
        $this->addFriendGroupMembers($friendGroup, $user, UserFriendGroupStatus::JOINED, UserFriendGroupRole::CREATOR);
    }

    /**
     * Add friend group members.
     *
     * @param FriendGroup $friendGroup
     * @param Collection|User[]|User $users
     * @param UserFriendGroupStatus $memberStatus
     * @param UserFriendGroupRole|null $memberRole
     * @return void
     */
    public function addFriendGroupMembers(FriendGroup $friendGroup, $users, UserFriendGroupStatus $memberStatus, UserFriendGroupRole $memberRole = UserFriendGroupRole::MEMBER)
    {
        if(($user = $users) instanceof User) {
            $users = collect([$user]);
        }elseif(is_array($users)) {
            $users = collect($users);
        }

        $userIds = $users->pluck('id');

        if( $userIds->count() ) {

            $lastSelectedAt = $memberRole == UserFriendGroupRole::CREATOR ? now() : null;
            $invitedByUser = $memberStatus == UserFriendGroupStatus::INVITED ? request()->current_user : null;

            $records = $userIds->map(function($userId) use($friendGroup, $memberStatus, $memberRole, $lastSelectedAt, $invitedByUser) {

                $record = [
                    'invited_to_join_by_user_id' => $invitedByUser->id ?? null,
                    'last_selected_at' => $lastSelectedAt,
                    'friend_group_id' => $friendGroup->id,
                    'role' => $memberRole->value,
                    'status' => $memberStatus,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'user_id' => $userId,
                    'id' => Str::uuid()
                ];

                return $record;

            })->toArray();

            DB::table('friend_group_user_association')->insert($records);

            if($memberRole !== UserFriendGroupRole::CREATOR) {
                Notification::send($users, new InvitationToJoinFriendGroupCreated($friendGroup, $invitedByUser));
            }

        }
    }

    /**
     * Add friend group members by mobile numbers
     *
     * @param FriendGroup $friendGroup
     * @param string|array<string> $mobileNumbers
     * @param UserFriendGroupRole|null $memberRole
     * @return void
     */
    private function addFriendGroupMembersByMobileNumbers(FriendGroup $friendGroup, $mobileNumbers, UserFriendGroupRole $memberRole = UserFriendGroupRole::MEMBER): void
    {
        if(is_int($mobileNumber = $mobileNumbers)) {
            $mobileNumbers = collect([$mobileNumber]);
        }elseif(is_array($mobileNumbers)) {
            $mobileNumbers = collect($mobileNumbers);
        }

        if( $mobileNumbers->count() ) {

            $records = collect($mobileNumbers)->map(function($mobileNumber) use($friendGroup, $memberRole) {

                return [
                    'invited_to_join_by_user_id' => request()->current_user->id,
                    'user_id' => $this->getUserRepository()->getGuestUserId(),
                    'status' => UserFriendGroupStatus::INVITED,
                    'friend_group_id' => $friendGroup->id,
                    'mobile_number' => $mobileNumber,
                    'role' => $memberRole->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'id' => Str::uuid()
                ];
            })->toArray();

            DB::table('friend_group_user_association')->insert($records);

        }
    }

    /**
     * Get friend group user association.
     *
     * @param FriendGroup $friendGroup
     * @return FriendGroupUserAssociation|null
     */
    public function getFriendGroupUserAssociation(FriendGroup $friendGroup)
    {
        return FriendGroupUserAssociation::where('user_id', request()->current_user->id)
                                  ->where('friend_group_id', $friendGroup->id)->first();
    }

    /**
     * Check if joined friend group.
     *
     * @param FriendGroup $friendGroup
     * @return bool
     */
    public function checkIfAssociatedAsFriendGroupMemberWhoJoined(FriendGroup $friendGroup)
    {
        $friendGroupUserAssociation = $this->getFriendGroupUserAssociation($friendGroup);
        return $friendGroupUserAssociation && $friendGroupUserAssociation->is_user_who_has_joined;
    }

    /**
     * Check if associated as friend group creator or admin.
     *
     * @param FriendGroup $friendGroup
     * @return bool
     */
    public function checkIfAssociatedAsFriendGroupCreatorOrAdmin(FriendGroup $friendGroup)
    {
        $friendGroupUserAssociation = $this->getFriendGroupUserAssociation($friendGroup);
        return $friendGroupUserAssociation && $friendGroupUserAssociation->is_creator_or_admin;
    }

    /**
     * Notify group members on user response to accept or decline invitation to join friend group(s).
     *
     * @param InvitationResponse $invitationResponse
     * @param FriendGroup|Collection|array<FriendGroup> $friendGroupsInvitedToJoin
     * @return void
     */
    private function notifyFriendGroupMembersOnUserResponseToInvitation(InvitationResponse $invitationResponse, $friendGroupsInvitedToJoin): void
    {
        $sendNotifications = function($friendGroupInvitedToJoin) use ($invitationResponse) {
            if($invitationResponse == InvitationResponse::ACCEPTED) {
                Notification::send($friendGroupInvitedToJoin->users, new InvitationToJoinFriendGroupAccepted($friendGroupInvitedToJoin, request()->current_user));
            }else{
                Notification::send($friendGroupInvitedToJoin->users, new InvitationToJoinFriendGroupDeclined($friendGroupInvitedToJoin, request()->current_user));
            }
        };

        if($friendGroupsInvitedToJoin instanceof FriendGroup) {
            $friendGroupsInvitedToJoin = [$friendGroupsInvitedToJoin];
        }

        foreach($friendGroupsInvitedToJoin as $friendGroupInvitedToJoin) {
            $sendNotifications($friendGroupInvitedToJoin);
        }
    }

    /**
     * Get assigned users.
     *
     * @param FriendGroup $friendGroup
     * @param array $mobileNumbers
     * @return Collection
     */
    private function getAssignedUsers(FriendGroup $friendGroup, array $mobileNumbers): Collection
    {
        return $friendGroup->users()->whereIn('users.mobile_number', $mobileNumbers)->get();
    }

    /**
     * Get non assigned users.
     *
     * @param FriendGroup $friendGroup
     * @param array $mobileNumbers
     * @return Collection
     */
    private function getNotAssignedUsers(FriendGroup $friendGroup, array $mobileNumbers): Collection
    {
        return User::whereIn('mobile_number', $mobileNumbers)
            ->whereDoesntHave('friendGroups', function (Builder $query) use ($friendGroup) {
                $query->where('friend_group_user_association.friend_group_id', $friendGroup->id);
            })
            ->get();
    }

    /**
     * Get non-existing users but invited.
     *
     * @param FriendGroup $friendGroup
     * @param array $mobileNumbers
     * @return array
     */
    private function getNonExistingUsersButInvited(FriendGroup $friendGroup, array $mobileNumbers): array
    {
        return DB::table('friend_group_user_association')
            ->where('friend_group_id', $friendGroup->id)
            ->whereIn('mobile_number', $mobileNumbers)
            ->where('status', UserFriendGroupStatus::INVITED)
            ->pluck('mobile_number')
            ->toArray();
    }

    /**
     * Get non matching mobile numbers.
     *
     * @param array $mobileNumbers
     * @param array $unmatchableMobileNumbers
     * @param Collection $assignedUsers
     * @return array
     */
    private function getNonMatchingMobileNumbers(array $mobileNumbers, array $unmatchableMobileNumbers): array
    {
        return array_diff($mobileNumbers, $unmatchableMobileNumbers);
    }

    /**
     * Prepare invitation message.
     *
     * @param array $mobileNumbers
     * @param Collection $assignedUsers
     * @return string
     */
    private function prepareInvitationMessage(array $mobileNumbers, Collection $assignedUsers): string
    {
        if (count($mobileNumbers) === $assignedUsers->count()) {
            $message = $assignedUsers->pluck('first_name')->join(', ', ' and ');
            $message .= $assignedUsers->count() === 1 ? ' has' : ' have';
            return "$message already been invited";
        }

        return 'Invitations sent successfully';
    }

    /**
     * Prepare invitation summary.
     *
     * @param Collection $notAssignedUsers
     * @param Collection $assignedUsers
     * @param array $mobileNumbersThatDontMatchAnyUser
     * @param array $mobileNumbersThatDontMatchAnyUserButInvited
     * @return array
     */
    private function prepareInvitationSummary(Collection $notAssignedUsers, Collection $assignedUsers, array $mobileNumbersThatDontMatchAnyUser, array $mobileNumbersThatDontMatchAnyUserButInvited): array
    {
        $transformExistingUser = fn(User $user) => [
            'name' => $user->name,
            'mobile_number' => $user->mobile_number,
            'status' => $user->friend_group_user_association->status ?? 'Invited',
        ];

        $transformNonExistingUser = fn($mobileNumber) => [
            'mobile_number' => PhoneNumberService::formatPhoneNumber($mobileNumber),
            'status' => 'Invited'
        ];

        return [
            'total_invited' => $notAssignedUsers->count() + count($mobileNumbersThatDontMatchAnyUser),
            'total_already_invited' => $assignedUsers->count() + count($mobileNumbersThatDontMatchAnyUserButInvited),
            'existing_users_invited' => [
                'total' => $notAssignedUsers->count(),
                'existing_users' => $notAssignedUsers->map($transformExistingUser)->toArray()
            ],
            'existing_users_already_invited' => [
                'total' => $assignedUsers->count(),
                'existing_users' => $assignedUsers->map($transformExistingUser)->toArray()
            ],
            'non_existing_users_invited' => [
                'total' => count($mobileNumbersThatDontMatchAnyUser),
                'non_existing_users' => collect($mobileNumbersThatDontMatchAnyUser)->map($transformNonExistingUser)->values()->toArray()
            ],
            'non_existing_users_already_invited' => [
                'total' => count($mobileNumbersThatDontMatchAnyUserButInvited),
                'non_existing_users' => collect($mobileNumbersThatDontMatchAnyUserButInvited)->map(fn($mobileNumber) => $transformNonExistingUser($mobileNumber))->values()->toArray()
            ],
        ];
    }

    /**
     * Update invitation status to join friend group.
     *
     * @param FriendGroup $friendGroup
     * @param UserFriendGroupStatus $status
     * @return void
     */
    private function updateInvitationStatusToJoinFriendGroup(FriendGroup $friendGroup, UserFriendGroupStatus $status): void
    {
        DB::table('friend_group_user_association')
            ->where('friend_group_id', $friendGroup->id)
            ->where('user_id', request()->current_user->id)->update([
                'status' => $status
            ]);
    }

    /**
     * Check if created by super admin
     *
     * @return bool
     */
    private function checkIfCreatedBySuperAdmin()
    {
        return $this->getAuthUser()->isSuperAdmin() && (request()->current_user != null) && (request()->current_user->id != $this->getAuthUser()->id);
    }
}
