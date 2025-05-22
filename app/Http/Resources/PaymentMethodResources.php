<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResources;

class PaymentMethodResources extends BaseResources
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = 'App\Http\Resources\PaymentMethodResource';

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /**
         *  Return the resource together with the pagination links
         *  and the nested data assets
         */
        return $this->resource;
    }
}
