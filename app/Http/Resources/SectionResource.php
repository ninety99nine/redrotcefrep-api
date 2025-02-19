<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class SectionResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $section = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.section', route('show.section', ['sectionId' => $section->id])),
            new ResourceLink('update.section', route('update.section', ['sectionId' => $section->id])),
            new ResourceLink('delete.section', route('delete.section', ['sectionId' => $section->id])),

            new ResourceLink('show.section.rows', route('show.section.rows', ['sectionId' => $section->id])),
        ];
    }
}
