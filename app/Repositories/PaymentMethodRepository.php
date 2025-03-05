<?php

namespace App\Repositories;

use App\Models\Store;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Models\PaymentMethod;
use App\Traits\Base\BaseTrait;
use App\Enums\PaymentMethodType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\PaymentMethodResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class PaymentMethodRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show payment methods.
     *
     * @return PaymentMethodResources|array
     */
    public function showPaymentMethods(array $data = []): PaymentMethodResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;

            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show payment methods'];
                $this->setQuery(PaymentMethod::latest());
            }else{

                $store = Store::find($storeId);

                if($store) {

                    $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

                    if($association == Association::UNASSOCIATED) {

                        $existingPaymentMethodTypes = $store->paymentMethods()->pluck('type');

                        $excludeTypes = [
                            ...$existingPaymentMethodTypes,
                            PaymentMethodType::ORANGE_AIRTIME->value,
                            PaymentMethodType::DPO->value
                        ];

                        $query = PaymentMethod::select('id', 'name', 'type', 'automated_verification', 'config_schema')
                            ->where(function ($query) use ($store) {
                                $query->whereJsonContains('supported_countries', $store->country)
                                    ->orWhereNull('supported_countries');
                            })
                            ->whereNotIn('type', $excludeTypes)
                            ->orderBy('position')
                            ->latest();


                        $this->setQuery($query);

                    }else{

                        $this->setQuery($store->paymentMethods()->orderBy('position'));

                    }

                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create payment method.
     *
     * @param array $data
     * @return PaymentMethod|array
     */
    public function createPaymentMethod(array $data): PaymentMethod|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create payment methods'];
        $paymentMethod = PaymentMethod::create($data);
        return $this->showCreatedResource($paymentMethod);
    }

    /**
     * Delete payment methods.
     *
     * @param array $paymentMethodIds
     * @return array
     */
    public function deletePaymentMethods(array $paymentMethodIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete payment methods'];

        $paymentMethods = $this->setQuery(PaymentMethod::query())->getPaymentMethodsByIds($paymentMethodIds);

        if($totalPaymentMethods = $paymentMethods->count()) {

            foreach($paymentMethods as $paymentMethod) {
                $paymentMethod->delete();
            }

            return ['deleted' => true, 'message' => $totalPaymentMethods  .($totalPaymentMethods  == 1 ? ' payment method': ' payment methods') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No payment methods deleted'];
        }
    }

    /**
     * Update payment method arrangement
     *
     * @param array $data
     * @return array
     */
    public function updatePaymentMethodArrangement(array $data): array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update payment method arrangement'];
        $this->setQuery(PaymentMethod::orderBy('position', 'asc'));

        $paymentMethodIds = $data['payment_method_ids'];

        $paymentMethods = $this->query->get();
        $originalPaymentMethodPositions = $paymentMethods->pluck('position', 'id');

        $arrangement = collect($paymentMethodIds)->filter(function ($PaymentMethodId) use ($originalPaymentMethodPositions) {
            return collect($originalPaymentMethodPositions)->keys()->contains($PaymentMethodId);
        })->toArray();

        $movedPaymentMethodPositions = collect($arrangement)->mapWithKeys(function ($PaymentMethodId, $newPosition) use ($originalPaymentMethodPositions) {
            return [$PaymentMethodId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalPaymentMethodPositions = $originalPaymentMethodPositions->except(collect($movedPaymentMethodPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedPaymentMethodPositions) {
            return [$id => count($movedPaymentMethodPositions) + $index + 1];
        })->toArray();

        $paymentMethodPositions = $movedPaymentMethodPositions + $adjustedOriginalPaymentMethodPositions;

        if(count($paymentMethodPositions)) {

            DB::table('payment_methods')
                ->whereIn('id', array_keys($paymentMethodPositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($paymentMethodPositions), $paymentMethodPositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Payment method arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching payment methods to update'];
    }

    /**
     * Show payment method.
     *
     * @param PaymentMethod|string|null $paymentMethodId
     * @return PaymentMethod|array|null
     */
    public function showPaymentMethod(PaymentMethod|string|null $paymentMethodId = null): PaymentMethod|array|null
    {
        if(($paymentMethod = $paymentMethodId) instanceof PaymentMethod) {
            $paymentMethod = $this->applyEagerLoadingOnModel($paymentMethod);
        }else {
            $query = $this->getQuery() ?? PaymentMethod::query();
            if($paymentMethodId) $query = $query->where('payment_methods.id', $paymentMethodId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $paymentMethod = $this->query->first();
        }

        return $this->showResourceExistence($paymentMethod);
    }

    /**
     * Update payment method.
     *
     * @param PaymentMethod|string $paymentMethodId
     * @param array $data
     * @return PaymentMethod|array
     */
    public function updatePaymentMethod(PaymentMethod|string $paymentMethodId, array $data): PaymentMethod|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update payment method'];

        $paymentMethod = PaymentMethod::find($paymentMethodId);

        if($paymentMethod) {

            $paymentMethod->update($data);
            return $this->showUpdatedResource($paymentMethod);

        }else{
            return ['updated' => false, 'message' => 'This payment method does not exist'];
        }
    }

    /**
     * Delete payment method.
     *
     * @param PaymentMethod|string $paymentMethodId
     * @return array
     */
    public function deletePaymentMethod(PaymentMethod|string $paymentMethodId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete payment method'];

        $paymentMethod = PaymentMethod::find($paymentMethodId);

        if($paymentMethod) {

            $deleted = $paymentMethod->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Payment method deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Payment method delete unsuccessful'];
            }

        }else{
            return ['deleted' => false, 'message' => 'This payment method does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query payment method by ID.
     *
     * @param string $paymentMethodId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryPaymentMethodById(string $paymentMethodId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('payment_methods.id', $paymentMethodId)->with($relationships);
    }

    /**
     * Get payment method by ID.
     *
     * @param string $paymentMethodId
     * @param array $relationships
     * @return PaymentMethod|null
     */
    public function getPaymentMethodById(string $paymentMethodId, array $relationships = []): PaymentMethod|null
    {
        return $this->queryPaymentMethodById($paymentMethodId, $relationships)->first();
    }

    /**
     * Query payment methods by IDs.
     *
     * @param array<string> $paymentMethodId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryPaymentMethodsByIds($paymentMethodIds): Builder|Relation
    {
        return $this->query->whereIn('payment_methods.id', $paymentMethodIds);
    }

    /**
     * Get payment methods by IDs.
     *
     * @param array<string> $paymentMethodId
     * @param string $relationships
     * @return Collection
     */
    public function getPaymentMethodsByIds($paymentMethodIds): Collection
    {
        return $this->queryPaymentMethodsByIds($paymentMethodIds)->get();
    }
}
