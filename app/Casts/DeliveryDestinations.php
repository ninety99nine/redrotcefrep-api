<?php

namespace App\Casts;

use App\Traits\Base\BaseTrait;
use App\Services\Money\MoneyService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DeliveryDestinations implements CastsAttributes
{
    use BaseTrait;

    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array
     */
    public function get($model, $key, $value, $attributes)
    {
        if(is_null($value)) {

            return [];

        }else if(is_string($value)) {

            /**
             *  Json decode the data to convert json string to array
             *
             *  Reference: https://www.php.net/manual/en/function.json-decode.php
             */
            $value = json_decode($value, true);

        }

        foreach($value as $key => $deliveryDestination) {

            /**
             *  Convert the cost to money format
             */
            $value[$key]['cost'] = MoneyService::convertToMoneyFormat($deliveryDestination['cost'], $attributes['currency']);

        }

        return $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  array  $value
     * @param  array  $attributes
     * @return string
     */
    public function set($model, $key, $value, $attributes)
    {
        return json_encode($value);
    }
}
