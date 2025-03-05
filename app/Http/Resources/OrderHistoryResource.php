<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class OrderHistoryResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $orderHistory = $this->resource;

        $this->resourceLinks = [];
    }
}
