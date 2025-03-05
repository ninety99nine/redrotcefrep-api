<?php

namespace App\Repositories;

use App\Models\MediaFile;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use App\Enums\RequestFileName;
use App\Services\AWS\AWSService;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\MediaFileResources;
use App\Models\Module;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class MediaFileRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show media files.
     *
     * @return MediaFileResources|array
     */
    public function showMediaFiles(): MediaFileResources|array
    {
        if($this->getQuery() == null) {
            if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show media files'];
            $this->setQuery(MediaFile::query()->latest());
        }

        return $this->getOutput();
    }

    /**
     * Create media file.
     *
     * @param RequestFileName $requestFileName
     * @param Model $model
     * @return array
     */
    public function createMediaFile(RequestFileName $requestFileName, Model $model): array
    {
        $fileName = $requestFileName->value;

        if(request()->hasFile($fileName)) {

            $mediaFiles = [];
            $totalCreatedMediaFiles = 0;
            $files = request()->file($fileName);
            $files = is_array($files) ? $files : [$files];
            $maximumUploadLimit = $this->getMaximumUploadLimit($requestFileName, $model);

            foreach($files as $key => $file) {

                if($key < $maximumUploadLimit) {

                    $folderName = $this->getFolderName($requestFileName);

                    $filePath = AWSService::store($folderName, $file);
                    $mediaFilePayload = $this->prepareMediaFilePayload($requestFileName, $file, $filePath, $model);
                    $mediaFile = MediaFile::create($mediaFilePayload);
                    $totalCreatedMediaFiles += 1;
                    $mediaFiles[] = $mediaFile;

                }else{
                    break;
                }

            }

            return $this->showBulkCreatedResources($mediaFiles);
        }

        return ['created' => false, 'message' => 'No file provided'];
    }

    /**
     * Delete media files.
     *
     * @param array $mediaFileIds
     * @return array
     */
    public function deleteMediaFiles(array $mediaFileIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete media files'];

        $mediaFiles = $this->setQuery($this->getQuery() ?? MediaFile::query())->getMediaFilesByIds($mediaFileIds);

        if($totalMediaFiles = $mediaFiles->count()) {

            foreach($mediaFiles as $mediaFile) {
                $mediaFile->delete();
            }

            return ['deleted' => true, 'message' => $totalMediaFiles  .($totalMediaFiles  == 1 ? ' media file': ' media files') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No media files deleted'];
        }
    }

    /**
     * Show media file.
     *
     * @param MediaFile|string|null $mediaFileId
     * @return MediaFile|array|null
     */
    public function showMediaFile(MediaFile|string|null $mediaFileId = null): MediaFile|array|null
    {
        if(($mediaFile = $mediaFileId) instanceof MediaFile) {
            $mediaFile = $this->applyEagerLoadingOnModel($mediaFile);
        }else {
            $query = $this->getQuery() ?? MediaFile::query();
            if($mediaFileId) $query = $query->where('media_files.id', $mediaFileId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $mediaFile = $this->query->first();
        }

        return $this->showResourceExistence($mediaFile);
    }

    /**
     * Update media file.
     *
     * @param MediaFile|string $mediaFileId
     * @return MediaFile|array
     */
    public function updateMediaFile(MediaFile|string $mediaFileId): MediaFile|array
    {
        $mediaFile = $mediaFileId instanceof MediaFile ? $mediaFileId->loadMissing(['mediable']) : MediaFile::with(['mediable'])->find($mediaFileId);

        if($mediaFile) {

            if(!$this->isAuthourized()) {

                $mediable = $mediaFile->mediable;

                if(($store = $mediable) instanceof Store) {
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update media file'];
                }else if(($product = $mediable) instanceof Product) {
                    $store = $product->store;
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update media file'];
                }else if(($module = $mediable) instanceof Module) {
                    $store = $module->store;
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update media file'];
                }

            }

            $fileName = $mediaFile->type;

            if(request()->hasFile($fileName)) {

                if(AWSService::exists($mediaFile->file_path)) {
                    $oldFileDeleted = AWSService::delete($mediaFile->file_path);
                    if(!$oldFileDeleted) return ['updated' => false, 'message' => 'Could not delete the previously media file'];
                }

                $file = request()->file($fileName);
                $requestFileName = RequestFileName::tryFrom($fileName);
                $folderName = $this->getFolderName($requestFileName);
                $filePath = AWSService::store($folderName, $file);

                $mediaFilePayload = $this->prepareMediaFilePayload($requestFileName, $file, $filePath);
                $mediaFile->update($mediaFilePayload);

                if(!$this->checkIfHasRelationOnRequest('mediable')) $mediaFile->unsetRelation('mediable');

                return $this->showUpdatedResource($mediaFile);

            }else{
                return [
                    'updated' => false,
                    'message' => 'No file provided',
                ];
            }

        }else{
            return ['updated' => false, 'message' => 'This media file does not exist'];
        }
    }

    /**
     * Delete media file.
     *
     * @param string|MediaFile $mediaFileId
     * @return array
     */
    public function deleteMediaFile(string|MediaFile $mediaFileId): array
    {
        $mediaFile = $mediaFileId instanceof MediaFile ? $mediaFileId->loadMissing(['mediable']) : MediaFile::with(['mediable'])->find($mediaFileId);

        if($mediaFile) {

            if(!$this->isAuthourized()) {

                $mediable = $mediaFile->mediable;

                if(($store = $mediable) instanceof Store) {
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete media file'];
                }else if(($product = $mediable) instanceof Product) {
                    $store = $product->store;
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete media file'];
                }else if(($module = $mediable) instanceof Module) {
                    $store = $module->store;
                    $isAuthourized = $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete media file'];
                }

            }

            if(AWSService::exists($mediaFile->file_path)) {
                AWSService::delete($mediaFile->file_path);
            }

            $deleted = $mediaFile->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Media file deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Media file delete unsuccessful'];
            }

        }else{
            return ['deleted' => false, 'message' => 'This media file does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query media file by ID.
     *
     * @param string $mediaFileId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryMediaFileById(string $mediaFileId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('media_files.id', $mediaFileId)->with($relationships);
    }

    /**
     * Get media file by ID.
     *
     * @param string $mediaFileId
     * @param array $relationships
     * @return MediaFile|null
     */
    public function getMediaFileById(string $mediaFileId, array $relationships = []): MediaFile|null
    {
        return $this->queryMediaFileById($mediaFileId, $relationships)->first();
    }

    /**
     * Query media files by IDs.
     *
     * @param array<string> $mediaFileIds
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryMediaFilesByIds($mediaFileIds): Builder|Relation
    {
        return $this->query->whereIn('media_files.id', $mediaFileIds);
    }

    /**
     * Get media files by IDs.
     *
     * @param array<string> $mediaFileIds
     * @param string $relationships
     * @return Collection
     */
    public function getMediaFilesByIds($mediaFileIds): Collection
    {
        return $this->queryMediaFilesByIds($mediaFileIds)->get();
    }

    /**
     * Prepare media file payload.
     *
     * @param RequestFileName $requestFileName
     * @param UploadedFile $file
     * @param string $filePath
     * @param Model|null $model
     * @return array
     */
    private function prepareMediaFilePayload(RequestFileName $requestFileName, UploadedFile $file, string $filePath, Model|null $model = null): array
    {
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();
        $type = $requestFileName->value;
        $fileName = $file->getClientOriginalName();

        if (strpos($mimeType, 'image') !== false) {
            list($width, $height) = getimagesize($file);
        }else{
            $width = null;
            $height = null;
        }

        $data = [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'height' => $height,
            'width' => $width,
            'type' => $type,
        ];

        if($model) {
            $data = array_merge($data, [
                'mediable_type' => $model->getResourceName(),
                'mediable_id' => $model->id,
            ]);
        }

        return $data;
    }

    /**
     * Get maximum upload limit.
     *
     * @param RequestFileName $requestFileName
     * @return string
     */
    private function getMaximumUploadLimit(RequestFileName $requestFileName, Model $model): string
    {
        switch ($requestFileName) {
            case RequestFileName::STORE_ADVERT:
                $maxLimit = 5;
                break;
            case RequestFileName::PRODUCT_PHOTO:
                $maxLimit = 5;
                break;
        }

        if(isset($maxLimit)) {

            $totalMediaFilesUploaded = MediaFile::where([
                'mediable_type' => $model->getResourceName(),
                'type' => $requestFileName->value,
                'mediable_id' => $model->id,
            ])->count();

            return $maxLimit - $totalMediaFilesUploaded;

        }

        return 1;
    }

    /**
     * Get folder name.
     *
     * @param RequestFileName $requestFileName
     * @return string
     */
    private function getFolderName(RequestFileName $requestFileName): string
    {
        switch ($requestFileName) {
            case RequestFileName::STORE_LOGO:
                return 'store_logos';
                break;
            case RequestFileName::MODULE_FILE:
                return 'module_files';
                break;
            case RequestFileName::STORE_ADVERT:
                return 'store_adverts';
                break;
            case RequestFileName::PRODUCT_PHOTO:
                return 'product_photos';
                break;
            case RequestFileName::PROFILE_PHOTO:
                return 'profile_photos';
                break;
            case RequestFileName::STORE_COVER_PHOTO:
                return 'store_cover_photos';
                break;
            case RequestFileName::STORE_PAYMENT_METHOD_LOGO:
                return 'store_payment_method_logos';
                break;
            case RequestFileName::STORE_PAYMENT_METHOD_PHOTO:
                return 'store_payment_method_photos';
                break;
        }
    }
}
