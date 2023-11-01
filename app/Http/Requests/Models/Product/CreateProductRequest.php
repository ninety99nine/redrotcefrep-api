<?php

namespace App\Http\Requests\Models\Product;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
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
        /**
         *  Convert the "allowed_quantity_per_order" to the correct format if it has been set on the request inputs
         *
         *  Example: convert "unlimited" into "Unlimited"
         */
        if($this->request->has('allowed_quantity_per_order')) {
            $this->merge([
                'allowed_quantity_per_order' => strtolower($this->request->get('allowed_quantity_per_order'))
            ]);
        }

        /**
         *  Convert the "stock_quantity_type" to the correct format if it has been set on the request inputs
         *
         *  Example: convert "unlimited" into "Unlimited"
         */
        if($this->request->has('stock_quantity_type')) {
            $this->merge([
                'stock_quantity_type' => strtolower($this->request->get('stock_quantity_type'))
            ]);
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
        $moneyRules = ['bail', 'required', 'min:0', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'];
        $stockQuantityType = collect(Product::STOCK_QUANTITY_TYPE)->map(fn($option) => strtolower($option));
        $allowedQuantityPerOrder = collect(Product::ALLOWED_QUANTITY_PER_ORDER)->map(fn($option) => strtolower($option));

        return [

            /*  General Information  */
            'name' => [
                'bail', 'required', 'string', 'min:'.Product::NAME_MIN_CHARACTERS, 'max:'.Product::NAME_MAX_CHARACTERS,
                /**
                 *  Make sure that this product name does not
                 *  already exist for the same store
                 */
                Rule::unique('products')->where('store_id', request()->store->id)
            ],
            'visible' => ['bail', 'sometimes', 'required', 'boolean'],
            'show_description' => ['bail', 'sometimes', 'required', 'boolean'],
            'description' => ['bail', 'sometimes', 'required', 'string', 'min:'.Product::DESCRIPTION_MIN_CHARACTERS, 'max:'.Product::DESCRIPTION_MAX_CHARACTERS],

            /*  Tracking Information  */
            'sku' => ['bail', 'sometimes', 'required', 'string', 'min:'.Product::SKU_MIN_CHARACTERS, 'max:'.Product::SKU_MAX_CHARACTERS],
            'barcode' => ['bail', 'sometimes', 'required', 'string', 'min:'.Product::BARCODE_MIN_CHARACTERS, 'max:'.Product::BARCODE_MAX_CHARACTERS],

            /*  Variation Information
             *
             *  (1) allow_variations: Exclude from the request data returned
             *      - Only modifiable on product update
             *
             *  (2) variant_attributes: Exclude from the request data returned
             *      - Only modifiable on creation of variations
             */
            'allow_variations' => ['exclude'],
            'variant_attributes' => ['exclude'],
            'total_variations' => ['exclude'],
            'total_visible_variations' => ['exclude'],

            /*  Pricing Information
             *
             *  currency: Exclude from the request data returned
             *      - The currency is derived from the store itself
            */
            'is_free' => ['bail', 'sometimes', 'required', 'boolean'],
            'currency' => ['exclude'],
            'unit_regular_price' => $moneyRules,
            'unit_sale_price' => collect($moneyRules)->add('sometimes')->toArray(),
            'unit_cost_price' => collect($moneyRules)->add('sometimes')->toArray(),

            /*  Quantity Information  */
            'allowed_quantity_per_order' => ['bail', 'sometimes', 'required', 'string', Rule::in($allowedQuantityPerOrder)],
            'maximum_allowed_quantity_per_order' => [
                'bail', 'sometimes', 'integer', 'numeric', 'min:'.Product::MAXIMUM_ALLOWED_QUANTITY_PER_ORDER_MIN, 'max:'.Product::MAXIMUM_ALLOWED_QUANTITY_PER_ORDER_MAX,
                Rule::requiredIf(fn() => request()->input('allowed_quantity_per_order') === 'limited')
            ],

            /*  Stock Information  */
            'stock_quantity_type' => ['bail', 'sometimes', 'required', 'string', Rule::in($stockQuantityType)],
            'stock_quantity' => [
                'bail', 'sometimes', 'integer', 'numeric', 'min:'.Product::STOCK_QUANTITY_MIN, 'max:'.Product::STOCK_QUANTITY_MAX,
                Rule::requiredIf(fn() => strtolower(request()->input('stock_quantity_type')) === 'limited')
            ],

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
            'allowed_quantity_per_order.string' => 'Answer "'.collect(Product::ALLOWED_QUANTITY_PER_ORDER)->join('", "', '" or "').'" to indicate the allowed quantity per order',
            'allowed_quantity_per_order.in' => 'Answer "'.collect(Product::ALLOWED_QUANTITY_PER_ORDER)->join('", "', '" or "').'" to indicate the allowed quantity per order',
            'stock_quantity_type.string' => 'Answer "'.collect(Product::STOCK_QUANTITY_TYPE)->join('", "', '" or "').'" to indicate the stock quantity type',
            'stock_quantity_type.in' => 'Answer "'.collect(Product::STOCK_QUANTITY_TYPE)->join('", "', '" or "').'" to indicate the stock quantity type',
        ];
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
