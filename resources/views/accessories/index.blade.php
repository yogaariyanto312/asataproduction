@extends('layouts.app')

@section('title', 'Aksesoris Keluar')
@section('page-title', 'Aksesoris Keluar')
@section('page-subtitle', 'Catatan aksesoris yang keluar dari produksi')

@section('content')
@php
    $role = auth()->user()->role;
    $canCreate = \App\Support\MenuAccess::can(auth()->user(), 'aksesoris.create');
    $canDelete = \App\Support\MenuAccess::can(auth()->user(), 'aksesoris.delete');
    $canInput  = $canCreate || $canDelete;
@endphp
<div class="space-y-5">

    {{-- Filter + tombol tambah --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <div class="flex flex-col lg:flex-row lg:items-end gap-4">
            <form method="GET" action="{{ route('accessories.index') }}" class="grid grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                <div class="col-span-2 lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Cari</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Nama aksesoris, seri, penerima, tujuan..."
                           class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                  bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Bulan</label>
                    <select name="month"
                            class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                   bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Bulan</option>
                        @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ (string) request('month') === (string) $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Tahun</label>
                    <select name="year"
                            class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                   bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Tahun</option>
                        @foreach($years as $yr)
                        <option value="{{ $yr }}" {{ (string) request('year') === (string) $yr ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                @if($departments->isNotEmpty())
                <div class="col-span-2 lg:col-span-4">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Departemen</label>
                    <select name="department" onchange="this.form.submit()"
                            class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                   bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Departemen</option>
                        @foreach($departments as $d)
                        <option value="{{ $d }}" {{ $deptFilter === $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-span-2 lg:col-span-4 flex gap-2">
                    <button type="submit"
                            class="px-4 py-2.5 text-sm font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200
                                   rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition">Filter</button>
                    @if(request()->hasAny(['search', 'month', 'year', 'department']))
                    <a href="{{ route('accessories.index') }}"
                       class="px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition">Reset</a>
                    @endif
                </div>
            </form>

            @if($canCreate)
            <button type="button" onclick="document.getElementById('modal-accessory').classList.remove('hidden')"
                    class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white
                           bg-blue-600 hover:bg-blue-700 rounded-xl shadow-lg shadow-blue-900/20 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Aksesoris
            </button>
            @endif
        </div>
    </div>

    {{-- Tabel --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-400 dark:text-slate-500">
                    <tr class="text-[11px] uppercase tracking-wide">
                        <th class="px-3 py-2 text-left font-semibold">Tanggal</th>
                        <th class="px-3 py-2 text-left font-semibold">Aksesoris</th>
                        <th class="px-3 py-2 text-left font-semibold">Seri Terkait</th>
                        <th class="px-3 py-2 text-left font-semibold">No. Urut</th>
                        <th class="px-3 py-2 text-center font-semibold">Jumlah</th>
                        <th class="px-3 py-2 text-left font-semibold">Penerima</th>
                        <th class="px-3 py-2 text-left font-semibold">Tujuan</th>
                        <th class="px-3 py-2 text-left font-semibold">Diinput</th>
                        @if($canInput)<th class="px-3 py-2 text-center font-semibold">Aksi</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($accessories as $a)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition {{ $a->keterangan ? 'border-b-0' : '' }}">
                        <td class="px-3 py-1.5 whitespace-nowrap text-slate-600 dark:text-slate-300">
                            {{ $a->accessory_date->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-3 py-1.5">
                            <div class="font-semibold text-slate-800 dark:text-white">{{ $a->name }}</div>
                        </td>
                        <td class="px-3 py-1.5 leading-tight">
                            @if($a->product)
                            <div class="text-slate-600 dark:text-slate-300">{{ $a->product->series ?: $a->product->name }}</div>
                            <div class="text-[11px] text-slate-400">{{ $a->product->kva ? $a->product->kva . ' KVA' : '' }}</div>
                            @else
                            <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $a->serial_number ?: '—' }}</td>
                        <td class="px-3 py-1.5 text-center whitespace-nowrap">
                            <span class="inline-flex items-center justify-center min-w-7 px-1.5 py-0.5 rounded-md text-xs
                                         bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 font-bold">
                                {{ $a->qty }}
                            </span>
                            <span class="text-[11px] text-slate-400 ml-1">{{ $a->unit }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-slate-600 dark:text-slate-300">{{ $a->recipient ?: '—' }}</td>
                        <td class="px-3 py-1.5 text-slate-600 dark:text-slate-300">{{ $a->purpose ?: '—' }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap text-[11px] text-slate-400">{{ $a->operator_name ?? optional($a->user)->name }}</td>
                        @if($canInput)
                        <td class="px-3 py-1.5 text-center">
                            @if($canDelete)
                            <form method="POST" action="{{ route('accessories.destroy', $a) }}" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-red-500 transition" title="Hapus"
                                        data-confirm="Hapus data aksesoris '{{ $a->name }}' ({{ $a->qty }} {{ $a->unit }})?"
                                        data-confirm-title="Hapus Aksesoris">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @else
                            <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @if($a->keterangan)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                        <td colspan="{{ $canInput ? 9 : 8 }}" class="px-3 pb-1.5 pt-0 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                            <span class="font-medium text-slate-400 dark:text-slate-500">Ket:</span> {{ $a->keterangan }}
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="{{ $canInput ? 9 : 8 }}" class="px-3 py-10 text-center text-slate-400">
                            Belum ada data aksesoris keluar.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($accessories->hasPages())
    <div>{{ $accessories->links() }}</div>
    @endif
</div>

@if($canCreate)
{{-- Modal Tambah --}}
<div id="modal-accessory" class="hidden fixed inset-0 z-50 p-4">
    <div class="absolute inset-0 bg-black/60"
         onclick="document.getElementById('modal-accessory').classList.add('hidden')"></div>

    <div class="relative flex min-h-full items-center justify-center">
    <div class="relative w-full max-w-3xl max-h-[92vh] flex flex-col bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden">
        {{-- Header gradient --}}
        <div class="flex items-center justify-between px-6 py-4 bg-linear-to-r from-blue-600 to-blue-700 shrink-0">
            <div>
                <h2 class="text-base font-bold text-white leading-tight">Tambah Aksesoris Keluar</h2>
                <p class="text-blue-100 text-xs mt-0.5">Catat aksesoris yang keluar dari produksi</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-accessory').classList.add('hidden')"
                    class="p-1.5 text-white/80 hover:text-white hover:bg-white/15 rounded-lg transition-colors" title="Tutup">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('accessories.store') }}" class="p-6 overflow-y-auto">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">

                {{-- Nama aksesoris — full width (field utama) --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Nama / Jenis Aksesoris <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required
                           placeholder="mis. Box Panel, Tahanan CP, End plate, dll."
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border {{ $errors->has('name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                  focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Seri produk terkait --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Seri Produk Terkait <span class="text-slate-400 font-normal">(opsional)</span>
                    </label>
                    <select id="accessory-product" name="product_id"
                            class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                   border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Tanpa seri (aksesoris lepas) --</option>
                        @foreach($products->groupBy('name') as $groupName => $groupItems)
                        <optgroup label="{{ $groupName }}">
                            @foreach($groupItems as $product)
                            @php
                                $isPlaceholder = $product->category && $product->category->has_manual_serial && !$product->series;
                                if ($isPlaceholder) {
                                    $nl = strtolower($product->name);
                                    $typeTag  = str_contains($nl, 'swasta') ? 'Swasta' : (str_contains($nl, 'type') ? 'TypeTest' : 'PLN');
                                    $catName  = strtolower($product->category->name ?? '');
                                    $typeAbbr = match(true) {
                                        $product->type === 'channel'           => ' CH',
                                        str_contains($catName, 'cover')        => ' CV',
                                        str_contains($catName, 'tangki')       => ' TK',
                                        default                                => '',
                                    };
                                }
                            @endphp
                            <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $isPlaceholder ? 'Seri & KVA Manual' . $typeAbbr . ' → ' . $typeTag : (($product->series ?: '—') . ($product->kva ? ' · ' . $product->kva . ' KVA' : '')) }}
                            </option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                </div>

                {{-- Nomor urut aksesoris --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Nomor Urut Aksesoris <span class="text-slate-400 font-normal">(opsional)</span>
                    </label>
                    <input type="text" name="serial_number" value="{{ old('serial_number') }}" maxlength="150"
                           placeholder="mis. NO.512 (kosongkan jika lepas)"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Tanggal --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Tanggal <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="accessory_date" value="{{ old('accessory_date', now()->toDateString()) }}" required
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Jumlah + Satuan --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            Jumlah <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="qty" value="{{ old('qty', 1) }}" min="1" max="99999" required
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Satuan</label>
                        <input type="text" name="unit" value="{{ old('unit', 'pcs') }}" maxlength="30" placeholder="pcs, set, kg"
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                {{-- Penerima --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Penerima</label>
                    <input type="text" name="recipient" value="{{ old('recipient') }}" maxlength="150" placeholder="Nama / tujuan kirim"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Tujuan --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tujuan / Keperluan</label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}" maxlength="255" placeholder="mis. kelengkapan unit"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Keterangan — full width --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Keterangan <span class="text-slate-400 font-normal">(opsional)</span></label>
                    <textarea name="keterangan" rows="2" maxlength="1000"
                              class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                     border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('keterangan') }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-6">
                <button type="button" onclick="document.getElementById('modal-accessory').classList.add('hidden')"
                        class="flex-1 px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-200
                               bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-3 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700
                               rounded-xl shadow-lg shadow-blue-900/20 transition">
                    Simpan
                </button>
            </div>
        </form>
    </div>
    </div>
</div>

@if($errors->any() || old('name') || old('product_id'))
<script>
    // Buka kembali modal bila ada error validasi
    document.getElementById('modal-accessory')?.classList.remove('hidden');
</script>
@endif

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css">
<style>
.ts-wrapper.full.focus .ts-control,
.ts-wrapper.full .ts-control { box-shadow: none; }
.ts-control {
    background: var(--ts-bg, #1e293b) !important;
    border-color: var(--ts-border, #475569) !important;
    border-radius: 0.75rem !important;
    padding: 0.75rem 1rem !important;
    color: var(--ts-color, #f1f5f9) !important;
    font-size: 0.875rem !important;
    min-height: unset !important;
}
.ts-dropdown {
    background: #1e293b !important;
    border-color: #475569 !important;
    border-radius: 0.75rem !important;
    margin-top: 4px !important;
    color: #f1f5f9 !important;
    font-size: 0.875rem !important;
    display: block !important;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px) scale(0.98);
    transform-origin: top center;
    pointer-events: none;
    transition: opacity 0.16s ease, transform 0.16s ease, visibility 0.16s ease;
}
.ts-wrapper.dropdown-active .ts-dropdown {
    opacity: 1;
    visibility: visible;
    transform: none;
    pointer-events: auto;
}
.ts-dropdown .option { padding: 8px 12px !important; }
.ts-dropdown .option:hover,
.ts-dropdown .option.active { background: #3b82f6 !important; color: #fff !important; }
.ts-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 0.7rem !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    padding: 8px 12px 4px !important;
    color: #94a3b8 !important;
    background: #0f172a !important;
}
.ts-dropdown-content { max-height: 300px !important; }
.ts-wrapper .ts-control input { color: #f1f5f9 !important; }
.ts-wrapper .ts-control input::placeholder { color: #64748b !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    new TomSelect('#accessory-product', {
        placeholder: '-- Tanpa seri (aksesoris lepas) --',
        searchField: ['text'],
        maxOptions: null,
        allowEmptyOption: true,
        onChange() {
            document.getElementById('accessory-product').dispatchEvent(new Event('change'));
        }
    });
}());
</script>
@endpush
@endif
@endsection
