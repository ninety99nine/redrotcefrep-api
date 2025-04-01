<?php

namespace App\Casts;

use App\Traits\Base\BaseTrait;
use App\Services\Money\MoneyService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class CheckoutFees implements CastsAttributes
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
            $value = json_decode($value, true);
        }

        foreach($value as $key => $checkoutFee) {
            $value[$key]['flat_rate'] = MoneyService::convertToMoneyFormat($checkoutFee['flat_rate'], $attributes['currency']);
            $value[$key]['percentage_rate'] = $this->convertToPercentageFormat($checkoutFee['percentage_rate']);
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
