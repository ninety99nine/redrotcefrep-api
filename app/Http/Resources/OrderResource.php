<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class OrderResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $order = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.order', route('show.order', ['orderId' => $order->id])),
            new ResourceLink('update.order', route('update.order', ['orderId' => $order->id])),
            new ResourceLink('delete.order', route('delete.order', ['orderId' => $order->id])),
            new ResourceLink('show.order.cancellation.reasons', route('show.order.cancellation.reasons', ['orderId' => $order->id])),
            new ResourceLink('generate.order.collection.code', route('generate.order.collection.code', ['orderId' => $order->id])),
            new ResourceLink('revoke.order.collection.code', route('revoke.order.collection.code', ['orderId' => $order->id])),
            new ResourceLink('verify.order.collection', route('verify.order.collection', ['orderId' => $order->id])),
            new ResourceLink('update.order.status', route('update.order.status', ['orderId' => $order->id])),
            new ResourceLink('request.order.payment', route('request.order.payment', ['orderId' => $order->id])),
            new ResourceLink('show.payment.methods.for.requesting.order.payment', route('show.payment.methods.for.requesting.order.payment', ['orderId' => $order->id])),
            new ResourceLink('mark.order.as.paid', route('mark.order.as.paid', ['orderId' => $order->id])),
            new ResourceLink('mark.order.as.unpaid', route('mark.order.as.unpaid', ['orderId' => $order->id])),
            new ResourceLink('show.payment.methods.for.marking.as.paid', route('show.payment.methods.for.marking.as.paid', ['orderId' => $order->id])),
            new ResourceLink('show.order.store', route('show.order.store', ['orderId' => $order->id])),
            new ResourceLink('show.order.customer', route('show.order.customer', ['orderId' => $order->id])),
            new ResourceLink('show.order.occasion', route('show.order.occasion', ['orderId' => $order->id])),
            new ResourceLink('show.order.placed.by.user', route('show.order.placed.by.user', ['orderId' => $order->id])),
            new ResourceLink('show.order.created.by.user', route('show.order.created.by.user', ['orderId' => $order->id])),
            new ResourceLink('show.order.collection.verified.by.user', route('show.order.collection.verified.by.user', ['orderId' => $order->id])),
            new ResourceLink('show.order.delivery.address', route('show.order.delivery.address', ['orderId' => $order->id])),
            new ResourceLink('show.order.friend.group', route('show.order.friend.group', ['orderId' => $order->id])),
            new ResourceLink('add.order.friend.group', route('add.order.friend.group', ['orderId' => $order->id])),
            new ResourceLink('remove.order.friend.group', route('remove.order.friend.group', ['orderId' => $order->id])),
            new ResourceLink('show.order.viewers', route('show.order.viewers', ['orderId' => $order->id])),
            new ResourceLink('show.order.transactions', route('show.order.transactions', ['orderId' => $order->id])),
        ];
    }
}
