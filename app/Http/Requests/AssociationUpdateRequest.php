<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssociationUpdateRequest extends FormRequest
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
            'subdomain' => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/',
            'home_image_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'rules_file' => 'nullable|file|mimes:pdf|max:10240',
            'favicon' => 'nullable|file|mimes:zip|max:2048',
            'about' => 'nullable|string',
            'favicon_metadata' => 'nullable|string',
        ];
    }
}
