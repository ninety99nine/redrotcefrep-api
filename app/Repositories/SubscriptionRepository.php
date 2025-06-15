<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\Store;
use App\Traits\AuthTrait;
use App\Models\AiAssistant;
use App\Models\Subscription;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\SubscriptionResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class SubscriptionRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show subscriptions.
     *
     * @param array $data
     * @return SubscriptionResources|array
     */
    public function showSubscriptions(array $data = []): SubscriptionResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;
            $aiAssistantId = isset($data['ai_assistant_id']) ? $data['ai_assistant_id'] : null;

            if($storeId) {
                $store = Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show subscriptions'];
                    $this->setQuery($store->subscriptions()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }else if($aiAssistantId) {
                $aiAssistant = AiAssistant::find($aiAssistantId);
                $isAuthourized = $this->isAuthourized() || request()->current_user->id == $aiAssistant->user_id;
                if(!$isAuthourized) return ['message' => 'You do not have permission to show subscriptions'];
                $this->setQuery($aiAssistant->subscriptions()->latest());
            }else {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show subscriptions'];
                $this->setQuery(Subscription::latest());
            }
        }

        return $this->getOutput();
    }

    /**
     * Create subscription.
     *
     * @param array $data
     * @param Model|null $model
     * @return Subscription|array
     */
    public function createSubscription(array $data, Model|null $model = null): Subscription|array
    {
        if($model == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to create subscriptions'];
            if(isset($data['store_id'])) {
                $model = Store::find($data['store_id']);
                if(!$model) return ['created' => false, 'message' => 'This store does not exist'];
            }else if(isset($data['ai_assistant_id'])) {
                $model = AiAssistant::find($data['ai_assistant_id']);
                if(!$model) return ['created' => false, 'message' => 'This AI Assistant does not exist'];
            }
        }

        $subscriptionPayload = $this->prepareSubscriptionPayload($model, $data);
        $subscription = Subscription::create($subscriptionPayload);

        if(($aiAssistant = $model) instanceof AiAssistant) {
            if(isset($data['replace_credits']) && $this->isTruthy($data['replace_credits'])) {
                $totalPaidCredits = $data['credits'];
            }else{
                $totalPaidCredits = $aiAssistant->remaining_paid_tokens + $data['credits'];
            }

            $aiAssistant->update([
                'total_paid_tokens' => $totalPaidCredits,
                'remaining_paid_tokens' => $totalPaidCredits
            ]);
        }

        return $this->showCreatedResource($subscription);
    }

    /**
     * Delete subscriptions.
     *
     * @param array $subscriptionIds
     * @return array
     */
    public function deleteSubscriptions(array $subscriptionIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete subscriptions'];
        $subscriptions = $this->setQuery(Subscription::query())->getSubscriptionsByIds($subscriptionIds);

        if($totalSubscriptions = $subscriptions->count()) {

            foreach($subscriptions as $subscription) {
                $subscription->delete();
            }

            return ['deleted' => true, 'message' => $totalSubscriptions  .($totalSubscriptions  == 1 ? ' subscription': ' subscriptions') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No subscriptions deleted'];
        }
    }

    /**
     * Show subscription.
     *
     * @param Subscription|string|null $subscriptionId
     * @return Subscription|array|null
     */
    public function showSubscription(Subscription|string|null $subscriptionId = null): Subscription|array|null
    {
        if(($subscription = $subscriptionId) instanceof Subscription) {
            $subscription = $this->applyEagerLoadingOnModel($subscription);
        }else {
            $query = $this->getQuery() ?? Subscription::query();
            if($subscriptionId) $query = $query->where('subscriptions.id', $subscriptionId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $subscription = $this->query->first();
        }

        return $this->showResourceExistence($subscription);
    }

    /**
     * Update subscription.
     *
     * @param string $subscriptionId
     * @param array $data
     * @return Subscription|array
     */
    public function updateSubscription(string $subscriptionId, array $data): Subscription|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update subscription'];
        $subscription = Subscription::find($subscriptionId);

        if($subscription) {

            $model = $subscription->owner;
            if(!$model && $subscription->owner_type == (new AiAssistant())->getResourceName()) return ['updated' => false, 'message' => 'The subscription AI Assistant does not exist'];
            if(!$model && $subscription->owner_type == (new Store)->getResourceName()) return ['updated' => false, 'message' => 'The subscription store does not exist'];

            $data = $this->prepareSubscriptionPayload($model, $data);
            $subscription->update($data);

            if(($aiAssistant = $model) instanceof AiAssistant) {
                if(isset($data['replace_credits']) && $this->isTruthy($data['replace_credits'])) {
                    $totalPaidCredits = $data['credits'];
                }else{
                    $totalPaidCredits = $aiAssistant->remaining_paid_tokens + $data['credits'];
                }

                $aiAssistant->update([
                    'total_paid_tokens' => $totalPaidCredits,
                    'remaining_paid_tokens' => $totalPaidCredits
                ]);
            }

            return $this->showUpdatedResource($subscription);

        }else{
            return ['updated' => false, 'message' => 'This subscription does not exist'];
        }
    }

    /**
     * Delete subscription.
     *
     * @param string $subscriptionId
     * @return array
     */
    public function deleteSubscription(string $subscriptionId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete subscription'];
        $subscription = Subscription::find($subscriptionId);

        if($subscription) {
            $deleted = $subscription->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Subscription deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Subscription delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This subscription does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query subscription by ID.
     *
     * @param Subscription|string $subscriptionId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function querySubscriptionById(Subscription|string $subscriptionId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('subscriptions.id', $subscriptionId)->with($relationships);
    }

    /**
     * Get subscription by ID.
     *
     * @param Subscription|string $subscriptionId
     * @param array $relationships
     * @return Subscription|null
     */
    public function getSubscriptionById(Subscription|string $subscriptionId, array $relationships = []): Subscription|null
    {
        return $this->querySubscriptionById($subscriptionId, $relationships)->first();
    }

    /**
     * Query subscriptions by IDs.
     *
     * @param array<string> $subscriptionId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function querySubscriptionsByIds($subscriptionIds): Builder|Relation
    {
        return $this->query->whereIn('subscriptions.id', $subscriptionIds);
    }

    /**
     * Get subscriptions by IDs.
     *
     * @param array<string> $subscriptionId
     * @param string $relationships
     * @return Collection
     */
    public function getSubscriptionsByIds($subscriptionIds): Collection
    {
        return $this->querySubscriptionsByIds($subscriptionIds)->get();
    }

    /**
     * Prepare subscription payload.
     *
     * @param Mode $mode
     * @param array $data
     * @return array
     */
    public function prepareSubscriptionPayload(Model $model, array $data): array
    {
        $duration = $data['duration'];
        $frequency = $data['frequency'];
        $userId = $data['user_id'] ?? null;
        $transactionId = isset($data['transaction_id']) ? $data['transaction_id'] : null;
        $pricingPlanId = isset($data['pricing_plan_id']) ? $data['pricing_plan_id'] : null;

        $subscription = $model->subscriptions()->orderBy('end_at', 'DESC')->first();
        $startAt = $subscription ? $subscription->end_at : now();

        return [
            'user_id' => $userId,
            'start_at' => $startAt,
            'owner_id' => $model->id,
            'transaction_id' => $transactionId,
            'pricing_plan_id' => $pricingPlanId,
            'owner_type' => $model->getResourceName(),
            'end_at' => $this->calculateSubscriptionEndAt($startAt, $frequency, $duration)
        ];
    }

    /**
     * Calculate subscription end at.
     *
     * @param Carbon|null $startAt
     * @param string $frequency
     * @param int $duration
     * @return Carbon|null
     */
    public function calculateSubscriptionEndAt(Carbon|null $startAt, string $frequency, int $duration): Carbon|null
    {
        $startAt = clone ($startAt ?? now());

        switch (strtolower($frequency)) {
            case 'day':
                return $startAt->addDays($duration);
                break;
            case 'week':
                return $startAt->addWeeks($duration);
                break;
            case 'month':
                return $startAt->addMonths($duration);
                break;
            case 'year':
                return $startAt->addYears($duration);
                break;
            default:
                return null;
                break;
        }
    }
}
