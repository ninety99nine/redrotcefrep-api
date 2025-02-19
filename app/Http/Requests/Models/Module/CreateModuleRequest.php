<?php

namespace App\Http\Requests\Models\Module;

use App\Models\Module;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateModuleRequest extends FormRequest
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
            'name' => ['bail', 'required', 'string', 'min:'.Module::NAME_MIN_CHARACTERS, 'max:'.Module::NAME_MAX_CHARACTERS],
            'type' => ['bail', 'required', Rule::in(Module::TYPES())],
            'settings' => ['required', 'array'],
            'column_id' => ['required', 'uuid']
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
