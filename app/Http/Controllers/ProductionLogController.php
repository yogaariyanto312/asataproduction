<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionLogRequest;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Services\BotNotificationService;
use App\Support\UrutanProduksi;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductionLogController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->date_from ?: null;
        $dateTo   = $request->date_to   ?: null;

        // Developer bisa menyaring per departemen (role lain sudah di-scope otomatis).
        $deptFilter = auth()->user()?->role === 'developer'
            ? (trim((string) $request->input('department')) ?: null)
            : null;

        $applyFilters = function ($q) use ($request, $dateFrom, $dateTo, $deptFilter) {
            $q->when($request->search,       fn($q) => $q->search($request->search))
              ->when($request->product_name, fn($q) => $q->whereHas('product', fn($q) => $q->where('name', $request->product_name)))
              ->when($dateFrom,              fn($q) => $q->where('production_date', '>=', $dateFrom))
              ->when($dateTo,                fn($q) => $q->where('production_date', '<=', $dateTo))
              ->when($request->month,        fn($q) => $q->whereMonth('production_date', $request->month))
              ->when($request->year,         fn($q) => $q->whereYear('production_date', $request->year))
              ->when($deptFilter,            fn($q) => $q->where('production_logs.department', $deptFilter))
;
        };

        $departments = auth()->user()?->role === 'developer'
            ? \App\Models\Department::where('is_active', true)->orderBy('name')->pluck('name')
            : collect();

        // Totals hari ini untuk summary bar
        $todayLogs  = ProductionLog::with(['product:id,category_id,type', 'product.category:id,name'])
            ->where('production_date', today()->toDateString())
            ->when($deptFilter, fn($q) => $q->where('production_logs.department', $deptFilter))
            ->get(['id', 'product_id', 'up_qty', 'bt_qty', 'total_qty']);

        $totalUp    = $todayLogs->sum('up_qty');
        $totalBt    = $todayLogs->sum('bt_qty');
        $totalTanki = $todayLogs->filter(fn($l) => str_contains(strtolower($l->product->category->name ?? ''), 'tangki'))->sum('total_qty');
        $totalCover = $todayLogs->filter(fn($l) => str_contains(strtolower($l->product->category->name ?? ''), 'cover'))->sum('total_qty');
        $grandTotal = $todayLogs->sum('total_qty');
        $totalCount = $todayLogs->count();

        // Paginate by date (7 hari per halaman) agar satu tanggal tidak terpotong
        $dates = ProductionLog::tap($applyFilters)
            ->selectRaw('DISTINCT production_date')
            ->orderByDesc('production_date')
            ->paginate(16, ['production_date'])
            ->withQueryString();

        $logs = ProductionLog::with(['product.category', 'user'])
            ->tap($applyFilters)
            ->whereIn('production_date', $dates->pluck('production_date'))
            ->orderByDesc('production_date')
            ->orderByDesc('created_at')
            ->get();

        $products   = Product::where('is_active', true)->distinct()->orderBy('name')->pluck('name');
        // Daftar tahun unik — ambil tanggal unik lalu ekstrak tahun di PHP (cross-DB: MySQL & SQLite)
        $years      = ProductionLog::query()
            ->distinct()
            ->orderByDesc('production_date')
            ->pluck('production_date')
            ->map(fn ($d) => (int) \Illuminate\Support\Carbon::parse($d)->year)
            ->unique()
            ->values();

        // Precompute nomor urut terakhir UP/BT — satu query untuk semua channel product
        $channelProductIds = $logs
            ->filter(fn($l) => ($l->product->type ?? '') === 'channel')
            ->pluck('product_id')->unique()->values();

        $lastChannelNums = [];
        if ($channelProductIds->isNotEmpty()) {
            $lastChannelNums = $this->lastChannelSerialsForMany($channelProductIds->all());
        }

        // Bentuk data mengikuti Production-QC-Logging-System: per TANGGAL, lalu
        // per KATEGORI lewat UrutanProduksi (Channel → Cover →
        // Tangki → sisanya abjad; isi kartu urut KVA lalu seri).
        // Penanda PLN / Swasta / Typetest dibaca dari nama KATEGORI, persis
        // seperti versi Blade — kategori memang bernama "Channel-PLN",
        // "Cover Swasta", "Tangki Typetest", dan seterusnya.
        $badgeOf = function ($log) {
            $cat = strtolower($log->product->category->name ?? '');
            if (str_contains($cat, 'swasta')) return 'Swasta';
            if (str_contains($cat, 'type'))   return 'Typetest';
            if (str_contains($cat, 'pln')
                || str_contains($cat, 'channel')
                || str_contains($cat, 'cover')
                || str_contains($cat, 'tangki')) return 'PLN';

            return null;
        };

        // URL per kartu dibangun dari templat sekali jalan — route() untuk tiap
        // log (4 x ratusan kartu) terasa di waktu muat halaman.
        $urlTpl = array_map(fn ($r) => route($r, '__ID__'), [
            'showUrl'   => 'production.show',
            'editUrl'   => 'production.edit',
            'deleteUrl' => 'production.destroy',
            'rejectUrl' => 'production.reject-unit',
        ]);

        $mapLog = function ($log) use ($badgeOf, $lastChannelNums, $urlTpl) {
            $isChannel = ($log->product->type ?? '') === 'channel';

            // Nomor urut UP/BT: dari catatan, jatuh ke nomor terakhir bila kosong.
            $lines = collect(explode("\n", (string) $log->notes))
                ->map(fn ($l) => trim($l))
                ->filter();
            $fallback = $isChannel ? ($lastChannelNums[$log->product_id] ?? []) : [];
            $up = $lines->first(fn ($l) => preg_match('/\bUP\b/i', $l)) ?: ($fallback['up'] ?? null);
            $bt = $lines->first(fn ($l) => preg_match('/\bBT\b/i', $l)) ?: ($fallback['bt'] ?? null);

            return [
                'id'         => $log->id,
                'name'       => trim(preg_replace('/\s+(typetest|swasta|pln)\b/i', '', $log->product->name ?? '-')),
                'seriesKva'  => $log->product->series_with_kva ?: null,
                'badge'      => $badgeOf($log),
                'isChannel'  => $isChannel,
                'up'     => (int) $log->up_qty,
                'bt'     => (int) $log->bt_qty,
                'total'      => (float) $log->total_qty,
                'reject'     => (int) $log->reject_qty,
                'rejectLabel'=> $log->reject_qty > 0 && $log->reject_category ? $log->rejectCategoryLabel() : null,
                'rejectNotes'=> $log->reject_qty > 0 ? $log->reject_notes : null,
                'serialLine' => $isChannel ? trim(($up ?? '') . ' ' . ($bt ?? '')) : null,
                'notes'      => $isChannel ? null : $log->notes,
                'keterangan' => $log->keterangan,
                'operator'   => $log->user->name ?? '-',
                ...str_replace('__ID__', (string) $log->id, $urlTpl),
                // Reject unit hanya untuk non-channel yang masih punya unit.
                'canReject'  => ! $isChannel && (float) $log->total_qty >= 1,
                'dateInput'  => $log->production_date->toDateString(),
            ];
        };

        $days = $logs
            ->groupBy(fn ($l) => $l->production_date->toDateString())
            ->map(function ($dayLogs, $dateStr) use ($mapLog) {
                $carbon = \Illuminate\Support\Carbon::parse($dateStr);

                $cats = UrutanProduksi::kelompokkan($dayLogs)
                    ->map(fn ($items, $key) => [
                        'name'  => (string) $key,
                        'total' => (float) $items->sum('total_qty'),
                        'items' => $items->map($mapLog)->values(),
                    ])
                    ->values();

                return [
                    'date'     => $dateStr,
                    'dayName'  => $carbon->locale('id')->isoFormat('dddd'),
                    'dateLabel'=> $carbon->locale('id')->isoFormat('D MMMM YYYY'),
                    'total'    => (float) $dayLogs->sum('total_qty'),
                    'count'    => $dayLogs->count(),
                    'categories' => $cats,
                ];
            })
            ->values();

        return Inertia::render('Production/Index', [
            'filters'   => $request->only(['search', 'product_name', 'date_from', 'date_to', 'month', 'year', 'department']),
            'indexUrl'  => route('production.index'),
            'createUrl' => route('production.create'),
            'canInput'  => ! auth()->user()->isSupervisor(),
            'can'       => [
                'edit'   => \App\Support\MenuAccess::can(auth()->user(), 'riwayat-produksi.edit'),
                'delete' => \App\Support\MenuAccess::can(auth()->user(), 'riwayat-produksi.delete'),
                'reject' => \App\Support\MenuAccess::can(auth()->user(), 'riwayat-produksi.reject'),
            ],
            'rejectCategories' => collect(\App\Models\ProductionLog::$rejectCategories)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'today'     => today()->toDateString(),
            'summaryLabel' => now()->locale('id')->isoFormat('D MMMM YYYY'),
            'summary'   => [
                'up'    => (int) $totalUp,
                'bt'    => (int) $totalBt,
                'tanki' => (float) $totalTanki,
                'cover' => (float) $totalCover,
                'total' => (float) $grandTotal,
                'count' => (int) $totalCount,
            ],
            'days'        => $days,
            'pagination'  => $dates,
            'products'    => $products->map(fn ($n) => ['value' => $n, 'label' => $n])->values(),
            'years'       => $years->map(fn ($y) => ['value' => $y, 'label' => (string) $y])->values(),
            'departments' => $departments->map(fn ($d) => ['value' => $d, 'label' => $d])->values(),
        ]);
    }

    public function create()
    {
        $products    = $this->dropdownProducts();
        // Developer memilih departemen tujuan; role lain otomatis ikut departemennya.
        $departments = auth()->user()?->role === 'developer'
            ? \App\Models\Department::where('is_active', true)->orderBy('name')->pluck('name')
            : collect();
        return Inertia::render('Production/Form', [
            'mode'         => 'create',
            'action'       => route('production.store'),
            'indexUrl'     => route('production.index'),
            'lastSerialUrl'=> route('api.production.last-serial'),
            'today'        => today()->toDateString(),
            'operatorName' => auth()->user()->name,
            'departments'  => $departments->map(fn ($d) => ['value' => $d, 'label' => $d])->values(),
            'products'     => $this->productOptions($products),
            'rejectCategories' => collect(\App\Models\ProductionLog::$rejectCategories)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
        ]);
    }

    public function store(ProductionLogRequest $request)
    {
        $data = $request->validated();
        $data['user_id']       = auth()->id();
        $data['operator_name'] = auth()->user()->name;
        $data['reject_qty']    = (int) ($data['reject_qty'] ?? 0);

        // Departemen data: developer memilih di form; role lain otomatis ikut
        // departemennya. Ditetapkan eksplisit di sini agar lookup merge & auto-fill
        // trait konsisten (lihat App\Models\Concerns\BelongsToDepartment).
        if (auth()->user()->role === 'developer' && \App\Models\Department::where('is_active', true)->exists()) {
            $request->validate([
                'department' => ['required', 'string', 'exists:departments,name'],
            ], [], ['department' => 'departemen tujuan']);
            $data['department'] = $request->input('department');
        } else {
            $data['department'] = auth()->user()->department;
        }

        // Backstop double-submit (klik ganda): abaikan kiriman identik dalam 10 detik terakhir.
        // Penting untuk produk channel yang akan di-MERGE (jika tidak, qty bisa berlipat).
        $dupeKey = 'prodlog_dupe_' . md5(implode('|', [
            $data['user_id'], $data['product_id'], $data['production_date'],
            $data['total_qty'] ?? '', $data['up_qty'] ?? '', $data['bt_qty'] ?? '',
            $data['manual_series'] ?? '', $data['notes'] ?? '',
        ]));
        if (\Illuminate\Support\Facades\Cache::get($dupeKey)) {
            return $this->storeResponse($request, 'Data produksi berhasil disimpan.');
        }
        \Illuminate\Support\Facades\Cache::put($dupeKey, true, now()->addSeconds(10));

        $product    = Product::with('category')->find($data['product_id']);
        $isChannel  = $product && $product->isChannel();
        $isManual   = $product && $product->category && $product->category->has_manual_serial;

        // Auto find-or-create specific product record for manual series+KVA entries
        if ($isManual && !empty($data['manual_series'])) {
            preg_match('/^(\d{2})/', $data['manual_series'], $ym);
            $tahun = isset($ym[1]) ? (2000 + (int)$ym[1]) : now()->year;

            $specificProduct = Product::firstOrCreate(
                [
                    'category_id' => $product->category_id,
                    'series'      => $data['manual_series'],
                    'kva'         => $data['manual_kva'] ?: null,
                ],
                [
                    'name'      => $product->name,
                    'type'      => $product->type,
                    'tahun'     => $tahun,
                    'is_active' => true,
                ]
            );
            $data['product_id'] = $specificProduct->id;
            $isManual = false; // product_id is now specific; no need for manual_series merge filter
        }

        if ($isChannel) {
            $up = (int) ($data['up_qty'] ?? 0);
            $bt = (int) ($data['bt_qty'] ?? 0);
            $data['total_qty']  = ($up + $bt) / 2;
        } else {
            $data['up_qty'] = $data['up_qty'] ?? 0;
            $data['bt_qty'] = $data['bt_qty'] ?? 0;
        }

        // Merge: produk sama + tanggal sama → tambahkan ke entri yang ada, jangan
        // buat baris baru. Untuk channel, UP/BT diinput terpisah. Untuk non-channel
        // (Cover, Tangki, dll.) seri manual sudah jadi product_id spesifik, jadi
        // product_id + tanggal sama berarti seri yang sama.
        // Merge harus per-departemen: produk+tanggal sama di departemen berbeda
        // adalah entri terpisah (penting untuk developer yang tak ter-scope).
        $existing = ProductionLog::where('product_id', $data['product_id'])
            ->whereDate('production_date', $data['production_date'])
            ->when($data['department'] !== null, fn($q) => $q->where('department', $data['department']))
            ->when($data['department'] === null, fn($q) => $q->whereNull('department'))
            ->first();

        if ($existing) {
            // Merge: tambah ke entri yang ada
            $update = [];

            if ($isChannel) {
                $update['up_qty'] = $existing->up_qty + (int) ($data['up_qty'] ?? 0);
                $update['bt_qty'] = $existing->bt_qty + (int) ($data['bt_qty'] ?? 0);
                $update['total_qty']  = ($update['up_qty'] + $update['bt_qty']) / 2;
            } else {
                $update['total_qty'] = $existing->total_qty + (float) ($data['total_qty'] ?? 0);
            }

            // Gabung nomor urut (notes) — deduplikasi baris agar tidak ada pengulangan
            if (!empty($data['notes'])) {
                $combined = $existing->notes
                    ? $existing->notes . "\n" . $data['notes']
                    : $data['notes'];
                $update['notes'] = $this->mergeSerialNotes($combined);
            }

            // Reject ikut digabung, supaya reject yang diinput ke produk+tanggal
            // yang sudah ada tidak hilang tanpa jejak.
            if ($data['reject_qty'] > 0) {
                $update['reject_qty'] = (int) $existing->reject_qty + $data['reject_qty'];
                if (!empty($data['reject_category'])) {
                    $update['reject_category'] = $data['reject_category'];
                }
                if (!empty($data['reject_notes'])) {
                    $update['reject_notes'] = \Illuminate\Support\Str::limit(trim(
                        ($existing->reject_notes ? $existing->reject_notes . '; ' : '') . $data['reject_notes']
                    ), 300, '');
                }
            }

            // Update keterangan jika ada isian baru
            if (!empty($data['keterangan'])) {
                $update['keterangan'] = $existing->keterangan
                    ? $existing->keterangan . '; ' . $data['keterangan']
                    : $data['keterangan'];
            }

            $existing->update($update);
            $log = $existing->fresh(['product']);

            ActivityLog::record('update', "Tambah produksi: {$log->product->name} (total kini: {$log->total_qty} unit)", $log);
            $this->notifyAfterResponse($product, $data['production_date'], $data['product_id']);
            return $this->storeResponse($request, "Ditambahkan ke entri yang ada. Total sekarang: {$this->fmtQty($log->total_qty)} unit.", $log);
        }

        $data['notes'] = $this->nomorUrutSesuaiJumlah(
            $data['notes'] ?? null, $isChannel,
            (int) ($data['up_qty'] ?? 0), (int) ($data['bt_qty'] ?? 0), (float) ($data['total_qty'] ?? 0)
        );

        $log = ProductionLog::create($data);
        ActivityLog::record('create', "Input produksi: {$log->product->name} ({$log->total_qty} unit)", $log);
        $this->notifyAfterResponse($product, $data['production_date'], $data['product_id']);

        return $this->storeResponse($request, "Data produksi berhasil disimpan. Total: {$this->fmtQty($log->total_qty)} unit.", $log);
    }

    public function show(ProductionLog $productionLog)
    {
        $productionLog->load(['product.category', 'user']);

        $lastChannelSerials = ($productionLog->product->isChannel())
            ? $this->lastChannelSerials($productionLog->product_id)
            : ['up' => null, 'bt' => null];

        return Inertia::render('Production/Show', [
            'indexUrl'           => route('production.index'),
            'editUrl'            => route('production.edit', $productionLog->id),
            'lastChannelSerials' => $lastChannelSerials,
            'log' => [
                'id'             => $productionLog->id,
                'dateLabel'      => $productionLog->production_date?->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                'product'        => $productionLog->product->name ?? '-',
                'category'       => $productionLog->product->category->name ?? null,
                'type'           => $productionLog->product->type ?? null,
                'series'         => $productionLog->manual_series ?: ($productionLog->product->series ?? null),
                'kva'            => $productionLog->manual_kva ?: ($productionLog->product->kva ?? null),
                'operator'       => $productionLog->operator_name ?: ($productionLog->user->name ?? '-'),
                'department'     => $productionLog->department,
                'up'         => (int) $productionLog->up_qty,
                'bt'         => (int) $productionLog->bt_qty,
                'total'          => (float) $productionLog->total_qty,
                'reject'         => (int) $productionLog->reject_qty,
                'rejectCategory' => $productionLog->reject_category,
                'rejectNotes'    => $productionLog->reject_notes,
                'notes'          => $productionLog->notes,
                'keterangan'     => $productionLog->keterangan,
                'createdAt'      => $productionLog->created_at?->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMM YYYY HH:mm'),
            ],
        ]);
    }

    public function edit(ProductionLog $productionLog)
    {
        $products = $this->dropdownProducts();
        return Inertia::render('Production/Form', [
            'mode'         => 'edit',
            'action'       => route('production.update', $productionLog->id),
            'indexUrl'     => route('production.index'),
            'lastSerialUrl'=> route('api.production.last-serial'),
            'today'        => today()->toDateString(),
            'operatorName' => $productionLog->operator_name,
            'departments'  => collect(),
            'products'     => $this->productOptions($products),
            'rejectCategories' => collect(\App\Models\ProductionLog::$rejectCategories)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'log' => [
                'id'              => $productionLog->id,
                'product_id'      => $productionLog->product_id,
                'production_date' => $productionLog->production_date?->toDateString(),
                'operator_name'   => $productionLog->operator_name,
                'up_qty'      => (int) $productionLog->up_qty,
                'bt_qty'      => (int) $productionLog->bt_qty,
                'total_qty'       => (float) $productionLog->total_qty,
                'notes'           => $productionLog->notes,
                'manual_series'   => $productionLog->manual_series,
                'manual_kva'      => $productionLog->manual_kva,
                'keterangan'      => $productionLog->keterangan,
                'reject_qty'      => (int) $productionLog->reject_qty,
                'reject_category' => $productionLog->reject_category,
                'reject_notes'    => $productionLog->reject_notes,
            ],
        ]);
    }

    /**
     * Opsi dropdown produk, sama dengan Production-QC-Logging-System:
     * "seri · kva KVA", atau "Seri & KVA Manual CH → PLN" untuk produk
     * placeholder (kategori seri manual yang belum punya seri). Hanya
     * placeholder yang memunculkan isian Seri & KVA manual.
     */
    private function productOptions($products)
    {
        return $products->map(function ($p) {
            $isPlaceholder = $p->category && $p->category->has_manual_serial && ! $p->series;

            if ($isPlaceholder) {
                $nl       = strtolower($p->name);
                $typeTag  = str_contains($nl, 'swasta') ? 'Swasta' : (str_contains($nl, 'type') ? 'TypeTest' : 'PLN');
                $catName  = strtolower($p->category->name ?? '');
                $typeAbbr = match (true) {
                    $p->type === 'channel'          => ' CH',
                    str_contains($catName, 'cover')  => ' CV',
                    str_contains($catName, 'tangki') => ' TK',
                    default                          => '',
                };
                $label = 'Seri & KVA Manual' . $typeAbbr . ' → ' . $typeTag;
            } else {
                $label = ($p->series ?: 'Tanpa seri') . ($p->kva ? ' · ' . $p->kva . ' KVA' : '');
            }

            return [
                'value'  => $p->id,
                'label'  => $label,
                'group'  => $p->name,
                'type'   => $p->type ?: 'regular',
                'manual' => $isPlaceholder,
            ];
        })->values();
    }

    private function dropdownProducts()
    {
        return Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->orderByRaw('CAST(kva AS UNSIGNED)')
            ->orderBy('series')
            ->get();
    }

    /**
     * Gabung baris nomor urut, menyatukan rentang yang bersambung.
     * Contoh: "NO.473-482" + "NO.483-492" → "NO.473-492".
     * Baris non-rentang (tak cocok format) dipertahankan apa adanya (dedup).
     */
    private function mergeSerialNotes(string $combined): string
    {
        $lines = collect(explode("\n", str_replace("\r\n", "\n", $combined)))
            ->map(fn($l) => trim($l))
            ->filter()
            ->unique()
            ->values();

        // Kelompokkan rentang per prefix (mis. "", "UP ", "BT "), pertahankan urutan kemunculan
        $ranges = [];      // prefix => [[start, end], ...]
        $order  = [];      // urutan kemunculan prefix pertama kali
        $width  = [];      // prefix => lebar padding nol maksimum (mis. 001 → 3)
        $others = [];      // baris yang bukan format rentang

        foreach ($lines as $line) {
            if (preg_match('/^(.*?)NO\.(\d+)-(\d+)$/i', $line, $m)) {
                $prefix = $m[1];
                if (!array_key_exists($prefix, $ranges)) {
                    $ranges[$prefix] = [];
                    $order[] = $prefix;
                    $width[$prefix] = 3;
                }
                $ranges[$prefix][] = [(int) $m[2], (int) $m[3]];
                // Padding nol minimal 3 digit seperti format form input, tanpa ini
                // "NO.001-010" tersimpan jadi "NO.1-10" setelah merge.
                $width[$prefix] = max($width[$prefix], strlen($m[2]), strlen($m[3]));
            } else {
                $others[] = $line;
            }
        }

        $out = [];
        foreach ($order as $prefix) {
            $list = $ranges[$prefix];
            usort($list, fn($a, $b) => $a[0] <=> $b[0]);

            $merged = [];
            foreach ($list as $r) {
                if (!empty($merged) && $r[0] <= end($merged)[1] + 1) {
                    // Bersambung/tumpang tindih — perluas rentang terakhir
                    $merged[count($merged) - 1][1] = max(end($merged)[1], $r[1]);
                } else {
                    $merged[] = $r;
                }
            }

            $w = $width[$prefix];
            foreach ($merged as $r) {
                $start = str_pad((string) $r[0], $w, '0', STR_PAD_LEFT);
                $end   = str_pad((string) $r[1], $w, '0', STR_PAD_LEFT);
                $out[] = "{$prefix}NO.{$start}-{$end}";
            }
        }

        return collect(array_merge($out, $others))->implode("\n");
    }

    /**
     * Nomor urut hanya untuk unit yang benar-benar jadi.
     *
     * Total 0 (mis. semua unit reject) berarti tidak ada nomor yang terpakai.
     * Untuk channel, baris UP/BT yang jumlahnya 0 ikut dibuang.
     */
    private function nomorUrutSesuaiJumlah(?string $notes, bool $isChannel, int $up, int $bt, float $total): ?string
    {
        if ($notes === null || trim($notes) === '' || $total <= 0) {
            return null;
        }

        if (! $isChannel) {
            return $notes;
        }

        $baris = collect(preg_split('/\r?\n/', $notes))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->reject(fn ($l) => ($up <= 0 && preg_match('/\bUP\b/i', $l))
                             || ($bt <= 0 && preg_match('/\bBT\b/i', $l)));

        return $baris->isEmpty() ? null : $baris->implode("\n");
    }

    /**
     * Satu query untuk semua channel products — jauh lebih efisien dari N calls.
     */
    private function lastChannelSerialsForMany(array $productIds): array
    {
        $result = array_fill_keys($productIds, ['up' => null, 'bt' => null]);

        ProductionLog::whereIn('product_id', $productIds)
            ->whereNotNull('notes')->where('notes', '!=', '')
            ->orderByDesc('production_date')->orderByDesc('created_at')
            ->select(['product_id', 'notes'])
            ->each(function ($log) use (&$result) {
                $pid = $log->product_id;
                if (!isset($result[$pid])) return;
                if ($result[$pid]['up'] && $result[$pid]['bt']) return;
                $lines = collect(explode("\n", $log->notes))->map(fn($l) => trim($l))->filter();
                if (!$result[$pid]['up']) {
                    $ul = $lines->first(fn($l) => preg_match('/\bUP\b/i', $l));
                    if ($ul) $result[$pid]['up'] = $ul;
                }
                if (!$result[$pid]['bt']) {
                    $bl = $lines->first(fn($l) => preg_match('/\bBT\b/i', $l));
                    if ($bl) $result[$pid]['bt'] = $bl;
                }
            });

        return $result;
    }

    /** Single-product version — dipakai di show() */
    private function lastChannelSerials(int $productId): array
    {
        return $this->lastChannelSerialsForMany([$productId])[$productId] ?? ['up' => null, 'bt' => null];
    }

    public function update(ProductionLogRequest $request, ProductionLog $productionLog)
    {
        $data = $request->validated();
        $data['reject_qty'] = (int) ($data['reject_qty'] ?? 0);
        $product = Product::find($data['product_id']);
        if ($product && $product->isChannel()) {
            $up = (int) ($data['up_qty'] ?? 0);
            $bt = (int) ($data['bt_qty'] ?? 0);
            $data['total_qty']  = ($up + $bt) / 2;
        } else {
            $data['up_qty'] = $data['up_qty'] ?? 0;
            $data['bt_qty'] = $data['bt_qty'] ?? 0;
        }

        $data['notes'] = $this->nomorUrutSesuaiJumlah(
            $data['notes'] ?? null, (bool) $product?->isChannel(),
            (int) $data['up_qty'], (int) $data['bt_qty'], (float) $data['total_qty']
        );

        $productionLog->update($data);
        ActivityLog::record('update', "Edit produksi: {$productionLog->product->name} ({$productionLog->total_qty} unit)", $productionLog);
        $this->notifyAfterResponse($product, $data['production_date'], $data['product_id']);
        return redirect()->route('production.index')
            ->with('success', 'Data produksi berhasil diperbarui.');
    }

    /**
     * API: kembalikan nomor urut (notes) terakhir untuk produk tertentu.
     * Digunakan sebagai hint di form input produksi.
     */
    public function lastSerial(Request $request)
    {
        $productId = (int) $request->input('product_id');
        if (!$productId) {
            return response()->json(['notes' => null]);
        }

        $log = ProductionLog::where('product_id', $productId)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderByDesc('production_date')
            ->orderByDesc('created_at')
            ->first(['notes', 'production_date']);

        if (!$log) {
            return response()->json(['notes' => null]);
        }

        $notes = $log->notes;

        // Untuk produk channel: baris UP dan BT bisa berasal dari log berbeda
        // (mis. hari terakhir hanya input UP, BT-nya di log sebelumnya). Ambil
        // masing-masing baris terakhir secara terpisah agar hint tidak hilang.
        $product = Product::find($productId);
        if ($product && $product->isChannel()) {
            $serials = $this->lastChannelSerials($productId);
            $lines = array_values(array_filter([$serials['up'], $serials['bt']]));
            if (!empty($lines)) {
                $notes = implode("\n", $lines);
            }
        }

        return response()->json([
            'notes' => $notes,
            'date'  => $log->production_date->translatedFormat('d M Y'),
        ]);
    }

    /**
     * Reject unit yang sudah tercatat (mis. ditemukan cacat beberapa hari setelah
     * produksi). Entri asal dikurangi & nomor urut paling akhir dilepas (bisa
     * dipakai unit pengganti), lalu reject dicatat di hari ditemukan — digabung
     * ke entri produk yang sama di hari itu kalau sudah ada.
     *
     * Channel tidak didukung: satu unit = UP + BT, jadi "kurangi 1 unit" ambigu.
     * Pencatatan di hari lain memakai departemen entri asal agar konsisten dgn
     * aturan merge Input Produksi.
     */
    public function rejectUnit(Request $request, ProductionLog $productionLog)
    {
        abort_unless(\App\Support\MenuAccess::can(auth()->user(), 'riwayat-produksi.reject'), 403);

        $productionLog->load('product.category');

        abort_if($productionLog->product?->isChannel(), 422, 'Reject unit belum didukung untuk produk Channel.');

        $maks = (int) floor((float) $productionLog->total_qty);

        $data = $request->validate([
            'jumlah'          => ['required', 'integer', 'min:1', 'max:' . max($maks, 0)],
            'tanggal'         => ['required', 'date', 'before_or_equal:today',
                                  'after_or_equal:' . $productionLog->production_date->toDateString()],
            'reject_category' => ['nullable', 'in:' . implode(',', array_keys(ProductionLog::$rejectCategories))],
            'reject_notes'    => ['nullable', 'string', 'max:300'],
        ], [
            'jumlah.max'              => "Entri ini hanya berisi {$maks} unit.",
            'jumlah.min'              => 'Jumlah reject minimal 1.',
            'tanggal.after_or_equal'  => 'Tanggal ditemukan tidak boleh sebelum tanggal produksinya.',
            'tanggal.before_or_equal' => 'Tanggal ditemukan tidak boleh melebihi hari ini.',
        ]);

        $n       = (int) $data['jumlah'];
        $tanggal = \Illuminate\Support\Carbon::parse($data['tanggal'])->toDateString();
        $asalTgl = $productionLog->production_date->toDateString();

        $hasil = \Illuminate\Support\Facades\DB::transaction(function () use ($productionLog, $n, $tanggal, $asalTgl, $data) {
            $asal = ProductionLog::lockForUpdate()->findOrFail($productionLog->id);

            [$sisaNomor, $dilepas] = $this->lepasNomorTerakhir($asal->notes, $n);
            $totalBaru = max(0, (float) $asal->total_qty - $n);

            $rujukan = "Reject {$n} unit" . ($dilepas ? " {$dilepas}" : '')
                     . ' dari produksi ' . $asal->production_date->format('d/m/Y');

            $asal->total_qty = $totalBaru;
            $asal->notes     = $totalBaru > 0 ? $sisaNomor : null;

            // Ditemukan di hari yang sama dengan produksinya: cukup satu entri.
            if ($tanggal === $asalTgl) {
                $this->tambahReject($asal, $n, $data, $rujukan);
                $asal->save();
                return ['asal' => $asal, 'tujuan' => $asal, 'dilepas' => $dilepas];
            }

            $asal->save();

            // Entri tujuan harus di departemen yang sama dengan entri asal.
            $tujuan = ProductionLog::where('product_id', $asal->product_id)
                ->whereDate('production_date', $tanggal)
                ->when($asal->department !== null, fn ($q) => $q->where('department', $asal->department))
                ->when($asal->department === null, fn ($q) => $q->whereNull('department'))
                ->lockForUpdate()
                ->first();

            if (! $tujuan) {
                $tujuan = new ProductionLog([
                    'product_id'      => $asal->product_id,
                    'department'      => $asal->department,
                    'user_id'         => auth()->id(),
                    'operator_name'   => auth()->user()->name,
                    'production_date' => $tanggal,
                    'up_qty'      => 0,
                    'bt_qty'      => 0,
                    'total_qty'       => 0,
                    'reject_qty'      => 0,
                ]);
            }

            $this->tambahReject($tujuan, $n, $data, $rujukan);
            $tujuan->save();

            return ['asal' => $asal, 'tujuan' => $tujuan, 'dilepas' => $dilepas];
        });

        $nama = $productionLog->product->series_with_kva ?: $productionLog->product->name;
        ActivityLog::record('update',
            "Reject unit: {$nama} {$n} unit" . ($hasil['dilepas'] ? " ({$hasil['dilepas']})" : '')
            . " dari {$productionLog->production_date->format('d/m/Y')}, dicatat {$hasil['tujuan']->production_date->format('d/m/Y')}",
            $hasil['tujuan']);

        return redirect()->route('production.index')->with('success',
            "{$n} unit {$nama} ditandai reject." . ($hasil['dilepas'] ? " Nomor {$hasil['dilepas']} dilepas." : ''));
    }

    /** Tambahkan reject ke sebuah entri, menggabung catatan yang sudah ada. */
    private function tambahReject(ProductionLog $log, int $n, array $data, string $rujukan): void
    {
        $log->reject_qty = (int) $log->reject_qty + $n;

        if (! empty($data['reject_category'])) {
            $log->reject_category = $data['reject_category'];
        }
        if (! empty($data['reject_notes'])) {
            $log->reject_notes = \Illuminate\Support\Str::limit(
                trim(($log->reject_notes ? $log->reject_notes . '; ' : '') . $data['reject_notes']), 300, '');
        }

        $log->keterangan = \Illuminate\Support\Str::limit(
            trim(($log->keterangan ? $log->keterangan . '; ' : '') . $rujukan), 500, '');
    }

    /**
     * Lepas $n nomor paling akhir dari catatan nomor urut.
     * "NO.023-027" dikurangi 2 → sisa "NO.023-025", dilepas "NO.026-027".
     *
     * @return array{0:?string,1:string} [sisa catatan, label nomor yang dilepas]
     */
    private function lepasNomorTerakhir(?string $notes, int $n): array
    {
        if (! $notes || $n <= 0) {
            return [$notes, ''];
        }

        $baris   = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $notes)), 'strlen'));
        $dilepas = [];

        for ($i = count($baris) - 1; $i >= 0 && $n > 0; $i--) {
            if (! preg_match('/^(.*?NO\.)(\d+)-(\d+)(.*)$/i', $baris[$i], $m)) {
                continue;
            }
            [$awalan, $dari, $sampai, $akhiran] = [$m[1], (int) $m[2], (int) $m[3], $m[4]];
            $lebar = max(3, strlen($m[2]), strlen($m[3]));
            $pad   = fn ($x) => str_pad((string) $x, $lebar, '0', STR_PAD_LEFT);

            $ambil      = min($n, $sampai - $dari + 1);
            $baruSampai = $sampai - $ambil;
            array_unshift($dilepas, $ambil === 1 ? $pad($sampai) : $pad($baruSampai + 1) . '-' . $pad($sampai));
            $n -= $ambil;

            if ($baruSampai < $dari) {
                unset($baris[$i]);
            } else {
                $baris[$i] = $awalan . $pad($dari) . '-' . $pad($baruSampai) . $akhiran;
            }
        }

        $sisa = implode("\n", $baris);

        return [$sisa === '' ? null : $sisa, $dilepas ? 'NO.' . implode(', ', $dilepas) : ''];
    }

    public function destroy(Request $request, ProductionLog $productionLog)
    {
        $info = "{$productionLog->product->name} tgl {$productionLog->production_date->format('d/m/Y')}";
        $productionLog->delete();
        ActivityLog::record('delete', "Hapus produksi: {$info}");

        // Request Inertia juga membawa X-Requested-With, jadi ajax() saja tidak
        // cukup: Inertia wajib dibalas redirect, bukan JSON polos.
        if (! $request->header('X-Inertia') && ($request->ajax() || $request->wantsJson())) {
            return response()->json([
                'success' => true,
                'message' => 'Data produksi berhasil dihapus.',
            ]);
        }

        // Kembali ke daftar yang sedang dibuka (filter & halaman tetap), kecuali
        // asalnya halaman detail entri yang baru saja dihapus.
        $asal = url()->previous();
        $keDaftar = parse_url($asal, PHP_URL_PATH) === parse_url(route('production.index'), PHP_URL_PATH);

        return ($keDaftar ? redirect()->to($asal) : redirect()->route('production.index'))
            ->with('success', 'Data produksi berhasil dihapus.');
    }

    /** Format qty: buang desimal .0 tapi pertahankan .5 (mis. channel). */
    private function fmtQty($v): string
    {
        return fmod((float) $v, 1) == 0 ? number_format((float) $v) : number_format((float) $v, 1);
    }

    /**
     * Balas simpan produksi: JSON untuk request AJAX (tanpa reload), redirect biasa
     * untuk submit form normal (progressive enhancement).
     */
    private function storeResponse(Request $request, string $message, ?ProductionLog $log = null)
    {
        // Request Inertia juga membawa X-Requested-With, jadi ajax() saja tidak
        // cukup: Inertia wajib dibalas redirect, bukan JSON polos.
        if (! $request->header('X-Inertia') && ($request->ajax() || $request->wantsJson())) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'total'   => $log ? (float) $log->total_qty : null,
            ]);
        }

        return redirect()->route('production.index')->with('success', $message);
    }

    /**
     * Jalankan cek notifikasi bot (reject rate & target tercapai) SETELAH response
     * terkirim ke browser, supaya simpan/update produksi terasa instan.
     */
    private function notifyAfterResponse(?Product $product, string $date, int $productId): void
    {
        dispatch(function () use ($product, $date, $productId) {
            try {
                if ($product) {
                    BotNotificationService::checkAndAlertRejectRate($product, $date);
                }
                BotNotificationService::checkAndNotifyTargetReached($productId);
            } catch (\Throwable) {}
        })->afterResponse();
    }

    public function poll()
    {
        return response()->json([
            'ts'    => ProductionLog::max('updated_at'),
            'count' => ProductionLog::count(),
        ]);
    }
}
