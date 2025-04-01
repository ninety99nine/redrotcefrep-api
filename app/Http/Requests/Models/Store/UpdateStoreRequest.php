<?php

namespace App\Http\Requests\Models\Store;

use App\Models\Store;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use App\Services\Money\MoneyService;
use App\Services\Country\CountryService;
use App\Services\Language\LanguageService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
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
        $storeId = request()->storeId;

        /*
         *  Note that image upload is not possible on a PUT/PATCH request, which is why
         *  logo uploading checks are not implemented on this method except for when
         *  were are creating a store on the POST request.
         *
         *  Reference: https://stackoverflow.com/questions/65008650/how-to-use-put-method-in-laravel-api-with-file-upload
         */
        return [
            'emoji' => ['bail', 'sometimes', 'nullable', 'string'],
            'logo' => ['bail', 'sometimes', 'nullable', 'mimetypes:image/jpeg,image/png,image/jpg,image/gif,image/bmp', 'max:4096'],
            'cover_photo' => ['bail', 'sometimes', 'nullable', 'mimetypes:image/jpeg,image/png,image/jpg,image/gif,image/bmp', 'max:4096'],
            'name' => ['bail', 'sometimes', 'string', 'min:'.Store::NAME_MIN_CHARACTERS, 'max:'.Store::NAME_MAX_CHARACTERS],
            'alias' => [
                'bail', 'sometimes', 'string', 'min:'.Store::ALIAS_MIN_CHARACTERS, 'max:'.Store::ALIAS_MAX_CHARACTERS,
                Rule::unique('stores')->ignore($storeId)
            ],
            'call_to_action' => ['bail', 'sometimes', 'nullable', 'min:'.Store::CALL_TO_ACTION_MIN_CHARACTERS, 'max:'.Store::CALL_TO_ACTION_MAX_CHARACTERS],
            'description' => ['bail', 'sometimes', 'nullable', 'min:'.Store::DESCRIPTION_MIN_CHARACTERS, 'max:'.Store::DESCRIPTION_MAX_CHARACTERS],
            'sms_sender_name' => [
                'bail', 'sometimes', 'nullable', 'min:'.Store::SMS_SENDER_NAME_MIN_CHARACTERS, 'max:'.Store::SMS_SENDER_NAME_MAX_CHARACTERS,
            ],
            'currency' => [
                'bail', 'sometimes', 'string', 'size:3',
                Rule::in(collect($this->supportedCurrencySymbols)->keys())
            ],
            'verified' => ['exclude'],
            'online' => ['bail', 'sometimes', 'boolean'],
            'offline_message' => ['bail', 'sometimes', 'string', 'min:'.Store::OFFLINE_MESSAGE_MIN_CHARACTERS, 'max:'.Store::OFFLINE_MESSAGE_MAX_CHARACTERS],
            'identified_orders' => ['bail', 'sometimes', 'boolean'],

            'offer_rewards' => ['bail', 'sometimes', 'boolean'],
            'reward_percentage_rate' => ['bail', 'sometimes', 'min:0', 'max:100', 'numeric'],

            'social_links' => ['bail', 'sometimes', 'array'],
            'social_links.*.name' => ['bail', 'nullable', 'string', 'min:'.Store::SOCIAL_LINK_NAME_MIN_CHARACTERS, 'max:'.Store::SOCIAL_LINK_NAME_MAX_CHARACTERS],
            'social_links.*.link' => ['bail', 'nullable', 'url:http,https'],

            'show_opening_hours' => ['bail', 'sometimes', 'boolean'],
            'allow_checkout_on_closed_hours' => ['bail', 'sometimes', 'boolean'],
            'opening_hours' => ['bail', 'sometimes', 'array', 'size:7'],
            'opening_hours.*.available' => ['bail', 'boolean'],
            'opening_hours.*.hours' => ['bail', 'array'],
            'opening_hours.*.hours.*' => ['bail', 'array'],
            'opening_hours.*.hours.*.*' => ['bail', 'string', 'regex:/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/'],

            'checkout_fees' => ['bail', 'sometimes', 'array', 'max:5'],
            'checkout_fees.*.name' => ['bail', 'required', 'string', 'min:'.Store::CHECKOUT_FEE_NAME_MIN_CHARACTERS, 'max:'.Store::CHECKOUT_FEE_NAME_MAX_CHARACTERS],
            'checkout_fees.*.rate_type' => ['bail', 'required', Rule::in(Store::CHECKOUT_FEE_TYPES())],
            'checkout_fees.*.flat_rate' => ['bail', 'required_without:checkout_fees.percentage_rate', 'min:0', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'checkout_fees.*.percentage_rate' => ['bail', 'required_without:checkout_fees.flat_rate', 'min:0', 'max:100', 'numeric'],

            'email' => ['bail', 'nullable', 'sometimes', 'email'],
            'ussd_mobile_number' => ['bail', 'nullable', 'sometimes', 'string', 'phone'],
            'contact_mobile_number' => ['bail', 'nullable', 'sometimes', 'string', 'phone'],
            'whatsapp_mobile_number' => ['bail', 'nullable', 'sometimes', 'string', 'phone'],

            'country' => ['bail', 'sometimes', Rule::in(collect(CountryService::getCountries())->map(fn($country) => $country->iso)->toArray())],
            'currency' => ['bail', 'sometimes', Rule::in(collect(MoneyService::getCurrencies())->map(fn($currency) => $currency['code'])->toArray())],
            'language' => ['bail', 'sometimes', Rule::in(collect((new LanguageService)->getLanguages())->map(fn($language) => $language['code'])->toArray())],
            'distance_unit' => ['bail', 'sometimes', Rule::in(Store::DISTANCE_UNIT_OPTIONS())],
            'weight_unit' => ['bail', 'sometimes', Rule::in(Store::WEIGHT_UNIT_OPTIONS())],
            'tax_method' => ['bail', 'sometimes', Rule::in(Store::TAX_METHOD_OPTIONS())],
            'tax_id' => ['bail', 'sometimes', 'nullable', 'string', 'min:'.Store::TAX_ID_MIN_CHARACTERS, 'max:'.Store::TAX_ID_MAX_CHARACTERS],
            'tax_percentage_rate' => ['bail', 'sometimes', 'min:0', 'max:100', 'numeric'],

            'delivery_note' => ['bail', 'sometimes', 'nullable', 'min:'.Store::DELIVERY_NOTE_MIN_CHARACTERS, 'max:'.Store::DELIVERY_NOTE_MAX_CHARACTERS],
            'allow_delivery' => ['bail', 'sometimes', 'boolean'],
            'allow_free_delivery' => ['bail', 'sometimes', 'boolean'],
            'delivery_flat_fee' => ['bail', 'sometimes', 'min:0', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'delivery_destinations' => ['bail', 'sometimes', 'array'],
            'delivery_destinations.*.name' => ['bail', 'required', 'string', 'min:'.Store::DELIVERY_DESTINATION_NAME_MIN_CHARACTERS, 'max:'.Store::DELIVERY_DESTINATION_NAME_MAX_CHARACTERS],
            'delivery_destinations.*.allow_free_delivery' => ['bail', 'required', 'boolean'],
            'delivery_destinations.*.cost' => ['bail', 'required', 'min:0', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],

            'pickup_note' => ['bail', 'sometimes', 'nullable', 'min:'.Store::PICKUP_NOTE_MIN_CHARACTERS, 'max:'.Store::PICKUP_NOTE_MAX_CHARACTERS],
            'allow_pickup' => ['bail', 'sometimes', 'boolean'],
            'pickup_destinations' => ['bail', 'sometimes', 'array'],
            'pickup_destinations.*.name' => ['bail', 'required', 'string', 'min:'.Store::PICKUP_DESTINATION_NAME_MIN_CHARACTERS, 'max:'.Store::PICKUP_DESTINATION_NAME_MAX_CHARACTERS],
            'pickup_destinations.*.address' => ['bail', 'nullable', 'min:'.Store::PICKUP_DESTINATION_ADDRESS_MIN_CHARACTERS, 'max:'.Store::PICKUP_DESTINATION_ADDRESS_MAX_CHARACTERS],

            'allow_deposit_payments' => ['bail', 'sometimes', 'boolean'],
            'deposit_percentages' => ['bail', 'sometimes', 'array'],
            'deposit_percentages.*' => ['bail', 'required', 'integer', 'min:5', 'max:95'],

            'allow_installment_payments' => ['bail', 'sometimes', 'boolean'],
            'installment_percentages' => ['bail', 'sometimes', 'array'],
            'installment_percentages.*' => ['bail', 'required', 'integer', 'min:5', 'max:95'],

            'supported_payment_methods' => ['bail', 'sometimes', 'array'],
            'supported_payment_methods.*.id' => [
                'bail', 'required', 'uuid', Rule::exists('payment_methods')
            ],
            'supported_payment_methods.*.active' => ['bail', 'required', 'boolean'],
            'supported_payment_methods.*.instruction' => ['bail', 'nullable', 'string'],
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
            'delivery_destinations.*.name' => 'delivery destination name',
            'delivery_destinations.*.cost' => 'delivery destination name',
            'delivery_destinations.*.allow_free_delivery' => 'delivery destination name',

            'pickup_destinations.*.name' => 'pickup destination name',
            'pickup_destinations.*.address' => 'pickup destination address',

            'deposit_percentages.*' => 'deposit percentage',
            'installment_percentages.*' => 'installment percentage',

            'social_links.*.name' => 'social name',
            'social_links.*.link' => 'social link',

            'opening_hours.*.hours' => 'open hours',
            'opening_hours.*.hours.*.*' => 'time',

            'alias' => 'store link'
        ];
    }
}
