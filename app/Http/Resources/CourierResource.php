<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class CourierResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $courier = $this->resource;

        if($courier->id) {
            $this->resourceLinks = [
                new ResourceLink('show.courier', route('show.courier', ['courierId' => $courier->id])),
                new ResourceLink('update.courier', route('update.courier', ['courierId' => $courier->id])),
                new ResourceLink('delete.courier', route('delete.courier', ['courierId' => $courier->id])),
            ];
        }
    }
}
