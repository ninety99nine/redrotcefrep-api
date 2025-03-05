<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class PromotionResource extends BaseResource
{
    /**
     *  Check if this promotion is being requested by a team member
     *  who has the permissions to manage orders
     *
     *  Note that an promotion is retrieved from a store, in
     *  which case the "user_store_association" will exist
     *
     *  @return bool
     */
    private function canManagePromotions() {
        return request()->store->user_store_association->can_manage_promotions;
    }

    /**
     *  Check if this order is being requested by a user that is allowed
     *  to see more sensitive information regarding this order.
     *
     *  @return bool
     */
    private function viewingPrivately() {

        $isSuperAdmin = $this->isSuperAdmin;
        $canManagePromotions = $this->canManagePromotions();

        return $isSuperAdmin || $canManagePromotions;
    }

    /**
     *  Check if this order is being requested by a user that is not allowed
     *  to see more sensitive information regarding this order.
     *
     *  @return bool
     */
    private function viewingPublicly() {
        return $this->viewingPrivately() == false;
    }

    public function toArray($request)
    {
        /**
         *  Viewing as Public User
         *
         *  If we are veiwing as the general public then limit the information we share.
         *  Usually we just want the basic promotion details, nothing that would expose
         *  sensitive promotion information such as promotion codes. Only the store Team
         *  Members can see those details.
         */
        //  if( $this->viewingPublicly() ) {

            //  Overide and apply custom fields
            //  $this->customExcludeFields = ['code', 'store_id', 'user_id'];

        //}

        return $this->transformedStructure();

    }

    public function setLinks()
    {
        $promotion = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.promotion', route('show.promotion', ['promotionId' => $promotion->id])),
            new ResourceLink('update.promotion', route('update.promotion', ['promotionId' => $promotion->id])),
            new ResourceLink('delete.promotion', route('delete.promotion', ['promotionId' => $promotion->id])),
        ];
    }
}
