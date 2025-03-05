<?php

namespace App\Http\Requests\Models\Order;

use App\Traits\Base\BaseTrait;
use Illuminate\Foundation\Http\FormRequest;

class ShowOrderRequest extends FormRequest
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
            'with_store' => ['bail', 'sometimes', 'boolean'],
            'with_customer' => ['bail', 'sometimes', 'boolean'],
            'with_transactions' => ['bail', 'sometimes', 'boolean'],
            'with_count_transactions' => ['bail', 'sometimes', 'boolean'],
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
