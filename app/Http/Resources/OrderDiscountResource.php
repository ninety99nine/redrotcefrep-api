<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;

class OrderDiscountResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }
}
