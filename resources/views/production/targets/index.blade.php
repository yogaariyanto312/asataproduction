@extends('layouts.app')

@section('title', 'Target Produksi')
@section('page-title', 'Target Produksi')
@section('page-subtitle', 'Set & pantau target produksi per produk')

@section('content')
@php
    $canSetTarget    = \App\Support\MenuAccess::can(auth()->user(), 'targets.edit');
    $canDeleteTarget = \App\Support\MenuAccess::can(auth()->user(), 'targets.delete');
@endphp
<div class="space-y-5">

    {{-- Flash --}}
    @if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-700 dark:text-green-400">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-center gap-3 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-400">
        {{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 {{ $canSetTarget ? 'lg:grid-cols-3' : '' }} gap-5">

        {{-- ── Kolom kiri: Form tambah target ── --}}
        @if($canSetTarget)
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden sticky top-4">
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-5 py-4">
                    <h3 class="text-base font-bold text-white">Set Target</h3>
                    <p class="text-green-100 text-xs mt-0.5">Target produksi per produk · tanpa batas waktu</p>
                </div>
                <form method="POST" action="{{ route('production.targets.store') }}" class="p-5 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Produk</label>
                        <select id="tgt-product-select" name="product_id"
                                class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-600
                                       bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                       focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">-- Pilih Produk --</option>
                            @foreach($products->groupBy('name') as $groupName => $groupItems)
                            <optgroup label="{{ $groupName }}">
                                @foreach($groupItems as $product)
                                @php
                                    $isPlaceholder = $product->category && $product->category->has_manual_serial && !$product->series;
                                    if ($isPlaceholder) {
                                        $nl = strtolower($product->name);
                                        $typeTag = str_contains($nl, 'swasta') ? 'Swasta' : (str_contains($nl, 'type') ? 'TypeTest' : 'PLN');
                                    }
                                @endphp
                                <option value="{{ $product->id }}"
                                        data-manual="{{ $isPlaceholder ? '1' : '0' }}"
                                        data-type="{{ $product->type }}"
                                        {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $isPlaceholder ? 'Seri & KVA Manual → ' . $typeTag : (($product->series ?: '—') . ($product->kva ? ' · ' . $product->kva . ' KVA' : '')) }}
                                </option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                        @error('product_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror

                        {{-- Manual Seri & KVA --}}
                        <div id="tgt-manual-section" class="hidden mt-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3 space-y-3">
                            <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">Identitas Produk <span class="text-red-500 font-normal">(wajib diisi)</span></p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Nomor Seri</label>
                                    <input type="text" name="manual_series" id="tgt-manual-series"
                                           value="{{ old('manual_series') }}" maxlength="100"
                                           placeholder="Contoh: A-1234"
                                           class="w-full px-3 py-2 text-sm rounded-xl border border-amber-300 dark:border-amber-700
                                                  bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                  focus:outline-none focus:ring-2 focus:ring-amber-400 font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">KVA</label>
                                    <input type="text" name="manual_kva" id="tgt-manual-kva"
                                           value="{{ old('manual_kva') }}" maxlength="50"
                                           placeholder="Contoh: 100"
                                           class="w-full px-3 py-2 text-sm rounded-xl border border-amber-300 dark:border-amber-700
                                                  bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                  focus:outline-none focus:ring-2 focus:ring-amber-400 font-mono">
                                </div>
                            </div>
                        </div>
                        <script>
                        (function(){
                            const sel = document.getElementById('tgt-product-select');
                            const sec = document.getElementById('tgt-manual-section');
                            const ser = document.getElementById('tgt-manual-series');
                            const kva = document.getElementById('tgt-manual-kva');
                            function toggle() {
                                const opt = sel.options[sel.selectedIndex];
                                const isM = opt && opt.dataset.manual === '1';
                                sec.classList.toggle('hidden', !isM);
                                if (ser) ser.required = isM;
                                if (kva) kva.required = isM;
                            }
                            sel.addEventListener('change', toggle);
                            toggle();
                        }());
                        </script>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Target Unit</label>

                        {{-- Mode toggle --}}
                        <div class="flex rounded-xl border border-slate-300 dark:border-slate-600 overflow-hidden mb-3 text-xs font-semibold">
                            <button type="button" id="tgt-mode-qty"
                                    onclick="setTargetMode('qty')"
                                    class="flex-1 py-2 transition-colors bg-green-600 text-white">
                                Jumlah Unit
                            </button>
                            <button type="button" id="tgt-mode-serial"
                                    onclick="setTargetMode('serial')"
                                    class="flex-1 py-2 transition-colors bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400">
                                Sampai No. Urut
                            </button>
                        </div>

                        {{-- Mode 1: Jumlah Unit --}}
                        <div id="tgt-section-qty">
                            {{-- Sub: regular (non-channel) --}}
                            <div id="tgt-qty-regular">
                                <input type="number" id="tgt-qty-input" name="target_qty" min="1" max="999999"
                                       value="{{ old('target_qty') }}"
                                       placeholder="Contoh: 500"
                                       class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                              focus:outline-none focus:ring-2 focus:ring-green-500 text-center font-bold text-lg">
                                <p class="text-[10px] text-slate-400 mt-1 text-center">Total unit yang harus diproduksi</p>
                            </div>

                            {{-- Sub: channel (UP + BT) --}}
                            <div id="tgt-qty-channel" class="hidden space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-blue-500 font-semibold mb-1 text-center">Channel UP</label>
                                        <input type="number" id="tgt-channel-up" min="0" max="9999"
                                               placeholder="Contoh: 100"
                                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                                      bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                      focus:outline-none focus:ring-2 focus:ring-blue-500 text-center font-bold text-lg">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-purple-500 font-semibold mb-1 text-center">Channel BT</label>
                                        <input type="number" id="tgt-channel-bt" min="0" max="9999"
                                               placeholder="Contoh: 100"
                                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                                      bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                      focus:outline-none focus:ring-2 focus:ring-purple-500 text-center font-bold text-lg">
                                    </div>
                                </div>
                                <div id="tgt-channel-preview" class="hidden px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl text-center">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Total target <span class="text-[10px]">(UP + BT) ÷ 2</span></p>
                                    <p id="tgt-channel-total" class="text-lg font-black text-blue-600 dark:text-blue-400">0</p>
                                    <input type="hidden" id="tgt-channel-qty" name="target_qty" value="">
                                </div>
                            </div>
                        </div>

                        {{-- Mode 2: Sampai No. Urut --}}
                        <div id="tgt-section-serial" class="hidden space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] text-slate-400 mb-1">
                                        Dari No. Urut
                                        <span id="tgt-serial-from-hint" class="text-green-500 hidden">(otomatis)</span>
                                    </label>
                                    <input type="number" id="tgt-serial-from" min="1" max="999999"
                                           placeholder="Auto dari riwayat"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600
                                                  bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                  focus:outline-none focus:ring-2 focus:ring-green-500 text-center font-bold">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-400 mb-1">Sampai No. Urut</label>
                                    <input type="number" id="tgt-serial-to" min="1" max="999999"
                                           placeholder="Contoh: 500"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600
                                                  bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                                  focus:outline-none focus:ring-2 focus:ring-green-500 text-center font-bold">
                                </div>
                            </div>
                            <input type="hidden" id="tgt-serial-qty" name="target_qty" value="">
                            <div id="tgt-serial-preview"
                                 class="hidden px-3 py-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-center">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Total target</p>
                                <p id="tgt-serial-result" class="text-lg font-black text-green-600 dark:text-green-400">0 unit</p>
                                <p class="text-[10px] text-slate-400">No. Urut <span id="tgt-serial-range">-</span></p>
                            </div>
                        </div>

                        @error('target_qty')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <script>
                    function updateSerialCalc() {
                        const from      = parseInt(document.getElementById('tgt-serial-from').value) || 0;
                        const to        = parseInt(document.getElementById('tgt-serial-to').value)   || 0;
                        const preview   = document.getElementById('tgt-serial-preview');
                        const hiddenQty = document.getElementById('tgt-serial-qty');

                        // "Dari" = no. urut terakhir yg sudah selesai, jadi qty = sampai - dari
                        if (from >= 0 && to > 0 && to > from) {
                            const qty = to - from;
                            hiddenQty.value = qty;
                            document.getElementById('tgt-serial-result').textContent = qty.toLocaleString('id-ID') + ' unit';
                            document.getElementById('tgt-serial-range').textContent  = from + ' → ' + to;
                            preview.classList.remove('hidden');
                        } else {
                            hiddenQty.value = '';
                            preview.classList.add('hidden');
                        }
                    }

                    async function autoFillSerialFrom() {
                        const productId = document.getElementById('tgt-product-select').value;
                        const fromInput = document.getElementById('tgt-serial-from');
                        const toInput   = document.getElementById('tgt-serial-to');
                        const hint      = document.getElementById('tgt-serial-from-hint');
                        if (!productId) return;
                        try {
                            const r = await fetch(`{{ route('api.targets.actual-qty') }}?product_id=${productId}`);
                            if (!r.ok) return;
                            const d = await r.json();
                            const dari = d.actual ?? 0;
                            fromInput.value = dari;
                            hint.classList.remove('hidden');

                            // Auto-fill sampai jika ada target yang sudah diset hari ini
                            if (d.target_qty && !toInput.value) {
                                toInput.value = dari + d.target_qty;
                            }

                            updateSerialCalc();
                        } catch {}
                    }

                    function isChannelSelected() {
                        const opt = document.getElementById('tgt-product-select').options[
                            document.getElementById('tgt-product-select').selectedIndex
                        ];
                        return opt && opt.dataset.type === 'channel';
                    }

                    function calcChannelTarget() {
                        const up  = parseFloat(document.getElementById('tgt-channel-up').value)  || 0;
                        const bt  = parseFloat(document.getElementById('tgt-channel-bt').value)  || 0;
                        const total = (up + bt) / 2;
                        const preview = document.getElementById('tgt-channel-preview');
                        const hiddenQty = document.getElementById('tgt-channel-qty');

                        if (up > 0 || bt > 0) {
                            hiddenQty.value = total;
                            document.getElementById('tgt-channel-total').textContent =
                                (total % 1 === 0 ? total.toLocaleString('id-ID') : total.toFixed(1)) + ' unit';
                            preview.classList.remove('hidden');
                        } else {
                            hiddenQty.value = '';
                            preview.classList.add('hidden');
                        }
                    }

                    function applyProductType() {
                        const isChannel = isChannelSelected();
                        document.getElementById('tgt-qty-regular').classList.toggle('hidden', isChannel);
                        document.getElementById('tgt-qty-channel').classList.toggle('hidden', !isChannel);
                        document.getElementById('tgt-qty-input').name    = isChannel ? '' : 'target_qty';
                        document.getElementById('tgt-channel-qty').name  = isChannel ? 'target_qty' : '';

                        // Sembunyikan "Sampai No. Urut" untuk channel
                        document.getElementById('tgt-mode-serial').classList.toggle('hidden', isChannel);
                        if (isChannel) {
                            setTargetMode('qty');
                        }
                    }

                    function setTargetMode(mode) {
                        const isSerial  = mode === 'serial';
                        const isChannel = isChannelSelected();

                        document.getElementById('tgt-section-qty').classList.toggle('hidden', isSerial);
                        document.getElementById('tgt-section-serial').classList.toggle('hidden', !isSerial);

                        // Pastikan nama field benar sesuai mode & tipe produk
                        document.getElementById('tgt-qty-input').name    = (!isSerial && !isChannel) ? 'target_qty' : '';
                        document.getElementById('tgt-channel-qty').name  = (!isSerial && isChannel)  ? 'target_qty' : '';
                        document.getElementById('tgt-serial-qty').name   = isSerial ? 'target_qty' : '';

                        document.getElementById('tgt-mode-qty').className =
                            'flex-1 py-2 transition-colors ' + (!isSerial ? 'bg-green-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400');
                        document.getElementById('tgt-mode-serial').className =
                            'flex-1 py-2 transition-colors ' + (isSerial ? 'bg-green-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400');

                        if (isSerial) autoFillSerialFrom();
                    }

                    document.getElementById('tgt-channel-up').addEventListener('input', calcChannelTarget);
                    document.getElementById('tgt-channel-bt').addEventListener('input', calcChannelTarget);

                    document.getElementById('tgt-serial-from').addEventListener('input', updateSerialCalc);
                    document.getElementById('tgt-serial-to').addEventListener('input', updateSerialCalc);

                    document.getElementById('tgt-product-select').addEventListener('change', function () {
                        applyProductType();
                        if (!document.getElementById('tgt-section-serial').classList.contains('hidden')) {
                            autoFillSerialFrom();
                        }
                    });
                    </script>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Catatan <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input type="text" name="notes" maxlength="200" value="{{ old('notes') }}"
                               placeholder="Misal: Target khusus akhir bulan"
                               class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-600
                                      bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <button type="submit"
                            class="w-full py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        Simpan Target
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- ── Kolom kanan: Tabel target & progres ── --}}
        <div class="{{ $canSetTarget ? 'lg:col-span-2' : '' }} space-y-4">

            {{-- Foto Jadwal --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Foto Jadwal</h3>
                        <p class="text-xs text-slate-400">Minggu {{ \Carbon\Carbon::parse($weekStart)->translatedFormat('d M') }} – {{ \Carbon\Carbon::parse($weekEnd)->translatedFormat('d M Y') }}</p>
                    </div>
                    @if($schedulePhoto && ($canSetTarget || $canDeleteTarget))
                    <div class="flex items-center gap-3">
                        {{-- Ganti --}}
                        @if($canSetTarget)
                        <form method="POST" action="{{ route('production.targets.schedule-photo.store') }}"
                              enctype="multipart/form-data" id="replacePhotoForm">
                            @csrf
                            <input type="hidden" name="target_date" value="{{ $scheduleDate }}">
                            <label class="flex items-center gap-1.5 text-xs text-blue-500 hover:text-blue-700 cursor-pointer font-medium">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Ganti
                                <input type="file" name="photo" accept="image/*,.pdf" class="hidden"
                                       onchange="showUploadOverlay(); document.getElementById('replacePhotoForm').submit();">
                            </label>
                        </form>
                        @endif
                        {{-- Hapus --}}
                        @if($canDeleteTarget)
                        <form method="POST" action="{{ route('production.targets.schedule-photo.destroy') }}">
                            @csrf @method('DELETE')
                            <input type="hidden" name="target_date" value="{{ $scheduleDate }}">
                            <button type="submit"
                                    data-confirm="Hapus foto jadwal minggu ini?"
                                    class="flex items-center gap-1.5 text-xs text-red-400 hover:text-red-600 font-medium">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Hapus
                            </button>
                        </form>
                        @endif
                    </div>
                    @endif
                </div>

                @if($schedulePhoto)
                @php $isPdf = strtolower(pathinfo($schedulePhoto->file_path, PATHINFO_EXTENSION)) === 'pdf'; @endphp
                <div class="p-3">
                    @if($isPdf)
                    {{-- PDF embed --}}
                    <iframe src="{{ route('storage.file', $schedulePhoto->file_path) }}"
                            class="w-full border-0 rounded-xl"
                            style="height: 60vh;"
                            title="Jadwal {{ $date }}">
                        <div class="p-6 text-center">
                            <p class="text-sm text-slate-500 mb-3">Browser tidak mendukung preview PDF.</p>
                            <a href="{{ route('storage.file', $schedulePhoto->file_path) }}" target="_blank"
                               class="px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700">
                                Buka PDF
                            </a>
                        </div>
                    </iframe>
                    @else
                    {{-- Image preview --}}
                    <a href="{{ route('storage.file', $schedulePhoto->file_path) }}" target="_blank"
                       class="block group relative overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-900">
                        <img src="{{ route('storage.file', $schedulePhoto->file_path) }}"
                             alt="Jadwal {{ $date }}"
                             class="w-full object-contain max-h-64 rounded-xl group-hover:opacity-90 transition-opacity cursor-zoom-in">
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="bg-black/50 text-white text-xs px-3 py-1.5 rounded-full font-medium">Buka penuh</span>
                        </div>
                    </a>
                    @endif
                    <p class="text-[10px] text-slate-400 text-center mt-2">
                        Diupload oleh {{ $schedulePhoto->uploader->name ?? '-' }} · {{ $schedulePhoto->updated_at->diffForHumans() }}
                    </p>
                </div>
                @elseif($canSetTarget)
                {{-- Upload area --}}
                <div class="p-4">
                    <form method="POST" action="{{ route('production.targets.schedule-photo.store') }}"
                          enctype="multipart/form-data" id="uploadPhotoForm">
                        @csrf
                        <input type="hidden" name="target_date" value="{{ $scheduleDate }}">
                        <label for="photoInput"
                               class="flex flex-col items-center justify-center gap-2 py-6 px-4
                                      border-2 border-dashed border-slate-200 dark:border-slate-600
                                      rounded-xl cursor-pointer transition-all
                                      hover:border-green-400 hover:bg-green-50 dark:hover:bg-green-900/10">
                            <div class="w-10 h-10 bg-slate-100 dark:bg-slate-700 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Upload jadwal</p>
                                <p class="text-xs text-slate-400 mt-0.5">JPG, PNG, WebP, PDF — maks 8 MB</p>
                            </div>
                            <input type="file" id="photoInput" name="photo" accept="image/*,.pdf" class="hidden"
                                   onchange="showUploadOverlay(); document.getElementById('uploadPhotoForm').submit();">
                        </label>
                    </form>
                    @error('photo')<p class="mt-2 text-xs text-red-500 text-center">{{ $message }}</p>@enderror
                </div>
                @else
                <div class="p-6 text-center text-sm text-slate-400">Belum ada foto jadwal untuk minggu ini.</div>
                @endif
            </div>

            {{-- Summary card --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-100 dark:border-slate-700 text-center">
                    <p class="text-2xl font-black text-slate-800 dark:text-white">{{ number_format($totalTarget) }}</p>
                    <p class="text-xs text-slate-500 mt-1">Total Target</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-100 dark:border-slate-700 text-center">
                    <p id="tgt-stat-actual" class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ number_format($totalActual) }}</p>
                    <p class="text-xs text-slate-500 mt-1">Total Aktual</p>
                </div>
                <div class="rounded-xl p-4 text-center
                    {{ $totalTarget > 0 && $totalActual >= $totalTarget ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800' }}">
                    @php $overallPct = $totalTarget > 0 ? min(round(($totalActual / $totalTarget) * 100), 100) : 0; @endphp
                    <p id="tgt-stat-pct" class="text-2xl font-black {{ $overallPct >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $overallPct }}%
                    </p>
                    <p class="text-xs text-slate-500 mt-1">Pencapaian</p>
                </div>
            </div>

            {{-- Tabel target --}}
            @if($targets->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 p-12 text-center">
                <p class="text-slate-400 text-sm">Belum ada target aktif.</p>
                <p class="text-slate-300 dark:text-slate-600 text-xs mt-1">Set target menggunakan form di sebelah kiri.</p>
            </div>
            @else
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($targets as $target)
                    @php
                        $actual  = $actuals[$target->product_id] ?? 0;
                        $pct     = $target->target_qty > 0 ? min(round(($actual / $target->target_qty) * 100), 100) : 0;
                        $done    = $actual >= $target->target_qty;
                        $barColor = $done ? 'bg-green-500' : ($pct >= 70 ? 'bg-amber-400' : 'bg-blue-500');
                    @endphp
                    <div class="px-5 py-4" data-pid="{{ $target->product_id }}">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ $target->product->name ?? '-' }}</p>
                                @if($target->product?->series_with_kva)
                                <p class="text-xs text-slate-400 font-mono">{{ $target->product->series_with_kva }}</p>
                                @endif
                                @if($target->notes)
                                <p class="text-xs text-slate-400 italic mt-0.5">{{ $target->notes }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="text-right">
                                    <p id="tgt-actual-{{ $target->product_id }}"
                                       class="text-sm font-bold {{ $done ? 'text-green-600 dark:text-green-400' : 'text-slate-800 dark:text-white' }}">
                                        {{ number_format($actual) }} / {{ number_format($target->target_qty) }}
                                    </p>
                                    <p class="text-xs text-slate-400">aktual / target</p>
                                </div>
                                <span id="tgt-pct-{{ $target->product_id }}"
                                      class="text-sm font-black {{ $done ? 'text-green-600' : ($pct >= 70 ? 'text-amber-500' : 'text-blue-600') }} dark:opacity-90 w-12 text-right">
                                    {{ $pct }}%
                                </span>
                                @allowedTo('targets.delete')
                                <form method="POST" action="{{ route('production.targets.destroy', $target->id) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            data-confirm="Hapus target {{ $target->product->name }}?"
                                            class="p-1 text-slate-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endallowedTo
                            </div>
                        </div>
                        {{-- Progress bar --}}
                        <div class="h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div id="tgt-bar-{{ $target->product_id }}"
                                 class="{{ $barColor }} h-full rounded-full transition-all duration-500"
                                 style="width: {{ $pct }}%"></div>
                        </div>
                        @if($done)
                        <p id="tgt-label-{{ $target->product_id }}" class="text-[10px] text-green-600 dark:text-green-400 mt-1 font-semibold">
                            ✓ Target tercapai!
                            @if($target->reached_at)
                            <span class="text-slate-400 font-normal">· terhapus otomatis {{ $target->reached_at->copy()->addHours(2)->diffForHumans() }}</span>
                            @endif
                        </p>
                        @else
                        <p id="tgt-label-{{ $target->product_id }}" class="text-[10px] text-slate-400 mt-1">Kurang {{ number_format($target->target_qty - $actual) }} unit lagi</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Upload Loading Overlay --}}
<div id="uploadOverlay" style="display:none"
     class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-white dark:bg-slate-800 rounded-2xl px-10 py-8 flex flex-col items-center gap-4 shadow-2xl mx-4">
        <div class="w-14 h-14 rounded-full border-[5px] border-blue-100 dark:border-slate-600 border-t-blue-500 animate-spin"></div>
        <div class="text-center">
            <p class="text-sm font-semibold text-slate-800 dark:text-white">Sedang mengupload...</p>
            <p class="text-xs text-slate-400 mt-1">Mohon tunggu, jangan tutup halaman</p>
        </div>
    </div>
</div>

{{-- Auto Refresh Toggle --}}
<div id="tgtRefreshBtn"
     class="fixed bottom-6 right-6 z-50 hidden sm:flex items-center gap-2 px-3 py-2 rounded-full shadow-lg cursor-pointer select-none
            bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:shadow-xl transition-all"
     title="Toggle auto-refresh">
    <span id="tgtDot" class="w-2 h-2 rounded-full bg-green-500 pulse-dot shrink-0"></span>
    <span id="tgtLabel" class="text-xs font-semibold text-slate-700 dark:text-slate-200">Live</span>
    <span id="tgtCountdown" class="text-xs text-slate-400">30s</span>
    <span id="tgtUpdatedAt" class="hidden text-[10px] text-slate-400 sm:inline"></span>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css">
<style>
.ts-wrapper.full.focus .ts-control,
.ts-wrapper.full .ts-control { box-shadow: none; }
.ts-control {
    background: #1e293b !important;
    border-color: #475569 !important;
    border-radius: 0.75rem !important;
    padding: 0.625rem 0.75rem !important;
    color: #f1f5f9 !important;
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
}
.ts-dropdown .option { padding: 8px 12px !important; }
.ts-dropdown .option:hover,
.ts-dropdown .option.active { background: #16a34a !important; color: #fff !important; }
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
    const el = document.getElementById('tgt-product-select');
    if (!el || typeof TomSelect === 'undefined') return;
    new TomSelect(el, {
        placeholder: '-- Pilih Produk --',
        searchField: ['text'],
        maxOptions: null,
        // Teruskan event change asli supaya toggle Seri & KVA Manual tetap jalan
        onChange() { el.dispatchEvent(new Event('change')); }
    });
}());
</script>
<script>
const TGT_DATE     = '{{ $date }}';
const TGT_INTERVAL = 60;
let tgtEnabled  = localStorage.getItem('tgtAutoRefresh') !== 'false';
let tgtCdVal    = TGT_INTERVAL;

// Clear timers from previous SPA eval context before creating new ones
clearInterval(window._tgtTimer);
clearInterval(window._tgtCdTimer);
let tgtTimer   = null;
let tgtCdTimer = null;

function tgtBarColor(pct) {
    return pct >= 100 ? '#22c55e' : pct >= 70 ? '#f59e0b' : '#3b82f6';
}
function tgtPctClass(pct) {
    return pct >= 100 ? 'text-green-600' : pct >= 70 ? 'text-amber-500' : 'text-blue-600';
}
function fmtNum(n) {
    return Math.round(n).toLocaleString('id-ID');
}

async function fetchTargetLive() {
    try {
        const r = await fetch(`{{ route('api.targets.live') }}?date=${TGT_DATE}`);
        if (!r.ok) return;
        const d = await r.json();

        // Summary cards
        const sa = document.getElementById('tgt-stat-actual');
        if (sa) sa.textContent = fmtNum(d.total_actual);

        const sp = document.getElementById('tgt-stat-pct');
        if (sp) {
            sp.textContent = d.overall_pct + '%';
            sp.className = sp.className.replace(/text-(green|amber|slate)-\d+/g, '');
            sp.classList.add(d.overall_pct >= 100 ? 'text-green-600' : 'text-amber-600', 'dark:opacity-90');
        }

        // Per-product rows
        for (const [pid, info] of Object.entries(d.actuals)) {
            const actualEl = document.getElementById(`tgt-actual-${pid}`);
            if (actualEl) {
                actualEl.textContent = `${fmtNum(info.actual)} / ${fmtNum(info.target)}`;
                actualEl.className = actualEl.className.replace(/text-(green|slate)-\d+/g, '').trim();
                actualEl.classList.add(info.done ? 'text-green-600' : 'text-slate-800');
            }

            const pctEl = document.getElementById(`tgt-pct-${pid}`);
            if (pctEl) {
                pctEl.textContent = info.pct + '%';
                pctEl.className = pctEl.className.replace(/text-(green|amber|blue)-\d+/g, '').trim();
                pctEl.classList.add(tgtPctClass(info.pct));
            }

            const barEl = document.getElementById(`tgt-bar-${pid}`);
            if (barEl) {
                barEl.style.width = info.pct + '%';
                barEl.style.backgroundColor = tgtBarColor(info.pct);
            }

            const labelEl = document.getElementById(`tgt-label-${pid}`);
            if (labelEl) {
                if (info.done) {
                    labelEl.textContent = '✓ Target tercapai!';
                    labelEl.className = 'text-[10px] text-green-600 dark:text-green-400 mt-1 font-semibold';
                } else {
                    labelEl.textContent = `Kurang ${fmtNum(info.remaining)} unit lagi`;
                    labelEl.className = 'text-[10px] text-slate-400 mt-1';
                }
            }
        }

        const ua = document.getElementById('tgtUpdatedAt');
        if (ua) { ua.textContent = ' · ' + d.updated_at; ua.classList.remove('hidden'); }
    } catch {}
}

function tgtStart() {
    tgtCdVal = TGT_INTERVAL;
    fetchTargetLive();
    tgtTimer   = window._tgtTimer   = setInterval(fetchTargetLive, TGT_INTERVAL * 1000);
    tgtCdTimer = window._tgtCdTimer = setInterval(() => {
        tgtCdVal--;
        if (tgtCdVal <= 0) tgtCdVal = TGT_INTERVAL;
        const el = document.getElementById('tgtCountdown');
        if (el) el.textContent = tgtCdVal + 's';
    }, 1000);
    tgtSetUI(true);
}

function tgtStop() {
    clearInterval(tgtTimer); clearInterval(tgtCdTimer);
    tgtTimer = window._tgtTimer = null; tgtCdTimer = window._tgtCdTimer = null;
    tgtSetUI(false);
}

function tgtSetUI(on) {
    const dot   = document.getElementById('tgtDot');
    const label = document.getElementById('tgtLabel');
    const count = document.getElementById('tgtCountdown');
    if (dot)   { dot.classList.toggle('bg-green-500', on); dot.classList.toggle('pulse-dot', on); dot.classList.toggle('bg-slate-400', !on); }
    if (label) label.textContent = on ? 'Live' : 'Paused';
    if (count) { count.textContent = TGT_INTERVAL + 's'; count.classList.toggle('hidden', !on); }
}

document.getElementById('tgtRefreshBtn')?.addEventListener('click', () => {
    tgtEnabled = !tgtEnabled;
    localStorage.setItem('tgtAutoRefresh', tgtEnabled);
    tgtEnabled ? tgtStart() : tgtStop();
});

tgtEnabled ? tgtStart() : tgtSetUI(false);

document.addEventListener('spa:leave', function() {
    clearInterval(window._tgtTimer);
    clearInterval(window._tgtCdTimer);
    window._tgtTimer = null;
    window._tgtCdTimer = null;
}, { once: true });

function showUploadOverlay() {
    var el = document.getElementById('uploadOverlay');
    if (el) el.style.display = 'flex';
}
</script>
@endpush
