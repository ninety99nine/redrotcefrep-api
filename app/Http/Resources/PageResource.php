<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class PageResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $page = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.page', route('show.page', ['pageId' => $page->id])),
            new ResourceLink('update.page', route('update.page', ['pageId' => $page->id])),
            new ResourceLink('delete.page', route('delete.page', ['pageId' => $page->id])),

            new ResourceLink('show.page.sections', route('show.page.sections', ['pageId' => $page->id])),
        ];
    }
}
