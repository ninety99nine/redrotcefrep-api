<?php

namespace App\Http\Requests\Models\AiMessageCategory;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAiMessageCategoriesRequest extends FormRequest
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
            'ai_message_category_ids' => ['required', 'array'],
            'ai_message_category_ids.*' => ['bail', 'required', 'uuid', 'distinct']
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
