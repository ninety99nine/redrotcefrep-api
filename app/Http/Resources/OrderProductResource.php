<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class OrderProductResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $orderProduct = $this->resource;
        $productId = $this->resource->product_id;

        $this->resourceLinks = [
            new ResourceLink('show.order.product', route('show.order.product', ['orderProductId' => $orderProduct->id]))
        ];

        if($productId) {
            array_push($this->resourceLinks,
                new ResourceLink('show.product', route('show.product', ['productId' => $productId]))
            );
        }
    }
}
