@extends('layouts.app')

@section('title', 'Barang Pengganti')
@section('page-title', 'Barang Pengganti / Repair')
@section('page-subtitle', 'Catatan barang pengganti (mis. rusak saat pengiriman)')

@section('content')
@php
    $role = auth()->user()->role;
    $canCreate = \App\Support\MenuAccess::can(auth()->user(), 'barang-pengganti.create');
    $canDelete = \App\Support\MenuAccess::can(auth()->user(), 'barang-pengganti.delete');
    $canInput  = $canCreate || $canDelete; // tampilkan kolom Aksi / modal bila salah satu ada
@endphp
<div class="space-y-5">

    {{-- Filter + tombol tambah --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <div class="flex flex-col lg:flex-row lg:items-end gap-4">
            <form method="GET" action="{{ route('replacements.index') }}" class="grid grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                <div class="col-span-2 lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Cari</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Produk, penerima, alasan, atau seri..."
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
                    <a href="{{ route('replacements.index') }}"
                       class="px-4 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition">Reset</a>
                    @endif
                </div>
            </form>

            @if($canCreate)
            <button type="button" onclick="document.getElementById('modal-replacement').classList.remove('hidden')"
                    class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white
                           bg-blue-600 hover:bg-blue-700 rounded-xl shadow-lg shadow-blue-900/20 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Pengganti
            </button>
            @endif
        </div>
    </div>

    {{-- Tabel --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-400 dark:text-slate-500 border-b border-slate-200 dark:border-slate-700">
                    <tr class="text-[11px] uppercase tracking-wide">
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left font-semibold">Produk</th>
                        <th class="px-4 py-3 text-center font-semibold">Qty</th>
                        <th class="px-4 py-3 text-left font-semibold">Penerima</th>
                        <th class="px-4 py-3 text-left font-semibold">Alasan</th>
                        <th class="px-4 py-3 text-left font-semibold">No. Unit Asli</th>
                        <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Diinput</th>
                        <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Status</th>
                        @if($canInput)<th class="px-4 py-3 text-center font-semibold">Aksi</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($replacements as $r)
                    <tr class="group align-top hover:bg-slate-50 dark:hover:bg-slate-900/40 transition {{ $r->keterangan ? 'border-b-0' : '' }}">
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} whitespace-nowrap text-slate-600 dark:text-slate-300">
                            {{ $r->replacement_date->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} leading-tight">
                            <div class="font-semibold text-slate-800 dark:text-white">{{ optional($r->product)->name ?? '—' }}</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">
                                {{ optional($r->product)->series }}{{ optional($r->product)->kva ? ' · ' . $r->product->kva . ' KVA' : '' }}
                            </div>
                        </td>
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} text-center">
                            <span class="inline-flex items-center justify-center min-w-7 px-2 py-0.5 rounded-md text-xs
                                         bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 font-bold">
                                {{ $r->qty }}
                            </span>
                        </td>
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} text-slate-600 dark:text-slate-300">{{ $r->recipient ?: '—' }}</td>
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} text-slate-600 dark:text-slate-300 max-w-xs">{{ $r->reason ?: '—' }}</td>
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }}">
                            @if($r->original_serial)
                            <div class="flex flex-wrap gap-1">
                                @foreach(preg_split('/\s*,\s*/', trim($r->original_serial), -1, PREG_SPLIT_NO_EMPTY) as $serial)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded font-mono text-[10px]
                                             bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-300">{{ $serial }}</span>
                                @endforeach
                            </div>
                            @else
                            <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} whitespace-nowrap text-[11px] text-slate-400">{{ $r->operator_name ?? optional($r->user)->name }}</td>
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} text-center whitespace-nowrap">
                            @php $canToggle = auth()->id() === $r->user_id || auth()->user()->isPrivileged(); @endphp
                            @if($canToggle)
                            <form method="POST" action="{{ route('replacements.toggle-complete', $r) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold transition
                                               {{ $r->is_completed
                                                    ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-900/50'
                                                    : 'bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}"
                                        title="{{ $r->is_completed ? 'Tandai belum selesai' : 'Tandai selesai' }}">
                                    @if($r->is_completed)
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Selesai
                                    @else
                                    <span class="w-3.5 h-3.5 rounded-full border-2 border-current opacity-60"></span>
                                    Belum
                                    @endif
                                </button>
                            </form>
                            @else
                            @if($r->is_completed)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold
                                         bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                Selesai
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold
                                         bg-slate-100 dark:bg-slate-700/60 text-slate-400 dark:text-slate-400">
                                <span class="w-3.5 h-3.5 rounded-full border-2 border-current opacity-60"></span>
                                Belum
                            </span>
                            @endif
                            @endif
                        </td>
                        @if($canInput)
                        <td class="px-4 pt-3 {{ $r->keterangan ? 'pb-1' : 'pb-3' }} text-center">
                            @if($canDelete)
                            <form method="POST" action="{{ route('replacements.destroy', $r) }}" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-red-500 transition" title="Hapus"
                                        data-confirm="Hapus data barang pengganti '{{ optional($r->product)->name }}' ({{ $r->qty }} unit)?"
                                        data-confirm-title="Hapus Barang Pengganti">
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
                    @if($r->keterangan)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                        <td class="pl-4"></td>
                        <td colspan="{{ $canInput ? 8 : 7 }}" class="pr-4 pb-3 pt-0">
                            <div class="flex gap-2 rounded-lg bg-slate-50 dark:bg-slate-900/50 border-l-2 border-slate-300 dark:border-slate-600 px-3 py-2 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                <span class="font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 shrink-0">Ket</span>
                                <span>{{ $r->keterangan }}</span>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="{{ $canInput ? 9 : 8 }}" class="px-4 py-12 text-center text-slate-400">
                            Belum ada data barang pengganti.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($replacements->hasPages())
    <div>{{ $replacements->links() }}</div>
    @endif
</div>

@if($canCreate)
{{-- Modal Tambah --}}
<div id="modal-replacement" class="hidden fixed inset-0 z-50 p-4">
    <div class="absolute inset-0 bg-black/60"
         onclick="document.getElementById('modal-replacement').classList.add('hidden')"></div>

    <div class="relative flex min-h-full items-center justify-center">
    <div class="relative w-full max-w-3xl max-h-[92vh] flex flex-col bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden">
        {{-- Header gradient --}}
        <div class="flex items-center justify-between px-6 py-4 bg-linear-to-r from-blue-600 to-blue-700 shrink-0">
            <div>
                <h2 class="text-base font-bold text-white leading-tight">Tambah Barang Pengganti</h2>
                <p class="text-blue-100 text-xs mt-0.5">Catat unit pengganti / repair</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-replacement').classList.add('hidden')"
                    class="p-1.5 text-white/80 hover:text-white hover:bg-white/15 rounded-lg transition-colors" title="Tutup">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('replacements.store') }}" class="p-6 overflow-y-auto">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">

                {{-- Produk — full width (field utama) --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Produk <span class="text-red-500">*</span>
                    </label>
                    <select id="replacement-product" name="product_id" required
                            class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                   border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Produk --</option>
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

                {{-- Tanggal --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Tanggal <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="replacement_date" value="{{ old('replacement_date', now()->toDateString()) }}" required
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Jumlah Unit --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Jumlah Unit <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="qty" value="{{ old('qty', 1) }}" min="1" max="9999" required
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Penerima --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Penerima</label>
                    <input type="text" name="recipient" value="{{ old('recipient') }}" maxlength="150" placeholder="Nama / tujuan"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Nomor Urut Asli --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nomor Urut / Seri Unit Asli</label>
                    <input type="text" name="original_serial" value="{{ old('original_serial') }}" maxlength="150"
                           placeholder="mis. NO.475 (opsional)"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Alasan — full width --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Alasan / Penyebab</label>
                    <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255"
                           placeholder="mis. kecelakaan saat pengiriman, rusak, dll."
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
                <button type="button" onclick="document.getElementById('modal-replacement').classList.add('hidden')"
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

@if($errors->any() || old('product_id'))
<script>
    // Buka kembali modal bila ada error validasi
    document.getElementById('modal-replacement')?.classList.remove('hidden');
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
    new TomSelect('#replacement-product', {
        placeholder: '-- Pilih Produk --',
        searchField: ['text'],
        maxOptions: null,
        onChange() {
            document.getElementById('replacement-product').dispatchEvent(new Event('change'));
        }
    });
}());
</script>
@endpush
@endif
@endsection
