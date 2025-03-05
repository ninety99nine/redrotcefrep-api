<?php

namespace App\Casts;

use App\Traits\Base\BaseTrait;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Status implements CastsAttributes
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
        //  Get the model resource name e.g product, order product, e.t.c
        $descriptionName = $model->getResourceName();

        switch ($key) {
            case 'active':
                $name = $value ? 'Active' : 'Inactive';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' active';
                break;
            case 'is_free':
                $name = $value ? 'Free' : 'Not Free';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' free';
                break;
            case 'is_cancelled':
                $name = $value ? 'Cancelled' : 'Not Cancelled';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' cancelled';
                break;
            case 'on_sale':
                $name = $value ? 'On Sale' : 'No Sale';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' on sale';
                break;
            case 'has_price':
                $name = $value ? 'Has Price' : 'No Price';
                $description = 'This '.$descriptionName.' '.($value ? 'has' : 'does not have').' a price';
                break;
            case 'has_stock':
                $name = $value ? 'Has Stock' : 'No Stock';
                $description = 'This '.$descriptionName.' '.($value ? 'has' : 'does not have').' stock';
                break;
            case 'visible':
                $name = $value ? 'Visible' : 'Hidden';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' publicly visible to customers';
                break;
            case 'allow_variations':
                $name = $value ? 'Yes' : 'No';
                $description = 'This '.$descriptionName.' '.($value ? 'supports' : 'does not support').' variations (different versions of itself)';
                break;
            case 'show_description':
                $name = $value ? 'Yes' : 'No';
                $description = 'This '.$descriptionName.' description is '.($value ? 'visible' : 'hidden').' to customers';
                break;
            case 'has_delivery_fee':
                $name = $value ? 'Has Delivery Fee' : 'No Delivery Fee';
                $description = 'This '.$descriptionName.' '.($value ? 'has a' : 'has no').' delivery fee';
                break;
            case 'collection_verified':
                $name = $value ? 'Collection Verified' : 'Collection Not Verified';
                $description = 'This '.$descriptionName.' collection '.($value ? 'has' : 'has not').' been verified';
                break;
            case 'allow_free_delivery':
                $name = $value ? 'Free Delivery' : 'No Free Delivery';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' free for delivery';
                break;
            case 'is_abandoned':
                $name = $value ? 'Abandoned' : 'Not Abandoned';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' abandoned';
                break;
            case 'has_exceeded_maximum_allowed_quantity_per_order':
                $name = $value ? 'Exceeded' : 'Not Exceeded';
                $description = 'This '.$descriptionName.' '.($value ? 'has' : 'has not').' exceeded the maximum allowed quantity per order';
                break;


            case 'offer_discount':
                $name = $value ? 'Offer Discount' : 'Don\'t Offer Discount';
                $description = 'This '.$descriptionName.' '.($value ? 'offers' : 'does not offer').' a discount';
                break;
            case 'offer_free_delivery':
                $name = $value ? 'Offer Free Delivery' : 'Don\'t Offer Free Delivery';
                $description = 'This '.$descriptionName.' '.($value ? 'offers' : 'does not offer').' free delivery';
                break;
            case 'activate_using_code':
                $name = $value ? 'Activate With Code' : 'Don\'t Activate With Code';
                $description = 'This '.$descriptionName.' '.($value ? 'requires' : 'does not require').' a code to be activated';
                break;
            case 'activate_using_start_datetime':
                $name = $value ? 'Activate With Start Date' : 'Activate Without Start Date';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated after' : 'is activated even before').' the start date is reached';
                break;
            case 'activate_using_end_datetime':
                $name = $value ? 'Activate With End Date' : 'Activate Without End Date';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated before' : 'is activated even after').' the end date is reached';
                break;
            case 'activate_using_hours_of_day':
                $name = $value ? 'Activate On Specific Hours' : 'Activate On Any Hour';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on specific hours' : 'is activated on any hour').' of the day';
                break;
            case 'activate_using_days_of_the_week':
                $name = $value ? 'Activate On Specific Days Of The Week' : 'Activate On Any Day Of The Week';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on specific days' : 'is activated on any day').' of the week';
                break;
            case 'activate_using_days_of_the_month':
                $name = $value ? 'Activate On Specific Days Of The Month' : 'Activate On Any Day Of The Month';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on specific days' : 'is activated on any day').' of the month';
                break;
            case 'activate_using_months_of_the_year':
                $name = $value ? 'Activate On Specific Months Of The Year' : 'Activate On Any Month Of The Year';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on specific months' : 'is activated on any month').' of the year';
                break;
            case 'activate_for_new_customer':
                $name = $value ? 'Activate Specificly For New Customer' : 'Don\'t Activate Specificly For New Customer';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' activated specificly for new customers';
                break;
            case 'activate_for_existing_customer':
                $name = $value ? 'Activate Specificly For Existing Customer' : 'Don\'t Activate Specificly For Existing Customer';
                $description = 'This '.$descriptionName.' '.($value ? 'is' : 'is not').' activated specificly for existing customers';
                break;
            case 'activate_using_usage_limit':
                $name = $value ? 'Activate With A Usage Limit' : 'Activate Without A Usage Limit';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on with a usage limit' : 'is activated on without a usage limit');
                break;
            case 'activate_using_minimum_grand_total':
                $name = $value ? 'Activate On Specific Minimum Total' : 'Activate On Any Total';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on a specific minimum grand total' : 'is activated on any grand total');
                break;
            case 'activate_using_minimum_total_products':
                $name = $value ? 'Activate On Specific Total Products' : 'Activate On Any Total Products';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on a specific number of unique items' : 'is activated on any number of unique items');
                break;
            case 'activate_using_minimum_total_product_quantities':
                $name = $value ? 'Activate On Specific Total Quantity' : 'Activate On Any Total Quantity';
                $description = 'This '.$descriptionName.' '.($value ? 'is activated on a specific number of items' : 'is activated on any number of items');
                break;
            default:
                //  In the case of no match, then return the value as is
                return $value;
                break;
        }

        return [
            'name' => $name,
            'status' => $value ? true : false,
            'description' => $description,
        ];
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

            return (in_array($value['status'], ['true', true, '1', 1]) ? 1 : 0);

        }else{

            return (in_array($value, ['true', true, '1', 1]) ? 1 : 0);

        }
    }
}
