<?php

namespace App\Repositories;

use App\Models\Store;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use App\Models\MobileVerification;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\MobileVerificationResources;
use App\Services\CodeGenerator\CodeGeneratorService;
use Illuminate\Database\Eloquent\Relations\Relation;

class MobileVerificationRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show mobile verifications.
     *
     * @return MobileVerificationResources|array
     */
    public function showMobileVerifications(array $data = []): MobileVerificationResources|array
    {
        if($this->getQuery() == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show mobile verifications'];
            $this->setQuery(MobileVerification::query()->latest());
        }

        return $this->getOutput();
    }

    /**
     * Create mobile verification.
     *
     * @param array $data
     * @return MobileVerification|array
     */
    public function createMobileVerification(array $data): MobileVerification|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create mobile verifications'];

        $data['code'] = isset($data['code']) ? $data['code'] : CodeGeneratorService::generateRandomSixDigitNumber();
        $mobileVerification = MobileVerification::whereMobileNumber($data['mobile_number'])->first();

        if($mobileVerification) {
            $mobileVerification->update($data);
        }else{
            $mobileVerification = MobileVerification::create($data);
        }

        return $this->showCreatedResource($mobileVerification);
    }

    /**
     * Delete mobile verifications.
     *
     * @param array $mobileVerificationIds
     * @return array
     */
    public function deleteMobileVerifications(array $mobileVerificationIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete mobile verifications'];

        $mobileVerifications = $this->setQuery(MobileVerification::query())->getMobileVerificationsByIds($mobileVerificationIds);

        if($totalMobileVerifications = $mobileVerifications->count()) {

            foreach($mobileVerifications as $mobileVerification) {
                $mobileVerification->delete();
            }

            return ['deleted' => true, 'message' => $totalMobileVerifications  .($totalMobileVerifications  == 1 ? ' mobile verification': ' mobile verifications') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No mobile verifications deleted'];
        }
    }

    /**
     * Show mobile verification.
     *
     * @param MobileVerification|string|null $mobileVerificationId
     * @return MobileVerification|array|null
     */
    public function showMobileVerification(MobileVerification|string|null $mobileVerificationId = null): MobileVerification|array|null
    {
        if(($mobileVerification = $mobileVerificationId) instanceof MobileVerification) {
            $mobileVerification = $this->applyEagerLoadingOnModel($mobileVerification);
        }else {
            $query = $this->getQuery() ?? MobileVerification::query();
            if($mobileVerificationId) $query = $query->where('mobile_verifications.id', $mobileVerificationId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $mobileVerification = $this->query->first();
        }

        return $this->showResourceExistence($mobileVerification);
    }

    /**
     * Update mobile verification.
     *
     * @param MobileVerification|string $mobileVerificationId
     * @param array $data
     * @return MobileVerification|array
     */
    public function updateMobileVerification(MobileVerification|string $mobileVerificationId, array $data): MobileVerification|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update mobile verification'];

        $mobileVerification = $mobileVerificationId instanceof MobileVerification ? $mobileVerificationId : MobileVerification::find($mobileVerificationId);

        if($mobileVerification) {

            $data['code'] = isset($data['code']) ? $data['code'] : CodeGeneratorService::generateRandomSixDigitNumber();

            $mobileVerification->update(['code' => $data['code']]);
            return $this->showUpdatedResource($mobileVerification);

        }else{
            return ['updated' => false, 'message' => 'This mobile verification does not exist'];
        }
    }

    /**
     * Delete mobile verification.
     *
     * @param MobileVerification|string $mobileVerificationId
     * @return array
     */
    public function deleteMobileVerification(MobileVerification|string $mobileVerificationId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete mobile verification'];

        $mobileVerification = $mobileVerificationId instanceof MobileVerification ? $mobileVerificationId : MobileVerification::find($mobileVerificationId);

        if($mobileVerification) {
            $deleted = $mobileVerification->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Mobile verification deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Mobile verification delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This mobile verification does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query mobile verification by ID.
     *
     * @param string $mobileVerificationId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryMobileVerificationById(string $mobileVerificationId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('mobile_verifications.id', $mobileVerificationId)->with($relationships);
    }

    /**
     * Get mobile verification by ID.
     *
     * @param string $mobileVerificationId
     * @param array $relationships
     * @return MobileVerification|null
     */
    public function getMobileVerificationById(string $mobileVerificationId, array $relationships = []): MobileVerification|null
    {
        return $this->queryMobileVerificationById($mobileVerificationId, $relationships)->first();
    }

    /**
     * Query mobile verifications by IDs.
     *
     * @param array<string> $mobileVerificationId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryMobileVerificationsByIds($mobileVerificationIds): Builder|Relation
    {
        return $this->query->whereIn('mobile_verifications.id', $mobileVerificationIds);
    }

    /**
     * Get mobile verifications by IDs.
     *
     * @param array<string> $mobileVerificationId
     * @param string $relationships
     * @return Collection
     */
    public function getMobileVerificationsByIds($mobileVerificationIds): Collection
    {
        return $this->queryMobileVerificationsByIds($mobileVerificationIds)->get();
    }
}
