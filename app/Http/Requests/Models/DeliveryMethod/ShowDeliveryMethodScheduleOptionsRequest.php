<?php

namespace App\Http\Requests\Models\DeliveryMethod;

use App\Models\DeliveryMethod;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ShowDeliveryMethodScheduleOptionsRequest extends FormRequest
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
        return [
            'delivery_date' => ['bail', 'date'],
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
