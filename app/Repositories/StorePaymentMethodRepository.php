<?php

namespace App\Repositories;

use App\Models\Store;
use App\Traits\AuthTrait;
use App\Models\MediaFile;
use Illuminate\Support\Str;
use App\Models\PaymentMethod;
use App\Traits\Base\BaseTrait;
use App\Enums\RequestFileName;
use App\Enums\PaymentMethodType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Pivots\StorePaymentMethod;
use App\Http\Resources\MediaFileResource;
use App\Http\Resources\StorePaymentMethodResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class StorePaymentMethodRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show store payment methods.
     *
     * @return StorePaymentMethodResources|array
     */
    public function showStorePaymentMethods(array $data = []): StorePaymentMethodResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;

            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show store payment methods'];
                $this->setQuery(StorePaymentMethod::latest());
            }else{
                $this->setQuery(StorePaymentMethod::where('store_id', $storeId)->orderBy('position')->latest());
            }
        }

        return $this->getOutput();
    }

    /**
     * Create store payment method.
     *
     * @param array $data
     * @return StorePaymentMethod|array
     */
    public function createStorePaymentMethod(array $data): StorePaymentMethod|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create store payment method'];

            $storePaymentMethodId = $data['payment_method_id'];
            $paymentMethod = PaymentMethod::find($storePaymentMethodId);
            if(!$paymentMethod) return ['created' => false, 'message' => 'This payment method does not exist'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $pivotId = Str::uuid();

        $filteredConfigs = collect($data['configs'] ?? [])->reject(function ($value, $key) {
            return in_array($key, ['logo', 'photo']);
        })->toArray();

        $filteredConfigs = empty($filteredConfigs) ? null : $filteredConfigs;

        $pivotData = [
            'id' => $pivotId,
            'created_at' => now(),
            'updated_at' => now(),
            'configs' => $filteredConfigs,
            'active' => $data['active'] ?? false,
            'position' => $data['position'] ?? null,
            'custom_name' => $data['custom_name'] ?? null,
            'instruction' => $data['instruction'] ?? null,
        ];

        if ($paymentMethod->type === PaymentMethodType::OTHER->value) {

            $store->paymentMethods()->attach($paymentMethod->id, $pivotData);

        } else {

            // Retrieve existing pivot data
            $existingPivot = StorePaymentMethod::where('store_id', $store->id)->where('payment_method_id', $paymentMethod->id)->first();

            if ($existingPivot) {
                $pivotId = $existingPivot->id;
                $pivotData['id'] = $existingPivot->id;
                $pivotData['created_at'] = $existingPivot->created_at;
                $store->paymentMethods()->updateExistingPivot($paymentMethod->id, $pivotData);
            }else{
                $store->paymentMethods()->attach($paymentMethod->id, $pivotData);
            }

        }

        $storePaymentMethod = StorePaymentMethod::find($pivotId);
        return $this->showCreatedResource($storePaymentMethod);
    }

    /**
     * Delete store payment methods.
     *
     * @param array $data
     * @return array
     */
    public function deleteStorePaymentMethods(array $data): array
    {
        $storeId = $data['store_id'] ?? null;

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete store payment methods'];
            $this->setQuery(StorePaymentMethod::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete store payment methods'];
                $this->setQuery(StorePaymentMethod::where('store_id', $storeId));
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $storePaymentMethodIds = $data['store_payment_method_ids'];
        $storePaymentMethods = $this->getStorePaymentMethodsByIds($storePaymentMethodIds);

        if($totalStorePaymentMethods = $storePaymentMethods->count()) {

            foreach($storePaymentMethods as $storePaymentMethod) {
                $storePaymentMethod->delete();
            }

            return ['deleted' => true, 'message' => $totalStorePaymentMethods . ($totalStorePaymentMethods == 1 ? ' store payment method': ' store payment methods') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No store payment method deleted'];
        }
    }

    /**
     * Update store payment method arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateStorePaymentMethodArrangement(array $data): array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update product arrangement'];
            $this->setQuery(StorePaymentMethod::where('store_id', $storeId)->orderBy('position', 'asc'));
        }else{
            return ['message' => 'This store does not exist'];
        }

        $storePaymentMethodIds = $data['store_payment_method_ids'];

        $storePaymentMethods = $this->query->get();
        $originalStorePaymentMethodPositions = $storePaymentMethods->pluck('position', 'id');

        $arrangement = collect($storePaymentMethodIds)->filter(function ($StorePaymentMethodId) use ($originalStorePaymentMethodPositions) {
            return collect($originalStorePaymentMethodPositions)->keys()->contains($StorePaymentMethodId);
        })->toArray();

        $movedStorePaymentMethodPositions = collect($arrangement)->mapWithKeys(function ($StorePaymentMethodId, $newPosition) use ($originalStorePaymentMethodPositions) {
            return [$StorePaymentMethodId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalStorePaymentMethodPositions = $originalStorePaymentMethodPositions->except(collect($movedStorePaymentMethodPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedStorePaymentMethodPositions) {
            return [$id => count($movedStorePaymentMethodPositions) + $index + 1];
        })->toArray();

        $storePaymentMethodPositions = $movedStorePaymentMethodPositions + $adjustedOriginalStorePaymentMethodPositions;

        if(count($storePaymentMethodPositions)) {

            DB::table('store_payment_method')
                ->whereIn('id', array_keys($storePaymentMethodPositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($storePaymentMethodPositions), $storePaymentMethodPositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Store payment method arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching store payment methods to update'];
    }

    /**
     * Show store payment method.
     *
     * @param StorePaymentMethod|string|null $storePaymentMethodId
     * @return StorePaymentMethod|array|null
     */
    public function showStorePaymentMethod(StorePaymentMethod|string|null $storePaymentMethodId = null): StorePaymentMethod|array|null
    {
        if(($storePaymentMethod = $storePaymentMethodId) instanceof StorePaymentMethod) {
            $storePaymentMethod = $this->applyEagerLoadingOnModel($storePaymentMethod);
        }else {
            $query = $this->getQuery() ?? StorePaymentMethod::query();
            if($storePaymentMethodId) $query = $query->where('store_payment_method.id', $storePaymentMethodId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $storePaymentMethod = $this->query->first();
        }

        return $this->showResourceExistence($storePaymentMethod);
    }

    /**
     * Update store payment method.
     *
     * @param StorePaymentMethod|string $storePaymentMethodId
     * @param array $data
     * @return StorePaymentMethod|array
     */
    public function updateStorePaymentMethod(StorePaymentMethod|string $storePaymentMethodId, array $data): StorePaymentMethod|array
    {
        $storePaymentMethod = StorePaymentMethod::with(['store'])->find($storePaymentMethodId);

        if($storePaymentMethod) {
            $store = $storePaymentMethod->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update store payment method'];
                if(!$this->checkIfHasRelationOnRequest('store')) $storePaymentMethod->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $filteredConfigs = collect($data['configs'] ?? [])->reject(function ($value, $key) {
                return in_array($key, ['logo', 'photo']);
            })->toArray();

            $filteredConfigs = empty($filteredConfigs) ? null : $filteredConfigs;

            $pivotData = [
                'updated_at' => now(),
                'configs' => $filteredConfigs,
                'active' => isset($data['active']) ? $data['active'] : $storePaymentMethod->active,
                'custom_name' => isset($data['custom_name']) ? $data['custom_name'] : $storePaymentMethod->custom_name,
                'instruction' => isset($data['instruction']) ? $data['instruction'] : $storePaymentMethod->instruction
            ];

            $storePaymentMethod->update($pivotData);
            return $this->showUpdatedResource($storePaymentMethod);

        }else{
            return ['updated' => false, 'message' => 'This store payment method does not exist'];
        }
    }

    /**
     * Delete store payment method.
     *
     * @param StorePaymentMethod|string $storePaymentMethodId
     * @return array
     */
    public function deleteStorePaymentMethod(StorePaymentMethod|string $storePaymentMethodId): array
    {
        $storePaymentMethod = StorePaymentMethod::with(['store'])->find($storePaymentMethodId);

        if($storePaymentMethod) {
            $store = $storePaymentMethod->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete store payment method'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $storePaymentMethod->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Store payment method deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Store payment method delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This Store payment method does not exist'];
        }
    }

    /**
     * Show store payment method logo.
     *
     * @param string $storePaymentMethodId
     * @return MediaFileResource|array|null
     */
    public function showStorePaymentMethodLogo(string $storePaymentMethodId): MediaFileResource|array|null
    {
        $storePaymentMethod = StorePaymentMethod::with(['store', 'logo'])->find($storePaymentMethodId);

        if($storePaymentMethod) {
            $store = $storePaymentMethod->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show store payment method logo'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($store->logo())->showMediaFile();
        }else{
            return ['message' => 'This store payment method does not exist'];
        }
    }

    /**
     * Uplaod store payment method logo.
     *
     * @param string $storePaymentMethodId
     * @return array
     */
    public function uploadStorePaymentMethodLogo(string $storePaymentMethodId): array
    {
        $storePaymentMethod = StorePaymentMethod::with(['store', 'logo'])->find($storePaymentMethodId);

        if($storePaymentMethod) {

            $store = $storePaymentMethod->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['uploaded' => false, 'message' => 'You do not have permission to create store payment method logo'];
            }else{
                return ['uploaded' => false, 'message' => 'This store does not exist'];
            }

            if($storePaymentMethod->logo) {

                $mediaFile = $this->getMediaFileRepository()->authourize()->shouldReturnModel()->updateMediaFile($storePaymentMethod->logo);

                if($mediaFile instanceof MediaFile) {
                    return $this->getMediaFileRepository()->showSavedResource($mediaFile, 'uploaded', 'Store payment method logo uploaded');
                }else{
                    return ['uploaded' => false, 'message' => 'Store payment method logo upload failed'];
                }

            }else{

                $mediaFiles = $this->getMediaFileRepository()->authourize()->shouldReturnModel()->createMediaFile(RequestFileName::STORE_PAYMENT_METHOD_LOGO, $storePaymentMethod);

                if(is_array($mediaFiles) && !empty($mediaFiles)) {
                    return $this->getMediaFileRepository()->showSavedResource($mediaFiles[0], 'uploaded', 'Store payment method logo uploaded');
                }else{
                    return ['uploaded' => false, 'message' => 'Store payment method logo upload failed'];
                }

            }

        }else{
            return ['uploaded' => false, 'message' => 'This store payment method does not exist'];
        }
    }

    /**
     * Delete store payment method logo.
     *
     * @param string $storePaymentMethodId
     * @return array
     */
    public function deleteStorePaymentMethodLogo(string $storePaymentMethodId): array
    {
        $storePaymentMethod = StorePaymentMethod::with(['store', 'logo'])->find($storePaymentMethodId);

        if($storePaymentMethod) {

            $store = $storePaymentMethod->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete store payment method logo'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            if($storePaymentMethod->logo) {
                $this->getMediaFileRepository()->authourize()->deleteMediaFile($storePaymentMethod->logo);
            }

            return ['deleted' => false, 'message' => 'This store payment method deleted'];

        }else{
            return ['deleted' => false, 'message' => 'This store payment method does not exist'];
        }
    }

    /**
     * Show store payment method photo.
     *
     * @param string $storePaymentMethodId
     * @return MediaFileResource|array|null
     */
    public function showStorePaymentMethodPhoto(string $storePaymentMethodId): MediaFileResource|array|null
    {
        $storePaymentMethod = StorePaymentMethod::with(['store', 'photo'])->find($storePaymentMethodId);

        if($storePaymentMethod) {
            $store = $storePaymentMethod->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show store payment method photo'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($store->photo())->showMediaFile();
        }else{
            return ['message' => 'This store payment method does not exist'];
        }
    }


    /**
     * Uplaod store payment method photo.
     *
     * @param string $storePaymentMethodId
     * @return array
     */
    public function uploadStorePaymentMethodPhoto(string $storePaymentMethodId): array
    {
        $storePaymentMethod = StorePaymentMethod::with(['store', 'photo'])->find($storePaymentMethodId);

        if($storePaymentMethod) {

            $store = $storePaymentMethod->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['uploaded' => false, 'message' => 'You do not have permission to create store payment method photo'];
            }else{
                return ['uploaded' => false, 'message' => 'This store does not exist'];
            }

            if($storePaymentMethod->photo) {

                $mediaFile = $this->getMediaFileRepository()->authourize()->shouldReturnModel()->updateMediaFile($storePaymentMethod->photo);

                if($mediaFile instanceof MediaFile) {
                    return $this->getMediaFileRepository()->showSavedResource($mediaFile, 'uploaded', 'Store payment method photo uploaded');
                }else{
                    return ['uploaded' => false, 'message' => 'Store payment method photo upload failed'];
                }

            }else{

                $mediaFiles = $this->getMediaFileRepository()->authourize()->shouldReturnModel()->createMediaFile(RequestFileName::STORE_PAYMENT_METHOD_PHOTO, $storePaymentMethod);

                if(is_array($mediaFiles) && !empty($mediaFiles)) {
                    return $this->getMediaFileRepository()->showSavedResource($mediaFiles[0], 'uploaded', 'Store payment method photo uploaded');
                }else{
                    return ['uploaded' => false, 'message' => 'Store payment method photo upload failed'];
                }

            }

        }else{
            return ['uploaded' => false, 'message' => 'This store payment method does not exist'];
        }
    }

    /**
     * Delete store payment method photo.
     *
     * @param string $storePaymentMethodId
     * @return array
     */
    public function deleteStorePaymentMethodPhoto(string $storePaymentMethodId): array
    {
        $storePaymentMethod = StorePaymentMethod::with(['store', 'photo'])->find($storePaymentMethodId);

        if($storePaymentMethod) {

            $store = $storePaymentMethod->store;

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete store payment method photo'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            if($storePaymentMethod->photo) {
                $this->getMediaFileRepository()->authourize()->deleteMediaFile($storePaymentMethod->photo);
            }

            return ['deleted' => false, 'message' => 'This store payment method deleted'];

        }else{
            return ['deleted' => false, 'message' => 'This store payment method does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query store payment method by ID.
     *
     * @param string $storePaymentMethodId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryStorePaymentMethodById(string $storePaymentMethodId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('store_payment_method.id', $storePaymentMethodId)->with($relationships);
    }

    /**
     * Get store payment method by ID.
     *
     * @param string $storePaymentMethodId
     * @param array $relationships
     * @return StorePaymentMethod|null
     */
    public function getStorePaymentMethodById(string $storePaymentMethodId, array $relationships = []): StorePaymentMethod|null
    {
        return $this->queryStorePaymentMethodById($storePaymentMethodId, $relationships)->first();
    }

    /**
     * Query store payment methods by IDs.
     *
     * @param array<string> $storePaymentMethodId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryStorePaymentMethodsByIds($storePaymentMethodIds): Builder|Relation
    {
        return $this->query->whereIn('store_payment_method.id', $storePaymentMethodIds);
    }

    /**
     * Get store payment methods by IDs.
     *
     * @param array<string> $storePaymentMethodId
     * @param string $relationships
     * @return Collection
     */
    public function getStorePaymentMethodsByIds($storePaymentMethodIds): Collection
    {
        return $this->queryStorePaymentMethodsByIds($storePaymentMethodIds)->get();
    }
}
