<?php

namespace App\Http\Requests\Models\Section;

use App\Models\Section;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSectionRequest extends FormRequest
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
            'name' => ['bail', 'sometimes', 'string', 'min:'.Section::NAME_MIN_CHARACTERS, 'max:'.Section::NAME_MAX_CHARACTERS],
            'background_color' => ['bail', 'nullable', 'string', 'size:7'],
            'visible' => ['bail', 'sometimes', 'boolean'],
            'top_divider_type' => ['bail', 'nullable', Rule::in(Section::DIVIDERS())],
            'top_divider_color' => ['bail', 'nullable', 'string', 'size:9'],
            'top_divider_height' => ['bail', 'nullable', 'integer', 'min:0'],
            'bottom_divider_type' => ['bail', 'nullable', Rule::in(Section::DIVIDERS())],
            'bottom_divider_color' => ['bail', 'nullable', 'string', 'size:9'],
            'bottom_divider_height' => ['bail', 'nullable', 'integer', 'min:0'],
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
