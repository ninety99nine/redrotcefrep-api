<?php

namespace App\Http\Requests\Models\PaymentMethod;

use App\Models\PaymentMethod;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
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
        $paymentMethod = PaymentMethod::find(request()->paymentMethodId);

        return [
            'return' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'name' => [
                'bail', 'sometimes', 'string', 'min:'.PaymentMethod::NAME_MIN_CHARACTERS, 'max:'.PaymentMethod::NAME_MAX_CHARACTERS,
                $paymentMethod && $paymentMethod->store_id
                    ? Rule::unique('payment_methods')->where('store_id', $paymentMethod->store_id)->ignore(request()->paymentMethodId)
                    : Rule::unique('payment_methods')->ignore(request()->paymentMethodId)
            ],
            'type' => ['exclude'],
            'category' => ['exclude'],
            'countries' => ['exclude'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'require_proof_of_payment' => ['sometimes', 'boolean'],
            'automatically_mark_as_paid' => ['sometimes', 'boolean'],
            'contact_seller_before_payment' => ['sometimes', 'boolean'],
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
