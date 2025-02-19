<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class RowResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $row = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.row', route('show.row', ['rowId' => $row->id])),
            new ResourceLink('update.row', route('update.row', ['rowId' => $row->id])),
            new ResourceLink('delete.row', route('delete.row', ['rowId' => $row->id])),

            new ResourceLink('show.row.columns', route('show.row.columns', ['rowId' => $row->id])),

        ];
    }
}
