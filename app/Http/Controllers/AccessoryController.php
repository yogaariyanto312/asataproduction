<?php

namespace App\Http\Controllers;

use App\Exports\AccessoryExport;
use App\Models\Accessory;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Support\MenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

/**
 * Aksesoris Keluar — mengikuti Production-QC-Logging-System.
 * Tambahan asata: filter departemen (developer) & data ter-scope departemen.
 */
class AccessoryController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        [$departments, $deptFilter] = $this->departmentFilter($request, $query, 'accessories.department');

        $accessories = (clone $query)
            ->with(['product', 'user'])
            ->orderByDesc('accessory_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $allProducts = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->orderByRaw('CAST(kva AS UNSIGNED)')
            ->orderBy('series')
            ->get();

        // Kategori "seri manual" menyimpan seri yang sama sebagai baris produk
        // terpisah per kategori — di dropdown aksesoris itu membingungkan (seri
        // 2051 muncul 3x). Digabung jadi satu per kombinasi seri+KVA.
        $manualSeries = $allProducts
            ->filter(fn ($p) => ($p->category?->has_manual_serial ?? false) && $p->series)
            ->unique(fn ($p) => $p->series . '|' . $p->kva)
            ->sortBy(fn ($p) => [(int) $p->kva, $p->series])
            ->values();

        $regularProducts = $allProducts->filter(fn ($p) => ! ($p->category?->has_manual_serial ?? false));

        $opsiProduk = fn ($p) => [
            'value' => $p->id,
            'label' => ($p->series ?: '—') . ($p->kva ? ' · ' . $p->kva . ' KVA' : ''),
        ];

        $productGroups = collect();
        if ($manualSeries->isNotEmpty()) {
            $productGroups->push(['label' => 'Nomor Seri', 'options' => $manualSeries->map($opsiProduk)->values()]);
        }
        foreach ($regularProducts->groupBy('name') as $nama => $isi) {
            $productGroups->push(['label' => $nama, 'options' => $isi->map($opsiProduk)->values()]);
        }

        $user = auth()->user();

        return Inertia::render('Accessories/Index', [
            'filters'     => $request->only(['search', 'month', 'year', 'department']),
            'indexUrl'    => route('accessories.index'),
            'storeUrl'    => route('accessories.store'),
            'exportUrl'   => route('accessories.export', $request->query()),
            'units'       => Accessory::UNITS,
            'can'         => [
                'create' => MenuAccess::can($user, 'aksesoris.create'),
                'edit'   => MenuAccess::can($user, 'aksesoris.edit'),
                'delete' => MenuAccess::can($user, 'aksesoris.delete'),
                'export' => MenuAccess::can($user, 'aksesoris.export'),
            ],
            'departments'    => $departments->map(fn ($d) => ['value' => $d, 'label' => $d])->values(),
            'years'          => $this->tahunTersedia()->map(fn ($y) => ['value' => (string) $y, 'label' => (string) $y])->values(),
            'productGroups'  => $productGroups->values(),
            // Peta id produk → seri, untuk menyusun kunci lastSerials.
            'productSeries'  => $allProducts->mapWithKeys(fn ($p) => [$p->id => (string) $p->series]),
            'accessoryNames' => Accessory::query()->select('name')->distinct()->orderBy('name')->pluck('name'),
            'lastSerials'    => $this->nomorUrutTerakhir(),
            'rows'           => $accessories->through(fn ($a) => [
                'id'         => $a->id,
                'date'       => $a->accessory_date?->locale('id')->isoFormat('D MMM YYYY'),
                'dateInput'  => $a->accessory_date?->toDateString(),
                'name'       => $a->name,
                'productId'  => $a->product_id,
                'series'     => $a->product ? ($a->product->series ?: $a->product->name) : null,
                'kva'        => $a->product?->kva,
                'serial'     => $a->serial_number,
                'qty'        => (int) $a->qty,
                'unit'       => $a->unit,
                'keterangan' => $a->keterangan,
                'operator'   => $a->operator_name ?: ($a->user->name ?? '-'),
                'updateUrl'  => route('accessories.update', $a->id),
                'deleteUrl'  => route('accessories.destroy', $a->id),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['user_id']       = auth()->id();
        $data['operator_name'] = auth()->user()->name;

        $accessory = Accessory::create($data);

        ActivityLog::record('create', "Input aksesoris keluar: {$accessory->name} ({$accessory->qty} {$accessory->unit})", $accessory);

        return redirect()->route('accessories.index')
            ->with('success', "Data aksesoris berhasil disimpan. ({$accessory->qty} {$accessory->unit})");
    }

    public function update(Request $request, Accessory $accessory)
    {
        abort_unless(MenuAccess::can(auth()->user(), 'aksesoris.edit'), 403);

        $accessory->update($this->validated($request));

        ActivityLog::record('update', "Ubah aksesoris keluar: {$accessory->name} ({$accessory->qty} {$accessory->unit})", $accessory);

        return redirect()->route('accessories.index')
            ->with('success', "Data aksesoris '{$accessory->name}' berhasil diperbarui.");
    }

    public function destroy(Accessory $accessory)
    {
        abort_unless(MenuAccess::can(auth()->user(), 'aksesoris.delete'), 403);

        $info = $accessory->name;
        $accessory->delete();
        ActivityLog::record('delete', "Hapus aksesoris keluar: {$info}");

        return redirect()->route('accessories.index')
            ->with('success', 'Data aksesoris berhasil dihapus.');
    }

    public function exportExcel(Request $request)
    {
        abort_unless(MenuAccess::can(auth()->user(), 'aksesoris.export'), 403);

        $query = $this->filteredQuery($request);
        $this->departmentFilter($request, $query, 'accessories.department');

        $accessories = $query->with(['product', 'user'])
            ->orderByDesc('accessory_date')
            ->orderByDesc('created_at')
            ->get();

        return ExcelFacade::download(
            new AccessoryExport($accessories),
            'aksesoris-keluar-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Daftar tahun yang punya data — dari MIN/MAX tanggal, bukan YEAR() yang
     * hanya ada di MySQL.
     */
    private function tahunTersedia(): \Illuminate\Support\Collection
    {
        $rentang = Accessory::selectRaw('MIN(accessory_date) as awal, MAX(accessory_date) as akhir')->first();

        if (! $rentang || ! $rentang->awal) {
            return collect([(int) now()->year]);
        }

        $awal  = (int) \Illuminate\Support\Carbon::parse($rentang->awal)->year;
        $akhir = (int) \Illuminate\Support\Carbon::parse($rentang->akhir)->year;

        return collect(range($akhir, $awal));
    }

    /**
     * Nomor urut terakhir per (jenis aksesoris | seri produk). Rentetan nomor
     * tiap seri berdiri sendiri: BOX seri 2051 yang sudah NO.15 tidak menyeret
     * BOX seri 2052. Kunci memakai SERI (bukan product_id) agar seri yang sama
     * dari kategori berbeda tetap satu rentetan. Baris terakhir dipilih di DB
     * lewat ROW_NUMBER, bukan menarik seluruh riwayat ke memori.
     */
    private function nomorUrutTerakhir(): \Illuminate\Support\Collection
    {
        $peringkat = DB::table('accessories as a')
            ->leftJoin('products as p', 'p.id', '=', 'a.product_id')
            ->selectRaw("a.name, a.serial_number, a.accessory_date, COALESCE(p.series, '') as series")
            ->selectRaw("ROW_NUMBER() OVER (
                PARTITION BY a.name, COALESCE(p.series, '')
                ORDER BY a.accessory_date DESC, a.id DESC
            ) as rn")
            ->whereNotNull('a.serial_number')
            ->where('a.serial_number', '!=', '');

        // Data departemen lain tidak ikut menyarankan nomor (developer melihat semua).
        $user = auth()->user();
        if ($user && $user->role !== 'developer') {
            $user->department
                ? $peringkat->where('a.department', $user->department)
                : $peringkat->whereNull('a.department');
        }

        return DB::query()->fromSub($peringkat, 't')->where('rn', 1)->get()
            ->mapWithKeys(function ($row) {
                preg_match('/(\d+)\s*$/', (string) $row->serial_number, $m);

                return [$row->name . '|' . $row->series => [
                    'serial_number' => $row->serial_number,
                    'date'          => \Illuminate\Support\Carbon::parse($row->accessory_date)->locale('id')->isoFormat('D MMM YYYY'),
                    'series'        => $row->series,
                    'last_number'   => isset($m[1]) ? (int) $m[1] : null,
                ]];
            })
            ->filter(fn ($v) => $v['last_number'] !== null);
    }

    private function validated(Request $request): array
    {
        // Satuan lama sempat tersimpan dengan kapitalisasi berbeda ("Unit").
        if ($request->filled('unit')) {
            $request->merge(['unit' => strtolower(trim((string) $request->input('unit')))]);
        }

        return $request->validate([
            'product_id'     => ['nullable', 'exists:products,id'],
            'accessory_date' => ['required', 'date'],
            'name'           => ['required', 'string', 'max:150'],
            'serial_number'  => ['nullable', 'string', 'max:150'],
            'qty'            => ['required', 'integer', 'min:1'],
            'unit'           => ['nullable', Rule::in(Accessory::UNITS)],
            'keterangan'     => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function filteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Accessory::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->search($search);
        }
        if ($month = $request->input('month')) {
            $query->whereMonth('accessory_date', (int) $month);
        }
        if ($year = $request->input('year')) {
            $query->whereYear('accessory_date', (int) $year);
        }

        return $query;
    }
}
