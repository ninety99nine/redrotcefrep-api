<?php

namespace App\Http\Requests\Models\Column;

use Illuminate\Foundation\Http\FormRequest;

class UpdateColumnVisibilityRequest extends FormRequest
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
            'row_id' => ['required', 'uuid'],
            'visibility' => ['required', 'array'],
            'visibility.*.visible' => ['bail', 'boolean'],
            'visibility.*.id' => ['bail', 'uuid', 'distinct'],
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
