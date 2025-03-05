<?php

namespace App\Http\Requests\Models\Promotion;

use App\Models\Promotion;
use App\Rules\IsTruthy;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    use BaseTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     *  We want to modify the request input before validating
     *
     *  Reference: https://laracasts.com/discuss/channels/requests/modify-request-input-value-before-validation
     */
    public function getValidatorInstance()
    {
        try {

            /**
             *  Convert the "discount_type" to the correct format if it has been set on the request inputs
             *
             *  Example: convert "percentage" into "Percentage"
             */
            if($this->has('discount_type')) {
                $this->merge([
                    'discount_type' => strtolower($this->request->all()['discount_type'])
                ]);
            }

            //  Make sure that the "hours_of_day" is an array if provided
            if($this->has('hours_of_day') && is_string($this->request->all()['hours_of_day'])) {
                $this->merge([
                    'hours_of_day' => json_decode($this->request->all()['hours_of_day'])
                ]);
            }

            //  Make sure that the "days_of_the_week" is an array if provided
            if($this->has('days_of_the_week') && is_string($this->request->all()['days_of_the_week'])) {
                $this->merge([
                    'days_of_the_week' => json_decode($this->request->all()['days_of_the_week'])
                ]);
            }

            //  Make sure that the "days_of_the_month" is an array if provided
            if($this->has('days_of_the_month') && is_string($this->request->all()['days_of_the_month'])) {
                $this->merge([
                    'days_of_the_month' => json_decode($this->request->all()['days_of_the_month'])
                ]);
            }

            //  Make sure that the "months_of_the_year" is an array if provided
            if($this->has('months_of_the_year') && is_string($this->request->all()['months_of_the_year'])) {
                $this->merge([
                    'months_of_the_year' => json_decode($this->request->all()['months_of_the_year'])
                ]);
            }

        } catch (\Throwable $th) {

        }

        return parent::getValidatorInstance();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $offerDiscount = request()->filled('offer_discount') && $this->isTruthy(request()->input('offer_discount'));
        $offerFreeDelivery = request()->filled('offer_free_delivery') && $this->isTruthy(request()->input('offer_free_delivery'));
        $activateUsingCode = request()->filled('activate_using_code') && $this->isTruthy(request()->input('activate_using_code'));
        $activateUsingMinimumGrandTotal = request()->filled('activate_using_minimum_grand_total') && $this->isTruthy(request()->input('activate_using_minimum_grand_total'));
        $activateUsingMinimumTotalProducts = request()->filled('activate_using_minimum_total_products') && $this->isTruthy(request()->input('activate_using_minimum_total_products'));
        $activateUsingMinimumTotalProductQuantities = request()->filled('activate_using_minimum_total_product_quantities') && $this->isTruthy(request()->input('activate_using_minimum_total_product_quantities'));
        $activateUsingStartDatetime = request()->filled('activate_using_start_datetime') && $this->isTruthy(request()->input('activate_using_start_datetime'));
        $activateUsingEndDatetime = request()->filled('activate_using_end_datetime') && $this->isTruthy(request()->input('activate_using_end_datetime'));
        $activateUsingHoursOfDay = request()->filled('activate_using_hours_of_day') && $this->isTruthy(request()->input('activate_using_hours_of_day'));
        $activateUsingDaysOfTheWeek = request()->filled('activate_using_days_of_the_week') && $this->isTruthy(request()->input('activate_using_days_of_the_week'));
        $activateUsingDaysOfTheMonth = request()->filled('activate_using_days_of_the_month') && $this->isTruthy(request()->input('activate_using_days_of_the_month'));
        $activateUsingMonthsOfTheYear = request()->filled('activate_using_months_of_the_year') && $this->isTruthy(request()->input('activate_using_months_of_the_year'));
        $activateUsingUsageLimit = request()->filled('activate_using_usage_limit') && $this->isTruthy(request()->input('activate_using_usage_limit'));

        return [
            'return' => ['sometimes', 'boolean'],

            /*  General Information  */
            'name' => [
                'bail', 'sometimes', 'string', 'min:'.Promotion::NAME_MIN_CHARACTERS, 'max:'.Promotion::NAME_MAX_CHARACTERS,
                //  Make sure that this promotion name does not already exist for the same store
                Rule::unique('promotions')->where('store_id', request()->input('store_id'))->ignore(request()->promotionId)
            ],
            'active' => ['bail', 'sometimes', 'boolean'],
            'description' => ['bail', 'sometimes', 'string', 'min:'.Promotion::DESCRIPTION_MIN_CHARACTERS, 'max:'.Promotion::DESCRIPTION_MAX_CHARACTERS],

            /*  Offer Discount Information  */
            'offer_discount' => array_merge(
                ['bail', 'sometimes', 'boolean'],
                $offerFreeDelivery ? [] : ['required']
            ),
            'discount_type' => ['bail', 'sometimes', Rule::in(Promotion::DISCOUNT_TYPES())],
            'discount_percentage_rate' => ['bail', 'sometimes', 'min:1', 'max:100', 'numeric'],
            'discount_fixed_rate' => ['bail', 'sometimes', 'min:1', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],

            /*  Offer Delivery Information  */
            'offer_free_delivery' => array_merge(
                ['bail', 'sometimes', 'boolean'],
                $offerDiscount ? [] : ['required']
            ),

            /*  Code Activation Information  */
            'activate_using_code' => ['bail', 'sometimes', 'boolean'],
            'code' => ['bail', 'sometimes', Rule::requiredIf($activateUsingCode), 'string', 'min:'.Promotion::CODE_MIN_CHARACTERS, 'max:'.Promotion::CODE_MAX_CHARACTERS],

            /*  Grand Total Activation Information  */
            'activate_using_minimum_grand_total' => ['bail', 'sometimes', 'boolean'],
            'minimum_grand_total' => ['bail', 'sometimes', Rule::requiredIf($activateUsingMinimumGrandTotal), 'min:0', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],

            /*  Minimum Total Products Activation Information  */
            'activate_using_minimum_total_products' => ['bail', 'sometimes', 'boolean'],
            'minimum_total_products' => ['bail', 'sometimes', Rule::requiredIf($activateUsingMinimumTotalProducts), 'min:0', 'numeric'],

            /*  Minimum Total Products Activation Information  */
            'activate_using_minimum_total_product_quantities' => ['bail', 'sometimes', 'boolean'],
            'minimum_total_product_quantities' => ['bail', 'sometimes', Rule::requiredIf($activateUsingMinimumTotalProductQuantities), 'min:0', 'numeric'],

            /*  Start Datetime Activation Information  */
            'activate_using_start_datetime' => ['bail', 'sometimes', 'boolean'],
            'start_datetime' => ['bail', 'sometimes', Rule::requiredIf($activateUsingStartDatetime), 'date'],

            /*  End Datetime Activation Information  */
            'activate_using_end_datetime' => ['bail', 'sometimes', 'boolean'],
            'end_datetime' => ['bail', 'sometimes', Rule::requiredIf($activateUsingEndDatetime), 'date'],

            /*  Hours Of Day Activation Information  */
            'activate_using_hours_of_day' => ['bail', 'sometimes', 'boolean'],
            'hours_of_day' => ['bail', 'sometimes', Rule::requiredIf($activateUsingHoursOfDay), 'array'],
            'hours_of_day.*' => ['bail', 'required', 'string', Rule::in((new Promotion)->getHoursOfDayOptions())],

            /*  Days Of The Week Activation Information  */
            'activate_using_days_of_the_week' => ['bail', 'sometimes', 'boolean'],
            'days_of_the_week' => ['bail', 'sometimes', Rule::requiredIf($activateUsingDaysOfTheWeek), 'array'],
            'days_of_the_week.*' => ['bail', 'required', 'string', Rule::in((new Promotion)->getDaysOfTheWeekOptions())],

            /*  Days Of The Month Activation Information  */
            'activate_using_days_of_the_month' => ['bail', 'sometimes', 'boolean'],
            'days_of_the_month' => ['bail', 'sometimes', Rule::requiredIf($activateUsingDaysOfTheMonth), 'array'],
            'days_of_the_month.*' => ['bail', 'required', 'string', Rule::in((new Promotion)->getDaysOfTheMonthOptions())],

            /*  Months Of The Year Activation Information  */
            'activate_using_months_of_the_year' => ['bail', 'sometimes', 'boolean'],
            'months_of_the_year' => ['bail', 'sometimes', Rule::requiredIf($activateUsingMonthsOfTheYear), 'array'],
            'months_of_the_year.*' => ['bail', 'required', 'string', Rule::in((new Promotion)->getMonthsOfTheYearOptions())],

            /*  Usage Activation Information  */
            'activate_using_usage_limit' => ['bail', 'sometimes', 'boolean'],
            'remaining_quantity' => ['bail', 'sometimes', Rule::requiredIf($activateUsingUsageLimit), 'min:0', 'max:'.Promotion::REMAINING_QUANTITY_MAX, 'numeric'],

            /*  Customer Activation Information  */
            'activate_for_existing_customer' => ['bail', 'sometimes', 'boolean'],
            'activate_for_new_customer' => ['bail', 'sometimes', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'discount_type.in' => 'Answer "'.collect(Promotion::DISCOUNT_TYPES())->join('", "', '" or "').'" to indicate the promotion discount type',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'hours_of_day.*' => 'hours of the day',
            'days_of_the_week.*' => 'days of the week',
            'days_of_the_month.*' => 'days of the month',
            'months_of_the_year.*' => 'months of the year'
        ];
    }
}
