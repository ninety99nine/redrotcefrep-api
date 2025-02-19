<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Repositories\StoreRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Store\ShowStoresRequest;
use App\Http\Requests\Models\Store\CreateStoreRequest;
use App\Http\Requests\Models\Store\UpdateStoreRequest;
use App\Http\Requests\Models\Store\InviteFollowersRequest;
use App\Http\Requests\Models\Store\InviteTeamMembersRequest;
use App\Http\Requests\Models\Store\ShowStoreInsightsRequest;
use App\Http\Requests\Models\Store\SearchStoreByAliasRequest;
use App\Http\Requests\Models\Store\ShowLastVisitedStoreRequest;
use App\Http\Requests\Models\Store\RemoveStoreTeamMembersRequest;
use App\Http\Requests\Models\Store\SearchStoreByUssdMobileNumberRequest;
use App\Http\Requests\Models\Store\UpdateStoreTeamMemberPermissionsRequest;

class StoreController extends BaseController
{
    protected StoreRepository $repository;

    /**
     * StoreController constructor.
     *
     * @param StoreRepository $repository
     */
    public function __construct(StoreRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show stores.
     *
     * @param ShowStoresRequest $request
     * @return JsonResponse
     */
    public function showStores(ShowStoresRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStores($request->all()));
    }

