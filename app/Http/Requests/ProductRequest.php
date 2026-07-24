<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isPrivileged();
    }

    public function rules(): array
    {
        return [
            'category_id'  => ['required', 'exists:categories,id'],
            'type'         => ['required', 'in:regular,channel'],
            'name'         => ['required', 'string', 'max:150'],
            'series'       => ['nullable', 'string', 'max:100'],
            'kva'          => ['nullable', 'string', 'max:20'],
            'tahun'        => ['nullable', 'integer', 'min:2025', 'max:' . (now()->year + 5)],
            'panjang'      => ['nullable', 'string', 'max:50'],
            'lebar'        => ['nullable', 'string', 'max:50'],
            'unit'         => ['required', 'string', 'max:20'],
            'description'  => ['nullable', 'string', 'max:500'],
            'is_active'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists'   => 'Kategori tidak valid.',
            'name.required'        => 'Nama produk wajib diisi.',
            'name.max'             => 'Nama produk maksimal 150 karakter.',
            'series.max'           => 'Seri produk maksimal 100 karakter.',
            'unit.required'        => 'Satuan wajib diisi.',
        ];
    }
}
