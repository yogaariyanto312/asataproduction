<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Kolom UP/BT di database bertipe NOT NULL DEFAULT 0, sementara form boleh
     * dikosongkan. Field kosong diubah jadi null oleh middleware bawaan dan
     * ditolak MySQL ("cannot be null"). Hanya kunci yang dikirim form yang
     * dinormalkan, supaya kolom yang tidak dikirim tidak tertimpa 0.
     */
    protected function prepareForValidation(): void
    {
        foreach (['up_qty', 'bt_qty', 'reject_qty'] as $key) {
            if ($this->has($key) && in_array($this->input($key), [null, ''], true)) {
                $this->merge([$key => 0]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'product_id'      => ['required', 'exists:products,id'],
            'production_date' => ['required', 'date', 'before_or_equal:today'],
            'operator_name'   => ['nullable', 'string', 'max:150'],
            'up_qty'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'bt_qty'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'total_qty'       => ['required', 'numeric', 'min:0', 'max:99999'],
            'notes'           => ['nullable', 'string', 'max:500'],
            'manual_series'   => ['nullable', 'string', 'max:100'],
            'manual_kva'      => ['nullable', 'string', 'max:50'],
            'keterangan'      => ['nullable', 'string', 'max:500'],
            'reject_qty'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'reject_category' => ['nullable', 'in:material,mesin,human_error,desain,lainnya'],
            'reject_notes'    => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required'             => 'Produk wajib dipilih.',
            'product_id.exists'               => 'Produk tidak valid.',
            'production_date.required'        => 'Tanggal produksi wajib diisi.',
            'production_date.date'            => 'Format tanggal tidak valid.',
            'production_date.before_or_equal' => 'Tanggal produksi tidak boleh melebihi hari ini.',
            'total_qty.required'              => 'Total unit wajib diisi.',
            'total_qty.integer'               => 'Total unit harus berupa angka.',
            'total_qty.min'                   => 'Total unit minimal 0.',
        ];
    }
}