    /**
     * Create store.
     *
     * @param CreateFriendGroupRequest $request
     * @return JsonResponse
     */
    public function createStore(CreateStoreRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createStore($request->all()));
    }

    /**
     * Show last visited store.
     *
     * @param ShowLastVisitedStoreRequest $request
     * @param string $alias
     * @return JsonResponse
     */
    public function showLastVisitedStore(ShowLastVisitedStoreRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showLastVisitedStore($request->input('alias')));
    }

    /**
     * Show store deposit percentage options.
     *
     * @return JsonResponse
     */
    public function showStoreDepositOptions(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreDepositOptions());
    }

    /**
     * Show store installment percentage options.
     *
     * @return JsonResponse
     */
    public function showStoreInstallmentOptions(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreInstallmentOptions());
    }

    /**
     * Search store by alias.
     *
     * @param SearchStoreByAliasRequest $request
     * @param string $alias
     * @return JsonResponse
     */
    public function searchStoreByAlias(SearchStoreByAliasRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->searchStoreByAlias($request->input('alias')));
    }

    /**
     * Search store by USSD mobile number.
     *
     * @param SearchStoreByUssdMobileNumberRequest $request
     * @param string $ussdMobileNumber
     * @return JsonResponse
     */
    public function searchStoreByUssdMobileNumber(SearchStoreByUssdMobileNumberRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->searchStoreByUssdMobileNumber($request->input('ussd_mobile_number')));
    }

    /**
     * Check invitations to follow stores.
     *
     * @return JsonResponse
     */
    public function checkInvitationsToFollowStores()
    {
        return $this->prepareOutput($this->repository->checkInvitationsToFollowStores());
    }

    /**
     * Accept all invitations to follow stores.
     *
     * @return JsonResponse
     */
    public function acceptAllInvitationsToFollowStores(): JsonResponse
    {
        return $this->prepareOutput($this->repository->acceptAllInvitationsToFollowStores());
    }

    /**
     * Decline all invitations to follow stores.
     *
     * @return JsonResponse
     */
    public function declineAllInvitationsToFollowStores(): JsonResponse
    {
        return $this->prepareOutput($this->repository->declineAllInvitationsToFollowStores());
    }

    /**
     * Check invitations to join stores.
     *
     * @return JsonResponse
     */
    public function checkInvitationsToJoinStores()
    {
        return $this->prepareOutput($this->repository->checkInvitationsToJoinStores());
    }

    /**
     * Accept all invitations to join stores.
     *
     * @return JsonResponse
     */
    public function acceptAllInvitationsToJoinStores(): JsonResponse
    {
        return $this->prepareOutput($this->repository->acceptAllInvitationsToJoinStores());
    }

    /**
     * Decline all invitations to join stores.
     *
     * @return JsonResponse
     */
    public function declineAllInvitationsToJoinStores(): JsonResponse
    {
        return $this->prepareOutput($this->repository->declineAllInvitationsToJoinStores());
    }

    /**
     * Show store.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStore(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStore($storeId));
    }

    /**
     * Update store.
     *
     * @param UpdateStoreRequest $request
     * @param string $storeId
     * @return JsonResponse
     */
    public function updateStore(UpdateStoreRequest $request, string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateStore($storeId, $request->all()));
    }

    /**
     * Delete store.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function deleteStore(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteStore($storeId));
    }

    /**
     * Show store logo.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStoreLogo(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreLogo($storeId));
    }

    /**
     * Upload store logo.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function uploadStoreLogo(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->uploadStoreLogo($storeId));
    }

    /**
     * Show store cover photo.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStoreCoverPhoto(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreCoverPhoto($storeId));
    }

    /**
     * Upload store cover photo.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function uploadStoreCoverPhoto(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->uploadStoreCoverPhoto($storeId));
    }

    /**
     * Show store adverts.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStoreAdverts(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreAdverts($storeId));
    }

    /**
     * Upload store advert.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function uploadStoreAdvert(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->uploadStoreAdvert($storeId));
    }

    /**
     * Show store qr code image preview.
     *
     * @param string $storeId
     * @return View
     */
    public function showStoreQrCodeImagePreview(string $storeId): View
    {
        return $this->prepareOutput($this->repository->showStoreQrCodeImagePreview($storeId));
    }

    /**
     * Show store quick start guide.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStoreQuickStartGuide(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreQuickStartGuide($storeId));
    }

    /**
     * Show store insights.
     *
     * @param string $storeId
     * @param ShowStoreInsightsRequest $request
     * @return JsonResponse
     */
    public function showStoreInsights(ShowStoreInsightsRequest $request, string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreInsights($storeId, $request->all()));
    }

    /**
     * Show store followers.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStoreFollowers(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreFollowers($storeId));
    }

    /**
     * Invite store followers.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function inviteStoreFollowers(InviteFollowersRequest $request, string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->inviteStoreFollowers($storeId, $request->input('mobile_numbers')));
    }

    /**
     * Show store following.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStoreFollowing(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreFollowing($storeId));
    }

    /**
     * Update store following.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function updateStoreFollowing(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateStoreFollowing($storeId));
    }

    /**
     * Accept invitation to follow store.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function acceptInvitationToFollowStore(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->acceptInvitationToFollowStore($storeId));
    }

    /**
     * Decline invitation to follow store.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function declineInvitationToFollowStore(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->declineInvitationToFollowStore($storeId));
    }

    /**
     * Show team member permission options.
     *
     * @return JsonResponse
     */
    public function showTeamMemberPermissionOptions(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showTeamMemberPermissionOptions());
    }

    /**
     * Show my store permissions.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showMyStorePermissions(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showMyStorePermissions($storeId));
    }

    /**
     * Show store team members.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function showStoreTeamMembers(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreTeamMembers($storeId));
    }

    /**
     * Invite store team members.
     *
     * @param InviteTeamMembersRequest $request
     * @param string $storeId
     * @return JsonResponse
     */
    public function inviteStoreTeamMembers(InviteTeamMembersRequest $request, string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->inviteStoreTeamMembers($storeId, $request->input('mobile_numbers'), $request->input('permissions')));
    }

    /**
     * Remove store team members.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function removeStoreTeamMembers(RemoveStoreTeamMembersRequest $request, string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->removeStoreTeamMembers($storeId, $request->input('mobile_numbers')));
    }

    /**
     * Show store team member.
     *
     * @param string $storeId
     * @param string $teamMemberId
     * @return JsonResponse
     */
    public function showStoreTeamMember(string $storeId, string $teamMemberId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreTeamMember($storeId, $teamMemberId));
    }

    /**
     * Show store team member permissions.
     *
     * @param string $storeId
     * @param string $teamMemberId
     * @return JsonResponse
     */
    public function showStoreTeamMemberPermissions(string $storeId, string $teamMemberId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showStoreTeamMemberPermissions($storeId, $teamMemberId));
    }

    /**
     * Update store team member permissions.
     *
     * @param UpdateStoreTeamMemberPermissionsRequest $request
     * @param string $storeId
     * @param string $teamMemberId
     * @return JsonResponse
     */
    public function updateStoreTeamMemberPermissions(UpdateStoreTeamMemberPermissionsRequest $request, string $storeId, string $teamMemberId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateStoreTeamMemberPermissions($storeId, $teamMemberId, $request->input('permissions')));
    }

    /**
     * Accept invitation to join store team.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function acceptInvitationToJoinStoreTeam(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->acceptInvitationToJoinStoreTeam($storeId));
    }

    /**
     * Decline invitation to join store team.
     *
     * @param string $storeId
     * @return JsonResponse
     */
    public function declineInvitationToJoinStoreTeam(string $storeId): JsonResponse
    {
        return $this->prepareOutput($this->repository->declineInvitationToJoinStoreTeam($storeId));
    }
}
