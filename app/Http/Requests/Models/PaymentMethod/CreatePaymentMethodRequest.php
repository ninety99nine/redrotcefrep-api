<?php

namespace App\Http\Requests\Models\PaymentMethod;

use App\Models\PaymentMethod;
use Illuminate\Validation\Rule;
use App\Traits\Base\BaseTrait;
use App\Services\Country\CountryService;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;

class CreatePaymentMethodRequest extends FormRequest
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
            'active' => ['sometimes', 'boolean'],
            'name' => [
                'bail', 'required', 'string', 'min:'.PaymentMethod::NAME_MIN_CHARACTERS, 'max:'.PaymentMethod::NAME_MAX_CHARACTERS,
                request()->filled('store_id')
                    ? Rule::unique('payment_methods')->where('store_id', request()->input('store_id'))
                    : Rule::unique('payment_methods')
            ],
            'type' => [
                'bail', 'required', 'string', 'min:'.PaymentMethod::TYPE_MIN_CHARACTERS, 'max:'.PaymentMethod::TYPE_MAX_CHARACTERS,
                request()->filled('store_id')
                    ? Rule::unique('payment_methods')->where('store_id', request()->input('store_id'))
                    : Rule::unique('payment_methods')
            ],
            'category' => ['bail', 'required', Rule::in(PaymentMethod::PAYMENT_METHOD_CATEGORIES())],
            'countries' => ['sometimes', 'nullable', 'array', 'required'],
            'countries.*' => ['string', Rule::in(collect((new CountryService)->getCountries())->map(fn($country) => $country->iso)->toArray())],
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
