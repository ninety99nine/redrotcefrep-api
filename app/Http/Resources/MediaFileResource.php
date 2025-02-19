<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class MediaFileResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $mediaFile = $this->resource;

        if($mediaFile->id) {
            $this->resourceLinks = [
                new ResourceLink('show.media.file', route('show.media.file', ['mediaFileId' => $mediaFile->id])),
                new ResourceLink('update.media.file', route('update.media.file', ['mediaFileId' => $mediaFile->id])),
                new ResourceLink('delete.media.file', route('delete.media.file', ['mediaFileId' => $mediaFile->id])),
            ];
        }
    }
}
