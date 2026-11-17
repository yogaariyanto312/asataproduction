{{--
    Kalender agenda pada dashboard panel.

    Gaya ditulis di sini (bukan kelas utilitas Tailwind) karena CSS panel Filament
    dikompilasi dari berkasnya sendiri — kelas utilitas yang hanya dipakai di view
    ini tidak akan ikut terbawa ke bundel.
--}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kalender &amp; Agenda</x-slot>
        <x-slot name="description">{{ $label }}</x-slot>

        {{-- Slot bawaan Filament v5 untuk isi di samping judul seksi. --}}
        <x-slot name="afterHeader">
            <div class="ak-nav">
                <button type="button" wire:click="bulanSebelumnya" title="Bulan lalu">&#8249;</button>
                @unless ($iniBulanBerjalan)
                    <button type="button" wire:click="bulanIni" class="ak-nav-kini">Bulan ini</button>
                @endunless
                <button type="button" wire:click="bulanBerikutnya" title="Bulan depan">&#8250;</button>
            </div>
        </x-slot>

        <div class="ak" wire:loading.class="ak--memuat">
            <div class="ak-bulan">
            <div class="ak-hari">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $i => $nama)
                    <span @class(['ak-akhir' => $i >= 5])>{{ $nama }}</span>
                @endforeach
            </div>

            <div class="ak-grid">
                @foreach ($sel as $item)
                    @if ($item === null)
                        <span class="ak-sel ak-sel--kosong"></span>
                    @else
                        <span
                            @class([
                                'ak-sel',
                                'ak-sel--kini' => $item['iniHari'],
                                'ak-sel--merah' => ! $item['iniHari'] && ($item['akhirPekan'] || $item['libur']),
                            ])
                            @if ($item['libur']) title="{{ $item['libur'] }}" @endif
                        >
                            {{ $item['hari'] }}
                            @if (count($item['agenda']))
                                <i class="ak-titik"></i>
                            @endif
                        </span>
                    @endif
                @endforeach
            </div>

            <div class="ak-legenda">
                <span><i class="ak-kotak ak-kotak--kini"></i> Hari ini</span>
                <span><i class="ak-kotak ak-kotak--merah"></i> Libur / akhir pekan</span>
                <span><i class="ak-kotak ak-kotak--agenda"></i> Ada agenda</span>
            </div>
            </div>

            <div class="ak-daftar">
                @forelse ($agenda as $tanggal => $isi)
                    <div class="ak-baris">
                        <span class="ak-tanggal">
                            {{ \Carbon\Carbon::parse($tanggal)->locale('id')->isoFormat('ddd, D MMM') }}
                        </span>
                        <span class="ak-isi">
                            @foreach ($isi as $acara)
                                <span class="ak-acara">
                                    <b>{{ $acara->title }}</b>
                                    @if ($acara->description)
                                        <i>{{ \Illuminate\Support\Str::limit($acara->description, 70) }}</i>
                                    @endif
                                </span>
                            @endforeach
                        </span>
                    </div>
                @empty
                    <p class="ak-kosong">Tidak ada agenda pada bulan ini.</p>
                @endforelse
            </div>
        </div>

        <style>
            .ak { font-size: 12px; display: grid; gap: 22px; grid-template-columns: minmax(0, 320px) minmax(0, 1fr); align-items: start; }
            @media (max-width: 900px) { .ak { grid-template-columns: minmax(0, 1fr); } }
            .ak--memuat { opacity: .5; transition: opacity .15s; }

            .ak-nav { display: flex; align-items: center; gap: 4px; }
            .ak-nav button {
                min-width: 28px; height: 28px; padding: 0 8px;
                border-radius: 8px; border: 1px solid rgb(148 163 184 / .25);
                background: rgb(148 163 184 / .10); color: inherit;
                font-size: 14px; font-weight: 700; cursor: pointer; line-height: 1;
            }
            .ak-nav button:hover { background: rgb(148 163 184 / .22); }
            .ak-nav-kini { font-size: 11px !important; font-weight: 600 !important; }

            .ak-hari, .ak-grid {
                display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px;
            }
            .ak-hari {
                margin-bottom: 6px; text-align: center;
                font-size: 10px; font-weight: 700; letter-spacing: .06em;
                text-transform: uppercase; opacity: .65;
            }
            .ak-hari .ak-akhir { color: rgb(248 113 113); opacity: 1; }

            .ak-sel {
                position: relative; display: grid; place-items: center;
                width: 34px; height: 34px; margin: 0 auto; border-radius: 999px;
                font-size: 12px; font-weight: 600;
            }
            .ak-sel--kosong { visibility: hidden; }
            .ak-sel--merah { color: rgb(248 113 113); }
            .ak-sel--kini { background: rgb(37 99 235); color: #fff; font-weight: 800; }

            .ak-titik {
                position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%);
                width: 4px; height: 4px; border-radius: 999px; background: rgb(52 211 153);
            }
            .ak-sel--kini .ak-titik { background: #fff; }

            .ak-legenda {
                display: flex; flex-wrap: wrap; gap: 14px;
                margin-top: 14px; font-size: 10.5px; opacity: .7;
            }
            .ak-legenda span { display: inline-flex; align-items: center; gap: 5px; }
            .ak-kotak { width: 8px; height: 8px; border-radius: 999px; display: inline-block; }
            .ak-kotak--kini { background: rgb(37 99 235); }
            .ak-kotak--merah { background: rgb(248 113 113); }
            .ak-kotak--agenda { background: rgb(52 211 153); }

            .ak-daftar { border-top: 1px solid rgb(148 163 184 / .18); }
            .ak-baris {
                display: flex; gap: 12px; padding: 8px 0;
                border-bottom: 1px solid rgb(148 163 184 / .10);
            }
            .ak-tanggal { flex: none; width: 96px; font-weight: 700; opacity: .8; }
            .ak-isi { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
            .ak-acara b { font-weight: 700; }
            .ak-acara i { display: block; font-style: normal; font-size: 11px; opacity: .65; }
            .ak-kosong { padding: 12px 0; opacity: .6; }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>
