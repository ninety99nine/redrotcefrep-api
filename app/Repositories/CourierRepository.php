<?php

namespace App\Repositories;

use App\Models\Courier;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\CourierResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class CourierRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show couriers.
     *
     * @return CourierResources|array
     */
    public function showCouriers(): CourierResources|array
    {
        $this->setQuery(Courier::query()->when(!request()->has('_sort'), fn($query) => $query->latest()));
        return $this->getOutput();
    }

    /**
     * Create courier.
     *
     * @param array $data
     * @return Courier|array
     */
    public function createCourier(array $data): Courier|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create couriers'];
        $courier = Courier::create($data);
        return $this->showCreatedResource($courier);
    }

    /**
     * Delete couriers.
     *
     * @param array $courierIds
     * @return array
     */
    public function deleteCouriers(array $courierIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete couriers'];

        $couriers = $this->setQuery(Courier::query())->getCouriersByIds($courierIds);

        if($totalCouriers = $couriers->count()) {

            foreach($couriers as $courier) {
                $courier->delete();
            }

            return ['deleted' => true, 'message' => $totalCouriers  .($totalCouriers  == 1 ? ' courier': ' couriers') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No couriers deleted'];
        }
    }

    /**
     * Update courier arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateCourierArrangement(array $data): array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update courier arrangement'];
        $this->setQuery(Courier::orderBy('position', 'asc'));

        $courierIds = $data['courier_ids'];

        $couriers = $this->query->get();
        $originalCourierPositions = $couriers->pluck('position', 'id');

        $arrangement = collect($courierIds)->filter(function ($CourierId) use ($originalCourierPositions) {
            return collect($originalCourierPositions)->keys()->contains($CourierId);
        })->toArray();

        $movedCourierPositions = collect($arrangement)->mapWithKeys(function ($CourierId, $newPosition) use ($originalCourierPositions) {
            return [$CourierId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalCourierPositions = $originalCourierPositions->except(collect($movedCourierPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedCourierPositions) {
            return [$id => count($movedCourierPositions) + $index + 1];
        })->toArray();

        $courierPositions = $movedCourierPositions + $adjustedOriginalCourierPositions;

        if(count($courierPositions)) {

            DB::table('couriers')
                ->whereIn('id', array_keys($courierPositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($courierPositions), $courierPositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Courier arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching couriers to update'];
    }

    /**
     * Show courier.
     *
     * @param Courier|string|null $courierId
     * @return Courier|array|null
     */
    public function showCourier(Courier|string|null $courierId = null): Courier|array|null
    {
        if(($courier = $courierId) instanceof Courier) {
            $courier = $this->applyEagerLoadingOnModel($courier);
        }else {
            $query = $this->getQuery() ?? Courier::query();
            if($courierId) $query = $query->where('couriers.id', $courierId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $courier = $this->query->first();
        }

        return $this->showResourceExistence($courier);
    }

    /**
     * Update courier.
     *
     * @param Courier|string $courierId
     * @param array $data
     * @return Courier|array
     */
    public function updateCourier(Courier|string $courierId, array $data): Courier|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update courier'];

        $courier = Courier::find($courierId);

        if($courier) {

            $courier->update($data);
            return $this->showUpdatedResource($courier);

        }else{
            return ['updated' => false, 'message' => 'This courier does not exist'];
        }
    }

    /**
     * Delete courier.
     *
     * @param Courier|string $courierId
     * @return array
     */
    public function deleteCourier(Courier|string $courierId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete courier'];

        $courier = Courier::find($courierId);

        if($courier) {

            $deleted = $courier->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Courier deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Courier delete unsuccessful'];
            }

        }else{
            return ['deleted' => false, 'message' => 'This courier does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query courier by ID.
     *
     * @param string $courierId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryCourierById(string $courierId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('couriers.id', $courierId)->with($relationships);
    }

    /**
     * Get courier by ID.
     *
     * @param string $courierId
     * @param array $relationships
     * @return Courier|null
     */
    public function getCourierById(string $courierId, array $relationships = []): Courier|null
    {
        return $this->queryCourierById($courierId, $relationships)->first();
    }

    /**
     * Query couriers by IDs.
     *
     * @param array<string> $courierId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryCouriersByIds($courierIds): Builder|Relation
    {
        return $this->query->whereIn('couriers.id', $courierIds);
    }

    /**
     * Get couriers by IDs.
     *
     * @param array<string> $courierId
     * @param string $relationships
     * @return Collection
     */
    public function getCouriersByIds($courierIds): Collection
    {
        return $this->queryCouriersByIds($courierIds)->get();
    }
}
