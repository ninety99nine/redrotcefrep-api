<?php

namespace App\Http\Requests\Models\AutoBillingSchedule;

use App\Traits\Base\BaseTrait;
use Illuminate\Foundation\Http\FormRequest;

class CreateAutoBillingScheduleRequest extends FormRequest
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
            'return' => ['sometimes', 'boolean'],
            'active' => ['bail', 'required', 'boolean'],
            'user_id' => ['required', 'uuid'],
            'store_id' => ['required', 'uuid'],
            'pricing_plan_id' => ['required', 'uuid'],
            'payment_method_id' => ['required', 'uuid'],
            'next_attempt_date' => ['required', 'date']
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
