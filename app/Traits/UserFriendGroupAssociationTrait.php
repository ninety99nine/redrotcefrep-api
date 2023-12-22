<?php

namespace App\Traits;

use App\Models\Pivots\UserFriendGroupAssociation;

trait UserFriendGroupAssociationTrait
{
    /*
     *  Scope: Return users that have joined the friend group
     */
    public function scopeJoinedGroup($query)
    {
        return $query->where('user_friend_group_association.status', 'Joined');
    }

    /*
     *  Scope: Return users that have joined the friend group as a creator
     */
    public function scopeJoinedGroupAsCreator($query)
    {
        return $query->joinedGroup()->where('user_friend_group_association.role', 'Creator');
    }

    /*
     *  Scope: Return users that have joined the friend group as a creator or admin
     */
    public function scopeJoinedGroupAsCreatorOrAdmin($query)
    {
        return $query->joinedGroup()->whereIn('user_friend_group_association.role', ['Creator', 'Admin']);
    }

    /*
     *  Scope: Return users that have joined the friend group as a non creator
     */
    public function scopeJoinedGroupAsNonCreator($query)
    {
        return $query->joinedGroup()->where('user_friend_group_association.role', '!=', 'Creator');
    }

    /*
     *  Scope: Return users that have left the friend group
     */
    public function scopeLeftGroup($query)
    {
        return $query->where('user_friend_group_association.status', 'Left');
    }

    /*
     *  Scope: Return users that have not responded to the invitation to join the friend group
     */
    public function scopeInvitedToJoinGroup($query)
    {
        return $query->where('user_friend_group_association.status', 'Invited');
    }

    /*
     *  Scope: Return users that have declined the invitation to join the friend group
     */
    public function scopeDeclinedToJoinGroup($query)
    {
        return $query->where('user_friend_group_association.status', 'Declined');
    }
}
