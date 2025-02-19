<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class StorePaymentMethodResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $storePaymentMethod = $this->resource;

        if($storePaymentMethod->id) {
            $this->resourceLinks = [
                new ResourceLink('show.store.payment.method', route('show.store.payment.method', ['storePaymentMethodId' => $storePaymentMethod->id])),
                new ResourceLink('update.store.payment.method', route('update.store.payment.method', ['storePaymentMethodId' => $storePaymentMethod->id])),
                new ResourceLink('delete.store.payment.method', route('delete.store.payment.method', ['storePaymentMethodId' => $storePaymentMethod->id])),

                new ResourceLink('show.store.payment.method.logo', route('show.store.payment.method.logo', ['storePaymentMethodId' => $storePaymentMethod->id])),
                new ResourceLink('upload.store.payment.method.logo', route('upload.store.payment.method.logo', ['storePaymentMethodId' => $storePaymentMethod->id])),
                new ResourceLink('delete.store.payment.method.logo', route('delete.store.payment.method.logo', ['storePaymentMethodId' => $storePaymentMethod->id])),

                new ResourceLink('show.store.payment.method.photo', route('show.store.payment.method.photo', ['storePaymentMethodId' => $storePaymentMethod->id])),
                new ResourceLink('upload.store.payment.method.photo', route('upload.store.payment.method.photo', ['storePaymentMethodId' => $storePaymentMethod->id])),
                new ResourceLink('delete.store.payment.method.photo', route('delete.store.payment.method.photo', ['storePaymentMethodId' => $storePaymentMethod->id])),
            ];
        }
    }
}
