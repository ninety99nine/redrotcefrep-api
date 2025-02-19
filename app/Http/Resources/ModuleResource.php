<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class ModuleResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $module = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.module', route('show.module', ['moduleId' => $module->id])),
            new ResourceLink('update.module', route('update.module', ['moduleId' => $module->id])),
            new ResourceLink('delete.module', route('delete.module', ['moduleId' => $module->id])),
            new ResourceLink('show.module.media.files', route('show.module.media.files', ['moduleId' => $module->id])),
            new ResourceLink('create.module.media.file', route('create.module.media.file', ['moduleId' => $module->id])),
        ];
    }
}
