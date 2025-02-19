<?php

namespace App\Http\Requests\Models\Product;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductPhotoRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'product_photo'   => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120', // max size in kilobytes (5MB)
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
            'photo.max' => 'The :attribute must not be greater than 4 megabytes',
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
