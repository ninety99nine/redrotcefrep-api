<?php

namespace App\Casts;

use App\Traits\Base\BaseTrait;
use App\Services\Money\MoneyService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Money implements CastsAttributes
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
        return MoneyService::convertToMoneyFormat($value, $attributes['currency']);
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
        if( is_array($value) ){

            //  If we have the array amount value
            if( isset($value['amount']) ) {

                $value = $value['amount'];

            }

        }else if( is_object($value) ){

            //  If we have the array amount value
            if( isset($value->amount) ) {

                $value = $value->amount;

            }

        }

        // Round to 2 decimal places
        return round($value, 2);
    }
}
