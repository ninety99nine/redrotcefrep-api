<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class StoreResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $store = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.store', route('show.store', ['storeId' => $store->id])),
            new ResourceLink('update.store', route('update.store', ['storeId' => $store->id])),
            new ResourceLink('delete.store', route('delete.store', ['storeId' => $store->id])),
            new ResourceLink('show.store.logo', route('show.store.logo', ['storeId' => $store->id])),
            new ResourceLink('upload.store.logo', route('upload.store.logo', ['storeId' => $store->id])),
            new ResourceLink('show.store.cover.photo', route('show.store.cover.photo', ['storeId' => $store->id])),
            new ResourceLink('upload.store.cover.photo', route('upload.store.cover.photo', ['storeId' => $store->id])),
            new ResourceLink('show.store.adverts', route('show.store.adverts', ['storeId' => $store->id])),
            new ResourceLink('upload.store.advert', route('upload.store.advert', ['storeId' => $store->id])),
            new ResourceLink('show.store.quick.start.guide', route('show.store.quick.start.guide', ['storeId' => $store->id])),
            new ResourceLink('show.store.qr.code.image.preview', route('show.store.qr.code.image.preview', ['storeId' => $store->id])),
            new ResourceLink('show.store.insights', route('show.store.insights', ['storeId' => $store->id])),

            new ResourceLink('show.store.followers', route('show.store.followers', ['storeId' => $store->id])),
            new ResourceLink('invite.store.followers', route('invite.store.followers', ['storeId' => $store->id])),
            new ResourceLink('show.store.following', route('show.store.following', ['storeId' => $store->id])),
            new ResourceLink('update.store.following', route('update.store.following', ['storeId' => $store->id])),
            new ResourceLink('accept.invitation.to.follow.store', route('accept.invitation.to.follow.store', ['storeId' => $store->id])),
            new ResourceLink('decline.invitation.to.follow.store', route('decline.invitation.to.follow.store', ['storeId' => $store->id])),

            new ResourceLink('show.store.team.members', route('show.store.team.members', ['storeId' => $store->id])),
            new ResourceLink('invite.store.team.members', route('invite.store.team.members', ['storeId' => $store->id])),
            new ResourceLink('remove.store.team.members', route('remove.store.team.members', ['storeId' => $store->id])),
            new ResourceLink('show.my.store.permissions', route('show.my.store.permissions', ['storeId' => $store->id])),
            new ResourceLink('show.team.member.permission.options', route('show.team.member.permission.options', ['storeId' => $store->id])),
            new ResourceLink('accept.invitation.to.join.store.team', route('accept.invitation.to.join.store.team', ['storeId' => $store->id])),
            new ResourceLink('decline.invitation.to.join.store.team', route('decline.invitation.to.join.store.team', ['storeId' => $store->id])),

            new ResourceLink('show.store.orders', route('show.store.orders', ['storeId' => $store->id])),
            new ResourceLink('show.store.coupons', route('show.store.coupons', ['storeId' => $store->id])),
            new ResourceLink('show.store.reviews', route('show.store.reviews', ['storeId' => $store->id])),
            new ResourceLink('show.store.products', route('show.store.products', ['storeId' => $store->id])),
            new ResourceLink('show.store.shopping.products', route('show.store.shopping.products', ['storeId' => $store->id])),
            new ResourceLink('show.store.customers', route('show.store.customers', ['storeId' => $store->id])),
            new ResourceLink('show.store.workflows', route('show.store.workflows', ['storeId' => $store->id])),
            new ResourceLink('show.store.transactions', route('show.store.transactions', ['storeId' => $store->id])),
            new ResourceLink('show.store.subscriptions', route('show.store.subscriptions', ['storeId' => $store->id])),
            new ResourceLink('show.store.payment.methods', route('show.store.payment.methods', ['storeId' => $store->id])),
            new ResourceLink('show.store.delivery.methods', route('show.store.delivery.methods', ['storeId' => $store->id])),
            new ResourceLink('show.store.shopping.delivery.methods', route('show.store.shopping.delivery.methods', ['storeId' => $store->id])),

            new ResourceLink('show.store.pages', route('show.store.pages', ['storeId' => $store->id])),
        ];
    }
}
