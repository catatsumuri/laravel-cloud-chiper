<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreChirpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isMethod('patch')) {
            return [
                'message' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'message' => ['nullable', 'required_without:attachments', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:4'],
            'attachments.*' => [
                File::image()->max(10240),
            ],
        ];
    }
}
