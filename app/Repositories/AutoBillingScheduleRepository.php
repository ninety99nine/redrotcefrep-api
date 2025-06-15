<?php

namespace App\Repositories;

use App\Jobs\SendSms;
use App\Enums\CacheName;
use App\Traits\AuthTrait;
use App\Helpers\CacheManager;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use App\Models\AutoBillingSchedule;
use App\Traits\MessageCrafterTrait;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\AutoBillingScheduleResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class AutoBillingScheduleRepository extends BaseRepository
{
    use AuthTrait, BaseTrait, MessageCrafterTrait;

    /**
     * Show auto billing schedules.
     *
     * @return AutoBillingScheduleResources|array
     */
    public function showAutoBillingSchedules(): AutoBillingScheduleResources|array
    {
        $userId = isset($data['user_id']) ? $data['user_id'] : null;

        if($userId) {
            $this->setQuery(AutoBillingSchedule::where('user_id', $userId)->when(!request()->has('_sort'), fn($query) => $query->latest()));
        }else{
            $this->setQuery(AutoBillingSchedule::query()->when(!request()->has('_sort'), fn($query) => $query->latest()));
        }

        return $this->getOutput();
    }

    /**
     * Create auto billing schedule.
     *
     * @param array $data
     * @return AutoBillingSchedule|array
     */
    public function createAutoBillingSchedule(array $data): AutoBillingSchedule|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create auto billing schedules.'];

        $autoBillingSchedule = AutoBillingSchedule::create($data);
        (new CacheManager(CacheName::TOTAL_ACTIVE_AUTO_BILLING_SCHEDULES))->append($autoBillingSchedule->user_id)->forget();

        return $this->showCreatedResource($autoBillingSchedule);
    }

    /**
     * Delete auto billing schedules.
     *
     * @param array $autoBillingScheduleIds
     * @return array
     */
    public function deleteAutoBillingSchedules(array $autoBillingScheduleIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete auto billing schedules.'];

        $autoBillingSchedules = $this->setQuery(AutoBillingSchedule::query())->getAutoBillingSchedulesByIds($autoBillingScheduleIds);

        if($totalAutoBillingSchedules = $autoBillingSchedules->count()) {

            foreach($autoBillingSchedules as $autoBillingSchedule) {
                $autoBillingSchedule->delete();
            }

            return ['deleted' => true, 'message' => $totalAutoBillingSchedules  .($totalAutoBillingSchedules  == 1 ? ' auto billing schedule': ' auto billing schedules.') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No auto billing schedules. deleted'];
        }
    }

    /**
     * Show auto billing schedule.
     *
     * @param AutoBillingSchedule|string|null $autoBillingScheduleId
     * @return AutoBillingSchedule|array|null
     */
    public function showAutoBillingSchedule(AutoBillingSchedule|string|null $autoBillingScheduleId = null): AutoBillingSchedule|array|null
    {
        if(($autoBillingSchedule = $autoBillingScheduleId) instanceof AutoBillingSchedule) {
            $autoBillingSchedule = $this->applyEagerLoadingOnModel($autoBillingSchedule);
        }else {
            $query = $this->getQuery() ?? AutoBillingSchedule::query();
            if($autoBillingScheduleId) $query = $query->where('auto_billing_schedules.id', $autoBillingScheduleId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $autoBillingSchedule = $this->query->first();
        }

        return $this->showResourceExistence($autoBillingSchedule);
    }

    /**
     * Update auto billing schedule.
     *
     * @param AutoBillingSchedule|string $autoBillingScheduleId
     * @param array $data
     * @return AutoBillingSchedule|array
     */
    public function updateAutoBillingSchedule(AutoBillingSchedule|string $autoBillingScheduleId, array $data): AutoBillingSchedule|array
    {
        $autoBillingSchedule = AutoBillingSchedule::with(['user', 'pricingPlan'])->find($autoBillingScheduleId);

        if(!$this->isAuthourized() && $autoBillingSchedule->user_id != request()->auth_user->id) return ['updated' => false, 'message' => 'You do not have permission to update auto billing schedule'];

        if($autoBillingSchedule) {

            $autoBillingSchedule->update($data);
            (new CacheManager(CacheName::TOTAL_ACTIVE_AUTO_BILLING_SCHEDULES))->append($autoBillingSchedule->user_id)->forget();

            $user = $autoBillingSchedule->user;
            $pricingPlan = $autoBillingSchedule->pricingPlan;

            if(!empty($pricingPlan->auto_billing_disabled_sms_message)) {

                $smsMessage = $this->craftAutoBillingDisabledMessage($pricingPlan);
                SendSms::dispatch($smsMessage, $user->mobile_number->formatE164());

            }

            return $this->showUpdatedResource($autoBillingSchedule);

        }else{
            return ['updated' => false, 'message' => 'This auto billing schedule does not exist'];
        }
    }

    /**
     * Delete auto billing schedule.
     *
     * @param AutoBillingSchedule|string $autoBillingScheduleId
     * @return array
     */
    public function deleteAutoBillingSchedule(AutoBillingSchedule|string $autoBillingScheduleId): array
    {
        $autoBillingSchedule = AutoBillingSchedule::find($autoBillingScheduleId);

        if(!$this->isAuthourized() && $autoBillingSchedule->user_id != request()->auth_user->id) return ['deleted' => false, 'message' => 'You do not have permission to delete auto billing schedule'];

        if($autoBillingSchedule) {

            $deleted = $autoBillingSchedule->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Auto billing schedule deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Auto billing schedule delete unsuccessful'];
            }

        }else{
            return ['deleted' => false, 'message' => 'This auto billing schedule does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query auto billing schedule by ID.
     *
     * @param string $autoBillingScheduleId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryAutoBillingScheduleById(string $autoBillingScheduleId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('auto_billing_schedules.id', $autoBillingScheduleId)->with($relationships);
    }

    /**
     * Get auto billing schedule by ID.
     *
     * @param string $autoBillingScheduleId
     * @param array $relationships
     * @return AutoBillingSchedule|null
     */
    public function getAutoBillingScheduleById(string $autoBillingScheduleId, array $relationships = []): AutoBillingSchedule|null
    {
        return $this->queryAutoBillingScheduleById($autoBillingScheduleId, $relationships)->first();
    }

    /**
     * Query auto billing schedules. by IDs.
     *
     * @param array<string> $autoBillingScheduleId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryAutoBillingSchedulesByIds($autoBillingScheduleIds): Builder|Relation
    {
        return $this->query->whereIn('auto_billing_schedules.id', $autoBillingScheduleIds);
    }

    /**
     * Get auto billing schedules. by IDs.
     *
     * @param array<string> $autoBillingScheduleId
     * @param string $relationships
     * @return Collection
     */
    public function getAutoBillingSchedulesByIds($autoBillingScheduleIds): Collection
    {
        return $this->queryAutoBillingSchedulesByIds($autoBillingScheduleIds)->get();
    }
}
