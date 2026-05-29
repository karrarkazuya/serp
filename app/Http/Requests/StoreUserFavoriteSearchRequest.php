<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserFavoriteSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'model_class'  => ['required', 'string', 'max:255'],
            'name'         => ['required', 'string', 'max:200'],
            'query_string' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
