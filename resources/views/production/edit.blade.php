@extends('layouts.app')

@section('title', 'Edit Data Produksi')
@section('page-title', 'Edit Data Produksi')
@section('page-subtitle', 'Ubah data produksi yang sudah diinput')

@section('content')
<div class="max-w-2xl lg:max-w-4xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-5">
            <h2 class="text-lg font-bold text-white">Edit Data Produksi</h2>
            <p class="text-amber-100 text-sm mt-1">
                {{ $productionLog->product->name ?? '' }} — {{ $productionLog->production_date->format('d/m/Y') }}
            </p>
        </div>

        <form method="POST" action="{{ route('production.update', $productionLog) }}" class="p-6 space-y-6">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-2 lg:gap-x-8 gap-y-5 lg:items-start">

                {{-- 1. Tanggal -- kiri atas --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Tanggal Produksi <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="production_date"
                           value="{{ old('production_date', $productionLog->production_date->toDateString()) }}"
                           max="{{ today()->toDateString() }}"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border {{ $errors->has('production_date') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                  focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('production_date')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- 2. Produk -- kanan atas (desktop); di bawah tanggal (mobile) --}}
                <div class="lg:col-start-2 lg:row-start-1">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Produk <span class="text-red-500">*</span>
                    </label>
                    <select id="product_id" name="product_id"
                            class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                   border {{ $errors->has('product_id') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                   focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                            <option value="{{ $product->id }}"
                                    data-type="{{ $product->type }}"
                                    data-manual="{{ $isPlaceholder ? '1' : '0' }}"
                                    {{ old('product_id', $productionLog->product_id) == $product->id ? 'selected' : '' }}>
                                {{ $isPlaceholder ? 'Seri & KVA Manual' . $typeAbbr . ' → ' . $typeTag : (($product->series ?: '—') . ($product->kva ? " · {$product->kva} KVA" : '')) }}
                            </option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                    @error('product_id')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- 3. Operator + Qty -- kiri bawah --}}
                <div class="space-y-5 lg:col-start-1 lg:row-start-2">

                    {{-- Nama Operator --}}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nama Operator</label>
                        <input type="text" name="operator_name"
                               value="{{ old('operator_name', $productionLog->operator_name) }}"
                               placeholder="Nama operator yang bertugas..."
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border {{ $errors->has('operator_name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    {{-- Channel UP + BT --}}
                    <div id="section-channel" class="hidden space-y-4">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                            Jumlah Channel <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-2 text-center">Channel UP</label>
                                <input type="number" id="channel_up" name="shift1_qty"
                                       value="{{ old('shift1_qty', $productionLog->shift1_qty) }}"
                                       min="0" max="9999"
                                       class="w-full px-4 py-4 text-center text-xl font-bold rounded-xl
                                              bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white
                                              border border-slate-300 dark:border-slate-600
                                              focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-2 text-center">Channel BT</label>
                                <input type="number" id="channel_bt" name="shift2_qty"
                                       value="{{ old('shift2_qty', $productionLog->shift2_qty) }}"
                                       min="0" max="9999"
                                       class="w-full px-4 py-4 text-center text-xl font-bold rounded-xl
                                              bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white
                                              border border-slate-300 dark:border-slate-600
                                              focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                            <p class="text-xs text-amber-600 dark:text-amber-400 mb-2 font-medium">Total otomatis: (UP + BT) ÷ 2</p>
                            <p id="channel-total-display" class="text-3xl font-black text-amber-700 dark:text-amber-300 text-center">0</p>
                            <input type="hidden" id="total_qty_channel" name="total_qty" value="{{ old('total_qty', $productionLog->total_qty) }}">
                        </div>
                    </div>

                    {{-- Total Manual --}}
                    <div id="section-regular">
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                            <label for="total_qty_regular" class="block text-sm font-semibold text-amber-700 dark:text-amber-300 mb-2">
                                Total Unit <span class="text-red-500">*</span>
                                <span class="ml-1 text-xs font-normal text-amber-500">(isi manual)</span>
                            </label>
                            <input type="number" id="total_qty_regular" name="total_qty"
                                   value="{{ old('total_qty', $productionLog->total_qty) }}"
                                   min="0" max="99999" step="any"
                                   class="w-full px-4 py-4 text-center text-3xl font-black rounded-xl
                                          bg-white dark:bg-slate-900 text-amber-700 dark:text-amber-300
                                          border-2 {{ $errors->has('total_qty') ? 'border-red-500' : 'border-amber-300 dark:border-amber-700' }}
                                          focus:outline-none focus:ring-2 focus:ring-amber-500">
                            @error('total_qty')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </div>{{-- /qty group --}}

                {{-- 4. Nomor Urut & Manual Serial -- kanan bawah --}}
                <div class="space-y-5 lg:col-start-2 lg:row-start-2">

                    {{-- Manual Seri & KVA --}}
                    <div id="section-manual-serial" class="hidden">
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 space-y-3">
                            <p class="text-sm font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                Identitas Produk <span class="text-red-500 font-normal text-xs">(wajib diisi)</span>
                            </p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                        Nomor Seri <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="manual_series" id="manual_series"
                                           value="{{ old('manual_series', $productionLog->manual_series) }}" maxlength="100"
                                           placeholder="Contoh: A-1234 / 2026.001"
                                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                  border {{ $errors->has('manual_series') ? 'border-red-500' : 'border-amber-300 dark:border-amber-700' }}
                                                  focus:outline-none focus:ring-2 focus:ring-amber-400 font-mono text-sm">
                                    @error('manual_series')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                        KVA <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="manual_kva" id="manual_kva"
                                           value="{{ old('manual_kva', $productionLog->manual_kva) }}" maxlength="50"
                                           placeholder="Contoh: 100 / 2x50 / 250"
                                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                  border {{ $errors->has('manual_kva') ? 'border-red-500' : 'border-amber-300 dark:border-amber-700' }}
                                                  focus:outline-none focus:ring-2 focus:ring-amber-400 font-mono text-sm">
                                    @error('manual_kva')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Nomor Urut - Regular --}}
                    <div id="section-notes-regular">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nomor Urut</label>
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <label class="block text-xs text-slate-400 mb-1">Nomor Awal</label>
                                <input type="number" id="no_awal_regular" min="1" placeholder="Contoh: 98"
                                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                              border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                            <div class="pt-4 text-slate-400 font-bold">→</div>
                            <div class="flex-1">
                                <label class="block text-xs text-slate-400 mb-1">Preview</label>
                                <div class="px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono text-sm text-amber-600 dark:text-amber-400 min-h-12">
                                    <span id="preview-regular">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Nomor Urut - Channel --}}
                    <div id="section-notes-channel" class="hidden">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nomor Urut</label>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-2 text-center">Nomor Awal UP</label>
                                <input type="number" id="no_awal_up" min="1" placeholder="Contoh: 435"
                                       class="w-full px-4 py-3 text-center text-lg font-bold rounded-xl
                                              bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white
                                              border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <p id="preview-up" class="mt-2 text-xs text-center font-mono text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg px-2 py-1.5 min-h-7">—</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-2 text-center">Nomor Awal BT</label>
                                <input type="number" id="no_awal_bt" min="1" placeholder="Contoh: 438"
                                       class="w-full px-4 py-3 text-center text-lg font-bold rounded-xl
                                              bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white
                                              border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <p id="preview-bt" class="mt-2 text-xs text-center font-mono text-purple-500 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 rounded-lg px-2 py-1.5 min-h-7">—</p>
                            </div>
                        </div>
                    </div>

                    {{-- Nilai saat ini (edit mode) --}}
                    @if(old('notes', $productionLog->notes))
                    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl px-4 py-3 border border-slate-200 dark:border-slate-700">
                        <p class="text-xs text-slate-400 mb-1">Tersimpan saat ini:</p>
                        <p class="text-sm font-mono text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ old('notes', $productionLog->notes) }}</p>
                    </div>
                    @endif

                </div>{{-- /nomor urut group --}}

            </div>{{-- /Grid 2-kolom --}}

            <input type="hidden" id="notes" name="notes" value="{{ old('notes', $productionLog->notes) }}">

            {{-- Reject / Defect --}}
            @php $hasReject = old('reject_qty', $productionLog->reject_qty) > 0; @endphp
            <div x-data="{ open: {{ $hasReject ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open"
                        class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 hover:text-red-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span x-text="open ? 'Sembunyikan Reject' : 'Ada Reject / Defect?'"></span>
                    <svg class="w-4 h-4 ml-auto transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-xl p-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Jumlah Reject</label>
                            <input type="number" name="reject_qty" min="0" max="9999"
                                   value="{{ old('reject_qty', $productionLog->reject_qty) }}"
                                   class="w-full px-4 py-3 text-center text-xl font-bold rounded-xl
                                          bg-white dark:bg-slate-900 text-red-600 dark:text-red-400
                                          border border-red-300 dark:border-red-700 focus:outline-none focus:ring-2 focus:ring-red-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Kategori Penyebab</label>
                            <select name="reject_category"
                                    class="w-full px-3 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                           border border-red-300 dark:border-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 text-sm">
                                <option value="">-- Pilih Kategori --</option>
                                @foreach(\App\Models\ProductionLog::$rejectCategories as $key => $label)
                                <option value="{{ $key }}" {{ old('reject_category', $productionLog->reject_category) == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Keterangan Reject</label>
                        <input type="text" name="reject_notes" maxlength="300"
                               value="{{ old('reject_notes', $productionLog->reject_notes) }}"
                               placeholder="Deskripsi singkat penyebab reject..."
                               class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border border-red-300 dark:border-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 text-sm">
                    </div>
                </div>
            </div>

            {{-- Keterangan --}}
            <div>
                <label for="keterangan" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Keterangan
                    <span class="ml-1 text-xs font-normal text-slate-400">(opsional)</span>
                </label>
                <textarea id="keterangan" name="keterangan" rows="2"
                          placeholder="Catatan tambahan, misal: Barang dari supplier, Lupa input, dll..."
                          class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                 border {{ $errors->has('keterangan') ? 'border-red-500' : 'border-amber-300 dark:border-amber-700' }}
                                 focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none">{{ old('keterangan', $productionLog->keterangan) }}</textarea>
                @error('keterangan')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('production.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-semibold transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const productSelect        = document.getElementById('product_id');
    const sectionChannel       = document.getElementById('section-channel');
    const sectionRegular       = document.getElementById('section-regular');
    const sectionNotesChannel  = document.getElementById('section-notes-channel');
    const sectionNotesRegular  = document.getElementById('section-notes-regular');
    const sectionManualSerial  = document.getElementById('section-manual-serial');
    const manualSeriesInput    = document.getElementById('manual_series');
    const manualKvaInput       = document.getElementById('manual_kva');
    const channelUp            = document.getElementById('channel_up');
    const channelBt            = document.getElementById('channel_bt');
    const channelDisplay       = document.getElementById('channel-total-display');
    const totalHidden          = document.getElementById('total_qty_channel');
    const totalRegular         = document.getElementById('total_qty_regular');
    const noAwalRegular        = document.getElementById('no_awal_regular');
    const noAwalUp             = document.getElementById('no_awal_up');
    const noAwalBt             = document.getElementById('no_awal_bt');
    const previewRegular       = document.getElementById('preview-regular');
    const previewUp            = document.getElementById('preview-up');
    const previewBt            = document.getElementById('preview-bt');
    const notesHidden          = document.getElementById('notes');

    function pad(n) {
        const s = String(Math.round(n));
        return s.length < 3 ? s.padStart(3, '0') : s;
    }

    function generateRegular() {
        const start = parseInt(noAwalRegular.value);
        const qty   = Math.ceil(parseFloat(totalRegular.value) || 0);
        if (!start || qty <= 0) {
            previewRegular.textContent = '—';
            // Jangan hapus notes tersimpan bila nomor awal belum diisi (mode edit)
            return;
        }
        const text = `NO.${pad(start)}-${pad(start + qty - 1)}`;
        previewRegular.textContent = text;
        notesHidden.value = text;
    }

    function generateChannel() {
        const up    = parseInt(channelUp.value) || 0;
        const bt    = parseInt(channelBt.value) || 0;
        const total = up + bt;
        const startUp = parseInt(noAwalUp.value);
        const startBt = parseInt(noAwalBt.value);

        previewUp.textContent = (startUp && up > 0)
            ? `UP NO.${pad(startUp)}-${pad(startUp + up - 1)}` : '—';
        previewBt.textContent = (startBt && bt > 0)
            ? `BT NO.${pad(startBt)}-${pad(startBt + bt - 1)}` : '—';

        const lines = [previewUp.textContent, previewBt.textContent].filter(t => t !== '—');
        if (lines.length) notesHidden.value = lines.join('\n');
    }

    function calcChannel() {
        const up    = parseInt(channelUp.value) || 0;
        const bt    = parseInt(channelBt.value) || 0;
        const total = (up + bt) / 2;
        channelDisplay.textContent = total % 1 === 0 ? total.toLocaleString('id-ID') : total.toFixed(1);
        totalHidden.value = total;
        generateChannel();
    }

    function getSelected() {
        const opt = productSelect.options[productSelect.selectedIndex];
        return {
            type:   opt ? (opt.dataset.type   || 'regular') : 'regular',
            manual: opt ? (opt.dataset.manual  === '1')     : false,
        };
    }

    function switchMode({ type, manual }) {
        const isChannel = type === 'channel';
        sectionChannel.classList.toggle('hidden', !isChannel);
        sectionRegular.classList.toggle('hidden', isChannel);
        sectionNotesChannel.classList.toggle('hidden', !isChannel || manual);
        sectionNotesRegular.classList.toggle('hidden', isChannel);
        sectionManualSerial.classList.toggle('hidden', !manual);
        totalRegular.disabled = isChannel;
        totalHidden.disabled  = !isChannel;
        channelUp.disabled    = !isChannel;
        channelBt.disabled    = !isChannel;
        if (manualSeriesInput) manualSeriesInput.required = manual;
        if (manualKvaInput)    manualKvaInput.required    = manual;
        if (isChannel) calcChannel(); else generateRegular();
    }

    productSelect.addEventListener('change', () => switchMode(getSelected()));
    channelUp.addEventListener('input', calcChannel);
    channelBt.addEventListener('input', calcChannel);
    totalRegular.addEventListener('input', generateRegular);
    noAwalRegular.addEventListener('input', generateRegular);
    noAwalUp.addEventListener('input', generateChannel);
    noAwalBt.addEventListener('input', generateChannel);

    // Scroll wheel untuk naikkan/turunkan angka
    function wheelStep(el, e) {
        e.preventDefault();
        const step = parseFloat(el.step) || 1;
        const min  = el.min !== '' ? parseFloat(el.min) : -Infinity;
        const max  = el.max !== '' ? parseFloat(el.max) :  Infinity;
        const cur  = parseFloat(el.value) || 0;
        el.value   = Math.min(max, Math.max(min, cur + (e.deltaY < 0 ? step : -step)));
        el.dispatchEvent(new Event('input'));
    }
    [totalRegular, channelUp, channelBt, noAwalRegular, noAwalUp, noAwalBt].forEach(el => {
        el.addEventListener('wheel', e => wheelStep(el, e), { passive: false });
    });

    // ── Pre-fill Nomor Awal dari notes tersimpan agar tidak hilang saat edit ──
    (function prefillFromNotes() {
        const raw = (notesHidden.value || '').trim();
        if (!raw) return;
        const lines = raw.split('\n').map(l => l.trim()).filter(Boolean);
        // Ambil angka pertama setelah "NO." (mis. "UP NO.477-482" → 477)
        const startOf = (line) => {
            const m = line.match(/NO\.\s*0*(\d+)/i);
            return m ? parseInt(m[1]) : null;
        };
        const upLine  = lines.find(l => /UP/i.test(l));
        const btLine  = lines.find(l => /BT/i.test(l));
        const regLine = lines.find(l => !/UP|BT/i.test(l));

        if (upLine && !noAwalUp.value)  { const s = startOf(upLine);  if (s) noAwalUp.value  = s; }
        if (btLine && !noAwalBt.value)  { const s = startOf(btLine);  if (s) noAwalBt.value  = s; }
        if (regLine && !noAwalRegular.value) { const s = startOf(regLine); if (s) noAwalRegular.value = s; }
    }());

    switchMode(getSelected());

    // ── Cegah double-submit (penyebab data/total ganda di riwayat) ───────────
    const form = productSelect.closest('form');
    if (form) {
        let submitted = false;
        form.addEventListener('submit', function (e) {
            if (submitted) { e.preventDefault(); return; }
            submitted = true;
            const btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.classList.add('opacity-60', 'cursor-not-allowed'); }
        });
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            submitted = false;
            const btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = false; btn.classList.remove('opacity-60', 'cursor-not-allowed'); }
        });
    }
}());
</script>
@endsection
