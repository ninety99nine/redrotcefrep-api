<?php

namespace App\Http\Requests\Models\Order;

use App\Models\Order;
use App\Models\Customer;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Models\ShoppingCart\InspectShoppingCartRequest;

class CreateOrderRequest extends FormRequest
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
             *  Convert the "delivery_destination_name" to the correct format if it has been set on the request inputs
             *
             *  Example: convert "Gaborone" into "gaborone"
             */
            if($this->has('delivery_destination_name')) {
                $this->merge([
                    'delivery_destination_name' => strtolower($this->get('delivery_destination_name'))
                ]);
            }

            /**
             *  Convert the "pickup_destination_name" to the correct format if it has been set on the request inputs
             *
             *  Example: convert "Gaborone" into "gaborone"
             */
            if($this->has('pickup_destination_name')) {
                $this->merge([
                    'pickup_destination_name' => strtolower($this->get('pickup_destination_name'))
                ]);
            }

            /**
             *  Convert the "collection_type" to the correct format if it has been set on the request inputs
             *
             *  Example: convert "Deliver" or "deliver" into "deliver"
             */
            if($this->has('collection_type')) {
                $this->merge([
                    'collection_type' => strtolower($this->get('collection_type'))
                ]);
            }

            //  Make sure that the "cart_products" is an array if provided
            if($this->has('cart_products') && is_string($this->request->all()['cart_products'])) {
                $this->merge([
                    'cart_products' => json_decode($this->request->all()['cart_products'])
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
        $collectionTypes = collect(Order::COLLECTION_TYPES())->map(fn($filter) => strtolower($filter));
        //  $pickupDestinationNames = collect(request()->store->pickup_destinations)->pluck('name')->map(fn($pickupDestinationName) => strtolower($pickupDestinationName));
        //  $deliveryDestinationNames = collect(request()->store->delivery_destinations)->pluck('name')->map(fn($deliveryDestinationName) => strtolower($deliveryDestinationName));

        return array_merge(

            // Rules specific to the ConvertCartRequest
            [
                'store_id' => ['required', 'uuid'],
                'friends_can_collect' => ['bail', 'sometimes', 'boolean'],

                'friend_group_id' => ['bail', 'sometimes', 'uuid'],

                'address_id' => ['bail', 'sometimes', 'uuid'],
                'occasion_id' => ['bail', 'sometimes', 'nullable', 'uuid'],

                'collection_type' => ['bail', 'sometimes', 'string', Rule::in($collectionTypes)],

                'customer_note' => ['bail', 'sometimes', 'string', 'min:'.Order::CUSTOMER_NOTE_MIN_CHARACTERS, 'max:'.Order::CUSTOMER_NOTE_MAX_CHARACTERS],
                'internal_note' => ['bail', 'sometimes', 'string', 'min:'.Order::INTERNAL_NOTE_MIN_CHARACTERS, 'max:'.Order::INTERNAL_NOTE_MAX_CHARACTERS],

                'delivery_destination_name' => ['bail', 'sometimes', 'string' /*, Rule::in($deliveryDestinationNames)*/],
                'pickup_destination_name' => ['bail', 'sometimes', 'string' /*, Rule::in($pickupDestinationNames)*/],

                'customer' => ['required', 'array'],
                'customer.first_name' => ['bail', 'required', 'string', 'min:'.Customer::FIRST_NAME_MIN_CHARACTERS, 'max:'.Customer::FIRST_NAME_MAX_CHARACTERS],
                'customer.last_name' => ['bail', 'sometimes', 'nullable', 'string', 'min:'.Customer::LAST_NAME_MIN_CHARACTERS, 'max:'.Customer::LAST_NAME_MAX_CHARACTERS],
                'customer.mobile_number' => ['bail', 'sometimes', 'nullable', 'string', 'phone'],
                'customer.email' => ['bail', 'sometimes', 'nullable', 'string', 'email'],
            ],

            // Merge with rules from the InspectShoppingCartRequest
            (new InspectShoppingCartRequest())->rules()

        );
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return (new InspectShoppingCartRequest())->messages();
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return (new InspectShoppingCartRequest())->attributes();
    }
}
