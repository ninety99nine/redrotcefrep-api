<?php

namespace App\Http\Requests\Models\Page;

use App\Models\Page;
use App\Traits\Base\BaseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreatePageRequest extends FormRequest
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
            'title' => [
                'bail', 'required', 'string', 'min:'.Page::TITLE_MIN_CHARACTERS, 'max:'.Page::TITLE_MAX_CHARACTERS,
                Rule::unique('pages')->where('store_id', request()->input('store_id'))
            ],
            'homepage' => ['bail', 'sometimes', 'boolean'],
            'visible' => ['bail', 'sometimes', 'boolean'],
            'background_color' => ['bail', 'nullable', 'string', 'size:7'],
            'store_id' => ['required', 'uuid'],
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
