<?php

namespace App\Http\Requests\Models\PricingPlan;

use App\Models\PricingPlan;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreatePricingPlanRequest extends FormRequest
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

            /*  General Information  */
            'active' => ['sometimes', 'boolean'],
            'name' => ['bail', 'required', 'string', 'min:'.PricingPlan::NAME_MIN_CHARACTERS, 'max:'.PricingPlan::NAME_MAX_CHARACTERS],
            'description' => ['bail', 'sometimes', 'nullable', 'min:'.PricingPlan::DESCRIPTION_MIN_CHARACTERS, 'max:'.PricingPlan::DESCRIPTION_MAX_CHARACTERS],
            'type' => ['bail', 'required', Rule::in(PricingPlan::TYPES())],
            'billing_type' => ['bail', 'required', Rule::in(PricingPlan::BILLING_TYPES())],
            'currency' => ['bail', 'sometimes', 'string', 'size:3', Rule::in(collect($this->supportedCurrencySymbols)->keys())],
            'price' => ['bail', 'required', 'min:0', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'discount_percentage_rate' => ['bail', 'sometimes', 'min:1', 'max:100', 'numeric'],
            'max_auto_billing_attempts' => ['bail', 'sometimes', 'min:1', 'max:10', 'numeric'],
            'auto_billing_disabled_sms_message' => ['bail', 'sometimes', 'nullable', 'min:1', 'max:160'],
            'supports_web' => ['sometimes', 'boolean'],
            'supports_ussd' => ['sometimes', 'boolean'],
            'supports_mobile' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'features' => ['sometimes', 'nullable', 'array'],
            'features.*' => ['string']
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
        return [
            'platforms.*' => 'feature',
            'features.*' => 'feature'
        ];
    }
}
