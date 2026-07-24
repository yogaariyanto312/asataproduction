<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionLogRequest;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Services\BotNotificationService;
use Illuminate\Http\Request;

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
            ->get(['id', 'product_id', 'shift1_qty', 'shift2_qty', 'total_qty']);

        $totalUp    = $todayLogs->sum('shift1_qty');
        $totalBt    = $todayLogs->sum('shift2_qty');
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
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        // Daftar tahun unik — ambil tanggal lalu ekstrak tahun di PHP (cross-DB: MySQL & SQLite)
        $years      = ProductionLog::query()
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

        return view('production.index', compact(
            'logs', 'dates', 'products', 'categories', 'years',
            'dateFrom', 'dateTo',
            'totalUp', 'totalBt', 'totalTanki', 'totalCover', 'grandTotal', 'totalCount',
            'lastChannelNums', 'departments', 'deptFilter'
        ));
    }

    public function create()
    {
        $products    = $this->dropdownProducts();
        // Developer memilih departemen tujuan; role lain otomatis ikut departemennya.
        $departments = auth()->user()?->role === 'developer'
            ? \App\Models\Department::where('is_active', true)->orderBy('name')->pluck('name')
            : collect();
        return view('production.create', compact('products', 'departments'));
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
            $data['total_qty'] ?? '', $data['shift1_qty'] ?? '', $data['shift2_qty'] ?? '',
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
            $up = (int) ($data['shift1_qty'] ?? 0);
            $bt = (int) ($data['shift2_qty'] ?? 0);
            $data['shift3_qty'] = 0;
            $data['total_qty']  = ($up + $bt) / 2;
        } else {
            $data['shift1_qty'] = $data['shift1_qty'] ?? 0;
            $data['shift2_qty'] = $data['shift2_qty'] ?? 0;
            $data['shift3_qty'] = $data['shift3_qty'] ?? 0;
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
                $update['shift1_qty'] = $existing->shift1_qty + (int) ($data['shift1_qty'] ?? 0);
                $update['shift2_qty'] = $existing->shift2_qty + (int) ($data['shift2_qty'] ?? 0);
                $update['shift3_qty'] = 0;
                $update['total_qty']  = ($update['shift1_qty'] + $update['shift2_qty']) / 2;
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

        return view('production.show', compact('productionLog', 'lastChannelSerials'));
    }

    public function edit(ProductionLog $productionLog)
    {
        $products = $this->dropdownProducts();
        return view('production.edit', compact('productionLog', 'products'));
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
                    $width[$prefix] = 0;
                }
                $ranges[$prefix][] = [(int) $m[2], (int) $m[3]];
                // Pertahankan padding nol asli (mis. "001-010" berlebar 3)
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
            $up = (int) ($data['shift1_qty'] ?? 0);
            $bt = (int) ($data['shift2_qty'] ?? 0);
            $data['shift3_qty'] = 0;
            $data['total_qty']  = ($up + $bt) / 2;
        } else {
            $data['shift1_qty'] = $data['shift1_qty'] ?? 0;
            $data['shift2_qty'] = $data['shift2_qty'] ?? 0;
            $data['shift3_qty'] = $data['shift3_qty'] ?? 0;
        }

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

    public function destroy(Request $request, ProductionLog $productionLog)
    {
        $info = "{$productionLog->product->name} tgl {$productionLog->production_date->format('d/m/Y')}";
        $productionLog->delete();
        ActivityLog::record('delete', "Hapus produksi: {$info}");

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data produksi berhasil dihapus.',
            ]);
        }

        return redirect()->route('production.index')
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
        if ($request->ajax() || $request->wantsJson()) {
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
