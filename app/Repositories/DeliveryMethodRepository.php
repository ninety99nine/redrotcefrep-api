<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\Store;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Models\DeliveryMethod;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\DeliveryMethodScheduleType;
use App\Http\Resources\DeliveryMethodResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class DeliveryMethodRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show delivery methods.
     *
     * @param array $data
     * @return DeliveryMethodResources|array
     */
    public function showDeliveryMethods(array $data = []): DeliveryMethodResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;

            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show delivery methods'];
                $this->setQuery(DeliveryMethod::orderBy('position'));
            }else{

                $store = Store::find($storeId);

                if($store) {

                    $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

                    if($association == Association::SHOPPER) {

                        $this->setQuery($store->deliveryMethods()->active()->orderBy('position'));

                    }else{

                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show delivery methods'];
                        $this->setQuery($store->deliveryMethods()->orderBy('position'));

                    }

                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->getOutput();
    }

    /**
     * Create delivery method.
     *
     * @param array $data
     * @return DeliveryMethod|array
     */
    public function createDeliveryMethod(array $data): DeliveryMethod|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create delivery methods'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'currency' => $store->currency,
            'store_id' => $storeId
        ]);

        $deliveryMethod = DeliveryMethod::create($data);

        if(isset($data['address'])) {
            $deliveryMethod->address()->create($data['address']);
        }

        $this->updateDeliveryMethodArrangement([
            'store_id' => $storeId,
            'delivery_method_ids' => [
                $deliveryMethod->id
            ]
        ]);

        return $this->showCreatedResource($deliveryMethod);
    }

    /**
     * Delete delivery methods.
     *
     * @param array $data
     * @return array
     */
    public function deleteDeliveryMethods(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete delivery methods'];
            $this->setQuery(DeliveryMethod::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete delivery methods'];
                $this->setQuery($store->deliveryMethods());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $deliveryMethodIds = $data['delivery_method_ids'];
        $deliveryMethods = $this->getDeliveryMethodsByIds($deliveryMethodIds);

        if($totalDeliveryMethods = $deliveryMethods->count()) {

            foreach($deliveryMethods as $deliveryMethod) {
                $deliveryMethod->delete();
            }

            return ['deleted' => true, 'message' => $totalDeliveryMethods . ($totalDeliveryMethods == 1 ? ' delivery method': ' delivery methods') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No delivery methods deleted'];
        }
    }

    /**
     * Update delivery method arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateDeliveryMethodArrangement(array $data): array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update delivery method arrangement'];
            $this->setQuery($store->deliveryMethods()->orderBy('position', 'asc'));
        }else{
            return ['message' => 'This store does not exist'];
        }

        $deliveryMethodIds = $data['delivery_method_ids'];

        $deliveryMethods = $this->query->get();
        $originalDeliveryMethodPositions = $deliveryMethods->pluck('position', 'id');

        $arrangement = collect($deliveryMethodIds)->filter(function ($DeliveryMethodId) use ($originalDeliveryMethodPositions) {
            return collect($originalDeliveryMethodPositions)->keys()->contains($DeliveryMethodId);
        })->toArray();

        $movedDeliveryMethodPositions = collect($arrangement)->mapWithKeys(function ($DeliveryMethodId, $newPosition) use ($originalDeliveryMethodPositions) {
            return [$DeliveryMethodId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalDeliveryMethodPositions = $originalDeliveryMethodPositions->except(collect($movedDeliveryMethodPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedDeliveryMethodPositions) {
            return [$id => count($movedDeliveryMethodPositions) + $index + 1];
        })->toArray();

        $deliveryMethodPositions = $movedDeliveryMethodPositions + $adjustedOriginalDeliveryMethodPositions;

        if(count($deliveryMethodPositions)) {

            DB::table('delivery_methods')
                ->where('store_id', $store->id)
                ->whereIn('id', array_keys($deliveryMethodPositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($deliveryMethodPositions), $deliveryMethodPositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Delivery method arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching delivery methods to update'];
    }

    /**
     * Show delivery method schedule options.
     *
     * @param array $data
     * @return array
     */
    public function showDeliveryMethodScheduleOptions(array $data): array
    {
        $deliveryDate = $data['delivery_date'] ?? null;
        $data['set_schedule'] = true;

        $deliveryMethod = new DeliveryMethod();
        $deliveryMethod->fill($data);

        $scheduleOptions = [
            'delivery_message' => null,
            'available_time_slots' => [],
            'min_date' => $deliveryMethod->minDate(),
            'max_date' => $deliveryMethod->maxDate(),
            'dates_disabled' => $deliveryMethod->datesDisabled(),
            'days_of_week_disabled' => $deliveryMethod->daysOfWeekDisabled(),
            'schedule_key_points' => [] // Add explanations here
        ];

        $availableDays = collect($deliveryMethod->operational_hours)
            ->filter(fn($day) => $day['available'])
            ->keys()
            ->map(fn($dayIndex) => Carbon::create()->startOfWeek()->addDays($dayIndex)->format('l'))
            ->toArray();

        if (empty($availableDays)) {
            $scheduleOptions['schedule_key_points'][] = 'Orders are allowed on any day of the week';
        } elseif (count($availableDays) == 7) {
            $scheduleOptions['schedule_key_points'][] = 'Orders are allowed on all days of the week';
        } else {
            $formattedDays = count($availableDays) > 1
                ? implode(', ', array_slice($availableDays, 0, -1)) . ' and ' . end($availableDays)
                : $availableDays[0];

            $scheduleOptions['schedule_key_points'][] = sprintf(
                'Orders are allowed on %d %s of the week: %s',
                count($availableDays), count($availableDays) == 1 ? 'day' : 'days', $formattedDays
            );
        }

        if ($deliveryMethod->schedule_type == DeliveryMethodScheduleType::DATE->value) {
            $scheduleOptions['schedule_key_points'][] = 'Customers must specify only date without the time for delivery';
        } else {
            $scheduleOptions['schedule_key_points'][] = 'Customers must specify both date and time for delivery';
        }

        if ($deliveryMethod->schedule_type == DeliveryMethodScheduleType::DATE_AND_TIME->value && $deliveryMethod->auto_generate_timeslots) {
            $scheduleOptions['schedule_key_points'][] = 'Auto generate additional time options between the specified timeslots for each day of the week';
        }

        // Minimum notice for orders
        if ($deliveryMethod->require_minimum_notice_for_orders && $deliveryMethod->earliest_delivery_time_value > 0) {
            $unit = $deliveryMethod->earliest_delivery_time_unit;
            $value = $deliveryMethod->earliest_delivery_time_value;
            $unitText = $value == 1 ? $unit : $unit . 's';

            $scheduleOptions['schedule_key_points'][] = sprintf(
                'Orders must be placed at least %d %s before the delivery date (%d %s notice)',
                $value, $unitText, $value, $unitText
            );
        }

        // Maximum notice for orders
        if ($deliveryMethod->restrict_maximum_notice_for_orders && $deliveryMethod->latest_delivery_time_value > 0) {
            $value = $deliveryMethod->latest_delivery_time_value;
            $unitText = $value == 1 ? 'day' : 'days';

            $scheduleOptions['schedule_key_points'][] = sprintf(
                'Orders cannot be scheduled for delivery more than %d %s in advance',
                $value, $unitText
            );
        }

        // Delivery message
        if ($deliveryDate && $deliveryMethod->schedule_type == DeliveryMethodScheduleType::DATE_AND_TIME->value) {
            $isValidDate = $deliveryMethod->isValidDate($deliveryDate);

            if ($isValidDate) {
                $scheduleOptions['available_time_slots'] = $deliveryMethod->availableTimeSlots($deliveryDate);

                // Format delivery message
                $deliveryDate = Carbon::parse($deliveryDate);

                $scheduleOptions['delivery_message'] = sprintf(
                    'Your order will be delivered on %s (%s), just %s from now.',
                    $deliveryDate->format('d M Y'),                 // e.g., "15 Jan 2025"
                    $deliveryDate->format('D'),                     // e.g., "Wed"
                    $deliveryDate->diffForHumans(null, true)        // e.g., "6 days"
                );
            }
        }

        return $scheduleOptions;
    }

    /**
     * Show delivery method.
     *
     * @param string $deliveryMethodId
     * @return DeliveryMethod|array|null
     */
    public function showDeliveryMethod(string $deliveryMethodId): DeliveryMethod|array|null
    {
        $deliveryMethod = $this->setQuery(DeliveryMethod::with(['store'])->whereId($deliveryMethodId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($deliveryMethod) {
            $store = $deliveryMethod->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show delivery method'];
                if(!$this->checkIfHasRelationOnRequest('store')) $deliveryMethod->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($deliveryMethod);
    }

    /**
     * Update delivery method.
     *
     * @param string $deliveryMethodId
     * @param array $data
     * @return DeliveryMethod|array
     */
    public function updateDeliveryMethod(string $deliveryMethodId, array $data): DeliveryMethod|array
    {
        $deliveryMethod = DeliveryMethod::with(['store'])->find($deliveryMethodId);

        if($deliveryMethod) {
            $store = $deliveryMethod->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update delivery method'];
                if(!$this->checkIfHasRelationOnRequest('store')) $deliveryMethod->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $deliveryMethod->update($data);
            return $this->showUpdatedResource($deliveryMethod);

        }else{
            return ['updated' => false, 'message' => 'This delivery method does not exist'];
        }
    }

    /**
     * Delete delivery method.
     *
     * @param string $deliveryMethodId
     * @return array
     */
    public function deleteDeliveryMethod(string $deliveryMethodId): array
    {
        $deliveryMethod = DeliveryMethod::with(['store'])->find($deliveryMethodId);

        if($deliveryMethod) {
            $store = $deliveryMethod->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete delivery method'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $deliveryMethod->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Delivery method deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Delivery method delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This delivery method does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query delivery method by ID.
     *
     * @param DeliveryMethod|string $deliveryMethodId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryDeliveryMethodById(DeliveryMethod|string $deliveryMethodId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('delivery_methods.id', $deliveryMethodId)->with($relationships);
    }

    /**
     * Get delivery method by ID.
     *
     * @param DeliveryMethod|string $deliveryMethodId
     * @param array $relationships
     * @return DeliveryMethod|null
     */
    public function getDeliveryMethodById(DeliveryMethod|string $deliveryMethodId, array $relationships = []): DeliveryMethod|null
    {
        return $this->queryDeliveryMethodById($deliveryMethodId, $relationships)->first();
    }

    /**
     * Query delivery methods by IDs.
     *
     * @param array<string> $deliveryMethodId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryDeliveryMethodsByIds($deliveryMethodIds): Builder|Relation
    {
        return $this->query->whereIn('delivery_methods.id', $deliveryMethodIds);
    }

    /**
     * Get delivery methods by IDs.
     *
     * @param array<string> $deliveryMethodId
     * @param string $relationships
     * @return Collection
     */
    public function getDeliveryMethodsByIds($deliveryMethodIds): Collection
    {
        return $this->queryDeliveryMethodsByIds($deliveryMethodIds)->get();
    }
}
