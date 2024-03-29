<?php

namespace App\Http\Requests\Models\User;

use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateSmsAlertTransactionRequest extends FormRequest
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
            'embed' => ['bail', 'sometimes', 'required', 'boolean'],
            'sms_credits' => ['bail', 'sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'payment_method_id' => ['bail', 'sometimes', 'required', 'numeric', 'min:1', 'exists:payment_methods,id'],
            'subscription_plan_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('subscription_plans', 'id')]
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
