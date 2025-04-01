<?php

namespace App\Traits\Base;

use Carbon\Carbon;
use App\Enums\ReturnType;
use Illuminate\Support\Str;
use App\Services\Country\CountryService;

trait BaseTrait
{
    /**
     *  Complete address.
     *
     *  @return string
     */
    public function completeAddress($addressLine, $addressLine2 = null, $city = null, $state = null, $postalCode = null, $country = null): string
    {
        $countryName = function() use ($country) {
            if(empty($country)) return '';
            return CountryService::findCountryNameByTwoLetterCountryCode($country) ?? '';
        };

        return collect([$addressLine, $addressLine2, $city, $state, $postalCode, $countryName()])->map('trim')->filter()->unique()->join(', ');
    }

    public function convertToPercentageFormat($value)
    {
        $roundedValue = (float) ($value ?? 0);

        return [
            'value' => $roundedValue,
            'value_symbol' => $roundedValue . '%',
        ];
    }

    public function convertNumberToShortenedPrefix($number)
    {
        $input = number_format($number);

        $input_count = substr_count($input, ',');

        if($input_count != '0') {
            if($input_count == '1'){
                return substr($input, 0, -4).'k';
            } else if($input_count == '2'){
                return substr($input, 0, -8).'m';
            } else if($input_count == '3'){
                return substr($input, 0,  -12).'b';
            } else {
                return;
            }
        } else {
            return $input;
        }
    }

    public function jsonToArray($value, $returnType = ReturnType::ARRAY) {

        if( is_null($value) ) {

            if($returnType == ReturnType::ARRAY->value) {

                return [];

            }elseif($returnType == ReturnType::NULL->value) {

                return null;

            }

        }else if(is_array($value)) {

            return $value;

        }else{

            /**
             *  Json decode the data to convert json string to array
             *
             *  Reference: https://www.php.net/manual/en/function.json-decode.php
             */
            return json_decode($value, true);

        }

    }

    /**
     *  Prepare the item line for insertion into the database
     *
     *  @param boolean $convertToJson
     */
    public function readyForDatabase($convertToJson = true)
    {
        /**
         *  Convert the specified item line (order product or Promotion) to array.
         *  This is because we don't want the casting functionality of the
         *  OrderProduct / OrderPromotion Model e.g To avoid automatic
         *  casting to array or vice-versa.
         */
        $output = $this->toArray();

        /**
         *  Foreach of the item line attributes, convert the value to a JSON representation
         *  of itself in the case that the value is an array. This is so that we can insert
         *  the value into the database without the "Array to string conversion" error
         *  especially when using Illuminate\Support\Facades\DB
         *
         *  Sometimes however we may not need to do this especially if we are updating an
         *  existing Model that already implements the cast to "array" feature, since that
         *  will cause double casting which is not desired. Laravel does not automatically
         *  check if the value is a string or an array before converting to Json. It should
         *  only convert an array to string, but sometimes when it receives a string it will
         *  process the string causing unwanted results. Because of this you can conviniently
         *  indicate whether to convert to JSON or not.
         */
        if( $convertToJson ) {

            // Get any fields that must be cast to dates
            $dateFields = collect($this->casts)->filter(function($castValue, $castName) {
                return $castValue == 'datetime';
            })->keys();

            // Get any fields that must be cast to money
            $moneyFields = collect($this->casts)->filter(function($castValue, $castName) {
                return $castValue == 'App\Casts\Money';
            })->keys();

            foreach($output as $attributeName => $attributeValue) {

                //  Check if this field is a money field
                $isMoneyField = collect($moneyFields)->contains($attributeName);

                //  Check if this field is a date field
                $isDateTimeField = collect($dateFields)->contains($attributeName);

                //  If this attribute value is a type of array and is not a carbon date
                if( is_array( $attributeValue ) ) {

                    //  Convert this value to a JSON representation of itself
                    $output[$attributeName] = json_encode($attributeValue);

                }elseif($isDateTimeField) {

                    //  Convert this value to carbon date format
                    $output[$attributeName] = Carbon::parse($attributeValue);

                }elseif($isMoneyField) {

                    //  Convert this value to float format
                    $output[$attributeName] = $attributeValue->amount;

                }


            }

        }

        return $output;
    }

    /**
     *  Get the current class basename as lowercase words separated by spaces
     *
     *  e.g OrderProduct into order product
     */
    public function getResourceName()
    {
        return $this->separateWordsThenLowercase(class_basename($this));
    }

    /**
     *  Convert the type to the correct format if it has been set on the request inputs
     *
     *  Example: convert "teamMember", "TeamMember" or "Team Member" into "team member"
     */
    public function separateWordsThenLowercase($value) {
        return strtolower(Str::snake($value, ' '));
    }

    public function getCurrentPage()
    {
        $page = (int) request()->input('page');
        return $page > 0 ? $page : '1';
    }

    /**
     * Check if the give value is matches any truthy value
     *
     * @param mixed $value
     */
    public function isTruthy($value) {
        return in_array($value, [true, 'true', '1'], true);
    }
}
