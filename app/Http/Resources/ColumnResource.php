<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class ColumnResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $column = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.column', route('show.column', ['columnId' => $column->id])),
            new ResourceLink('update.column', route('update.column', ['columnId' => $column->id])),
            new ResourceLink('delete.column', route('delete.column', ['columnId' => $column->id]))
        ];
    }
}
