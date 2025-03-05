<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class OrderPromotionResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $orderPromotion = $this->resource;
        $promotionId = $this->resource->promotion_id;

        $this->resourceLinks = [
            new ResourceLink('show.order.promotion', route('show.order.promotion', ['orderPromotionId' => $orderPromotion->id]))
        ];

        if($promotionId) {
            array_push($this->resourceLinks,
                new ResourceLink('show.promotion', route('show.promotion', ['promotionId' => $promotionId]))
            );
        }
    }
}
