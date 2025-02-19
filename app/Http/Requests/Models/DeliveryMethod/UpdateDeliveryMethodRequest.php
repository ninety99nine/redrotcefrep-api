<?php

namespace App\Http\Requests\Models\DeliveryMethod;

use App\Models\DeliveryMethod;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryMethodRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $moneyRules = ['bail', 'sometimes', 'min:0', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'];

        return [
            'return' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'name' => [
                'bail', 'required', 'string', 'min:'.DeliveryMethod::NAME_MIN_CHARACTERS, 'max:'.DeliveryMethod::NAME_MAX_CHARACTERS,
                Rule::unique('delivery_methods')->where('store_id', request()->input('store_id'))
            ],
            'description' => ['bail', 'nullable', 'string', 'min:'.DeliveryMethod::DESCRIPTION_MIN_CHARACTERS, 'max:'.DeliveryMethod::DESCRIPTION_MAX_CHARACTERS],
            'qualify_on_minimum_grand_total' => ['sometimes', 'boolean'],
            'minimum_grand_total' => $moneyRules,
            'offer_free_delivery_on_minimum_grand_total' => ['sometimes', 'boolean'],
            'free_delivery_minimum_grand_total' => $moneyRules,
            'ask_for_an_address' => ['sometimes', 'boolean'],
            'pin_location_on_map' => ['sometimes', 'boolean'],
            'show_distance_on_invoice' => ['sometimes', 'boolean'],
            'charge_fee' => ['sometimes', 'boolean'],
            'fee_type' => ['sometimes', Rule::in(DeliveryMethod::DELIVERY_METHOD_FEE_TYPES())],
            'percentage_fee_rate' => ['bail', 'sometimes', 'min:1', 'max:100', 'numeric'],
            'flat_fee_rate' => $moneyRules,
            'weight_categories' => ['sometimes', 'nullable', 'array'],
            'distance_zones' => ['sometimes', 'nullable', 'array'],
            'postal_code_zones' => ['sometimes', 'nullable', 'array'],
            'fallback_fee_type' => ['sometimes', Rule::in(DeliveryMethod::DELIVERY_METHOD_FALLBACK_FEE_TYPES())],
            'fallback_percentage_fee_rate' => ['bail', 'sometimes', 'min:1', 'max:100', 'numeric'],
            'fallback_flat_fee_rate' => $moneyRules,
            'set_schedule' => ['sometimes', 'boolean'],
            'schedule_type' => ['sometimes', Rule::in(DeliveryMethod::DELIVERY_METHOD_SCHEDULE_TYPES())],
            'operational_hours' => ['sometimes', 'nullable', 'array'],
            'auto_generate_time_slots' => ['sometimes', 'boolean'],
            'time_slot_interval_value' => ['sometimes', 'integer', 'min:1'],
            'time_slot_interval_unit' => ['sometimes', Rule::in(DeliveryMethod::AUTO_GENERATE_TIME_SLOTS_UNITS())],
            'same_day_delivery' => ['sometimes', 'boolean'],
            'require_minimum_notice_for_orders' => ['sometimes', 'boolean'],
            'earliest_delivery_time_value' => ['sometimes', 'integer', 'min:1'],
            'earliest_delivery_time_unit' => ['sometimes', Rule::in(DeliveryMethod::DELIVERY_TIME_UNITS())],
            'restrict_maximum_notice_for_orders' => ['sometimes', 'boolean'],
            'latest_delivery_time_value' => ['sometimes', 'integer', 'min:1'],
            'set_daily_order_limit' => ['sometimes', 'boolean'],
            'daily_order_limit' => ['sometimes', 'integer', 'min:1'],
            'capture_additional_fields' => ['sometimes', 'boolean'],
            'additional_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [];
    }
}
