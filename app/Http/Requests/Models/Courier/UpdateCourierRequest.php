<?php

namespace App\Http\Requests\Models\Courier;

use App\Models\Courier;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourierRequest extends FormRequest
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
            'name' => [
                'bail', 'sometimes', 'string', 'min:'.Courier::NAME_MIN_CHARACTERS, 'max:'.Courier::NAME_MAX_CHARACTERS,
                Rule::unique('couriers')
            ],
            'tracking_page' => [
                'bail', 'sometimes', 'string', 'url:http,https', 'min:'.Courier::TRACKING_PAGE_MIN_CHARACTERS, 'max:'.Courier::TRACKING_PAGE_MAX_CHARACTERS,
                Rule::unique('couriers')
            ]
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
