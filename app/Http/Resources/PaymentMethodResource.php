<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class PaymentMethodResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $paymentMethod = $this->resource;

        if($paymentMethod->id) {
            $this->resourceLinks = [
                new ResourceLink('show.payment.method', route('show.payment.method', ['paymentMethodId' => $paymentMethod->id])),
                new ResourceLink('update.payment.method', route('update.payment.method', ['paymentMethodId' => $paymentMethod->id])),
                new ResourceLink('delete.payment.method', route('delete.payment.method', ['paymentMethodId' => $paymentMethod->id])),
            ];
        }
    }
}
