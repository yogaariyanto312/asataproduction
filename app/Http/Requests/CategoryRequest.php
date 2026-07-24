<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isPrivileged();
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name'        => ['required', 'string', 'max:100'],
            'code'        => ['nullable', 'string', 'max:20', Rule::unique('categories', 'code')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.max'      => 'Nama kategori maksimal 100 karakter.',
            'code.unique'   => 'Kode kategori sudah digunakan.',
            'code.max'      => 'Kode kategori maksimal 20 karakter.',
        ];
    }
}
