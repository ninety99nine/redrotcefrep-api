<?php

namespace App\Http\Requests\Models\Row;

use App\Models\Row;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRowRequest extends FormRequest
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
            'name' => ['bail', 'required', 'string', 'min:'.Row::NAME_MIN_CHARACTERS, 'max:'.Row::NAME_MAX_CHARACTERS],
            'background_color' => ['bail', 'nullable', 'string', 'size:7'],
            'visible' => ['bail', 'sometimes', 'boolean'],
            'layout' => ['bail', 'required', Rule::in(Row::LAYOUTS())],
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
