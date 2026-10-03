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
            'urutan'       => ['nullable', 'integer', 'min:1', 'max:999'],
            'warna_ikon'   => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'warna_teks'   => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
            'urutan.integer'       => 'Urutan harus berupa angka.',
            'urutan.min'           => 'Urutan paling kecil 1.',
            'urutan.max'           => 'Urutan paling besar 999.',
            'warna_ikon.regex'     => 'Warna ikon harus kode hex, mis. #2563eb.',
            'warna_teks.regex'     => 'Warna teks harus kode hex, mis. #2563eb.',
        ];
    }
}
