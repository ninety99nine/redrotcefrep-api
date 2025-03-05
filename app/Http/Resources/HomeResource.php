<?php

namespace App\Http\Resources;

use App\Traits\Base\BaseTrait;
use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class HomeResource extends BaseResource
{
    use BaseTrait;

    public function setLinks()
    {
        $this->resourceLinks = [
            new ResourceLink('login', route('login')),
            new ResourceLink('register', route('register')),
            new ResourceLink('account.exists', route('account.exists')),
            new ResourceLink('reset.password', route('reset.password')),
            new ResourceLink('validate.register', route('validate.register')),
            new ResourceLink('validate.reset.password', route('validate.reset.password')),
            new ResourceLink('show.terms.and.conditions', route('show.terms.and.conditions')),
            new ResourceLink('show.terms.and.conditions.takeaways', route('show.terms.and.conditions.takeaways')),
            new ResourceLink('verify.mobile.verification.code', route('verify.mobile.verification.code')),
            new ResourceLink('generate.mobile.verification.code', route('generate.mobile.verification.code')),
            new ResourceLink('show.auth.user', route('show.auth.user')),
            new ResourceLink('show.social.login.links', route('show.social.login.links')),

            new ResourceLink('show.users', route('show.users')),

            new ResourceLink('show.stores', route('show.stores')),
            new ResourceLink('create.store', route('create.store')),
            new ResourceLink('search.store.by.alias', route('search.store.by.alias')),
            new ResourceLink('search.store.by.ussd.mobile.number', route('search.store.by.ussd.mobile.number')),
            new ResourceLink('show.last.visited.store', route('show.last.visited.store')),
            new ResourceLink('show.store.deposit.options', route('show.store.deposit.options')),
            new ResourceLink('show.store.installment.options', route('show.store.installment.options')),

            new ResourceLink('show.friends', route('show.friends')),
            new ResourceLink('show.reviews', route('show.reviews')),

            new ResourceLink('show.orders', route('show.orders')),
            new ResourceLink('create.order', route('create.order')),
            new ResourceLink('update.orders', route('update.orders')),
            new ResourceLink('delete.orders', route('delete.orders')),
            new ResourceLink('download.orders', route('download.orders')),
            new ResourceLink('show.order.status.counts', route('show.order.status.counts')),

            new ResourceLink('show.products', route('show.products')),
            new ResourceLink('create.product', route('create.product')),
            new ResourceLink('delete.products', route('delete.products')),
            new ResourceLink('update.product.visibility', route('update.product.visibility')),
            new ResourceLink('update.product.arrangement', route('update.product.arrangement')),

            new ResourceLink('show.pages', route('show.pages')),
            new ResourceLink('create.page', route('create.page')),
            new ResourceLink('delete.pages', route('delete.pages')),
            new ResourceLink('update.page.visibility', route('update.page.visibility')),
            new ResourceLink('update.page.arrangement', route('update.page.arrangement')),

            new ResourceLink('show.sections', route('show.sections')),
            new ResourceLink('create.section', route('create.section')),
            new ResourceLink('delete.sections', route('delete.sections')),
            new ResourceLink('update.section.visibility', route('update.section.visibility')),
            new ResourceLink('update.section.arrangement', route('update.section.arrangement')),

            new ResourceLink('show.rows', route('show.rows')),
            new ResourceLink('create.row', route('create.row')),
            new ResourceLink('delete.rows', route('delete.rows')),
            new ResourceLink('update.row.visibility', route('update.row.visibility')),
            new ResourceLink('update.row.arrangement', route('update.row.arrangement')),

            new ResourceLink('show.modules', route('show.modules')),
            new ResourceLink('create.module', route('create.module')),
            new ResourceLink('delete.modules', route('delete.modules')),
            new ResourceLink('update.module.visibility', route('update.module.visibility')),
            new ResourceLink('update.module.arrangement', route('update.module.arrangement')),

            new ResourceLink('show.promotions', route('show.promotions')),
            new ResourceLink('create.promotion', route('create.promotion')),
            new ResourceLink('delete.promotions', route('delete.promotions')),

            new ResourceLink('show.customers', route('show.customers')),
            new ResourceLink('create.customer', route('create.customer')),
            new ResourceLink('delete.customers', route('delete.customers')),

            new ResourceLink('show.store.rolling.numbers', route('show.store.rolling.numbers')),
            new ResourceLink('create.store.rolling.number', route('create.store.rolling.number')),
            new ResourceLink('delete.store.rolling.numbers', route('delete.store.rolling.numbers')),

            new ResourceLink('show.addresses', route('show.addresses')),
            new ResourceLink('create.address', route('create.address')),
            new ResourceLink('delete.addresses', route('delete.addresses')),
            new ResourceLink('validate.add.address', route('validate.add.address')),

            new ResourceLink('show.occasions', route('show.occasions')),

            new ResourceLink('create.review', route('create.review')),




            new ResourceLink('show.ai.lessons', route('show.ai.lessons')),

            new ResourceLink('show.ai.messages', route('show.ai.messages')),
            new ResourceLink('create.ai.message', route('create.ai.message')),
            new ResourceLink('delete.ai.messages', route('delete.ai.messages')),

            new ResourceLink('show.transactions', route('show.transactions')),
            new ResourceLink('show.ai.assistants', route('show.ai.assistants')),
            new ResourceLink('show.friend.groups', route('show.friend.groups')),
            new ResourceLink('show.pricing.plans', route('show.pricing.plans')),
            new ResourceLink('show.notifications', route('show.notifications')),
            new ResourceLink('show.subscriptions', route('show.subscriptions')),

            new ResourceLink('show.couriers', route('show.couriers')),
            new ResourceLink('create.courier', route('create.courier')),
            new ResourceLink('delete.couriers', route('delete.couriers')),
            new ResourceLink('update.courier.arrangement', route('update.courier.arrangement')),

            new ResourceLink('show.payment.methods', route('show.payment.methods')),
            new ResourceLink('create.payment.method', route('create.payment.method')),
            new ResourceLink('delete.payment.methods', route('delete.payment.methods')),
            new ResourceLink('update.payment.method.arrangement', route('update.payment.method.arrangement')),

            new ResourceLink('show.store.payment.methods', route('show.store.payment.methods')),
            new ResourceLink('create.store.payment.method', route('create.store.payment.method')),
            new ResourceLink('delete.store.payment.methods', route('delete.store.payment.methods')),
            new ResourceLink('update.store.payment.method.arrangement', route('update.store.payment.method.arrangement')),

            new ResourceLink('show.delivery.methods', route('show.delivery.methods')),
            new ResourceLink('create.delivery.method', route('create.delivery.method')),
            new ResourceLink('delete.delivery.methods', route('delete.delivery.methods')),
            new ResourceLink('update.delivery.method.arrangement', route('update.delivery.method.arrangement')),
            new ResourceLink('show.delivery.method.schedule.options', route('show.delivery.method.schedule.options')),

            new ResourceLink('show.workflows', route('show.workflows')),
            new ResourceLink('create.workflow', route('create.workflow')),
            new ResourceLink('delete.workflows', route('delete.workflows')),
            new ResourceLink('show.workflow.options', route('show.workflow.options')),
            new ResourceLink('update.workflow.arrangement', route('update.workflow.arrangement')),

            new ResourceLink('show.workflow.steps', route('show.workflow.steps')),
            new ResourceLink('create.workflow.step', route('create.workflow.step')),
            new ResourceLink('delete.workflow.steps', route('delete.workflow.steps')),
            new ResourceLink('update.workflow.step.arrangement', route('update.workflow.step.arrangement')),

            new ResourceLink('show.review.rating.options', route('show.review.rating.options')),
            new ResourceLink('show.ai.message.categories', route('show.ai.message.categories')),

            new ResourceLink('show.filters', route('show.filters')),
            new ResourceLink('show.sorting', route('show.sorting')),
            new ResourceLink('show.languages', route('show.languages')),
            new ResourceLink('show.countries', route('show.countries')),
            new ResourceLink('show.currencies', route('show.currencies')),
            new ResourceLink('show.social.media.icons', route('show.social.media.icons')),
            new ResourceLink('show.country.address.options', route('show.country.address.options')),

            new ResourceLink('inspect.shopping.cart', route('inspect.shopping.cart')),
        ];
    }
}
