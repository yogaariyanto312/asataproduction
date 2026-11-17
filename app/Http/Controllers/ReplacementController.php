<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Replacement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReplacementController extends Controller
{
    public function index(Request $request)
    {
        $query = Replacement::with(['product', 'user'])
            ->orderByDesc('replacement_date')
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->input('search'))) {
            $query->search($search);
        }

        if ($month = $request->input('month')) {
            $query->whereMonth('replacement_date', (int) $month);
        }

        if ($year = $request->input('year')) {
            $query->whereYear('replacement_date', (int) $year);
        }

        // Developer (lihat semua dept) bisa menyaring per departemen.
        [$departments, $deptFilter] = $this->departmentFilter($request, $query, 'replacements.department');

        $replacements = $query->paginate(20)->withQueryString();

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->orderByRaw('CAST(kva AS UNSIGNED)')
            ->orderBy('series')
            ->get();

        // Rentang tahun dari MIN/MAX tanggal — bukan YEAR() yang hanya ada di MySQL.
        $rentang = Replacement::selectRaw('MIN(replacement_date) as awal, MAX(replacement_date) as akhir')->first();
        $years = (! $rentang || ! $rentang->awal)
            ? collect([(int) now()->year])
            : collect(range(
                (int) \Illuminate\Support\Carbon::parse($rentang->akhir)->year,
                (int) \Illuminate\Support\Carbon::parse($rentang->awal)->year,
            ));

        return Inertia::render('Replacements/Index', [
            'filters'     => $request->only(['search', 'month', 'year', 'department']),
            'indexUrl'    => route('replacements.index'),
            'storeUrl'    => route('replacements.store'),
            'can'         => [
                'create' => \App\Support\MenuAccess::can(auth()->user(), 'barang-pengganti.create'),
                'delete' => \App\Support\MenuAccess::can(auth()->user(), 'barang-pengganti.delete'),
            ],
            'departments' => $departments->map(fn ($d) => ['value' => $d, 'label' => $d])->values(),
            'years'       => $years->map(fn ($y) => ['value' => $y, 'label' => (string) $y])->values(),
            'products'    => $products->map(fn ($p) => [
                'value' => $p->id,
                'label' => trim($p->name . ' ' . ($p->series ?? '') . ' ' . ($p->kva ? $p->kva . ' kVA' : '')),
            ])->values(),
            'rows'        => $replacements->through(fn ($r) => [
                'id'          => $r->id,
                'date'        => $r->replacement_date?->locale('id')->isoFormat('D MMM YYYY'),
                'product'     => $r->product->name ?? '-',
                'qty'         => (int) $r->qty,
                'recipient'   => $r->recipient,
                'reason'      => $r->reason,
                'serials'     => $r->original_serial
                    ? array_values(array_filter(preg_split('/\s*,\s*/', trim($r->original_serial))))
                    : [],
                'canToggle'   => auth()->id() === $r->user_id || auth()->user()->isPrivileged(),
                'keterangan'  => $r->keterangan,
                'operator'    => $r->operator_name ?: ($r->user->name ?? '-'),
                'department'  => $r->department,
                'completed'   => (bool) $r->completed_at,
                'toggleUrl'   => route('replacements.toggle-complete', $r->id),
                'deleteUrl'   => route('replacements.destroy', $r->id),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'       => ['required', 'exists:products,id'],
            'replacement_date' => ['required', 'date'],
            'qty'              => ['required', 'integer', 'min:1'],
            'recipient'        => ['nullable', 'string', 'max:150'],
            'reason'           => ['nullable', 'string', 'max:255'],
            'original_serial'  => ['nullable', 'string', 'max:150'],
            'keterangan'       => ['nullable', 'string', 'max:1000'],
        ]);

        $data['user_id']       = auth()->id();
        $data['operator_name'] = auth()->user()->name;

        $replacement = Replacement::create($data);
        $replacement->load('product');

        ActivityLog::record(
            'create',
            "Input barang pengganti: {$replacement->product->name} ({$replacement->qty} unit)",
            $replacement
        );

        return redirect()->route('replacements.index')
            ->with('success', "Data barang pengganti berhasil disimpan. ({$replacement->qty} unit)");
    }

    public function toggleComplete(Replacement $replacement)
    {
        abort_unless(
            auth()->id() === $replacement->user_id || auth()->user()->isPrivileged(),
            403
        );

        $replacement->completed_at = $replacement->completed_at ? null : now();
        $replacement->save();

        $info   = optional($replacement->product)->name ?? 'Produk';
        $status = $replacement->completed_at ? 'selesai' : 'belum selesai';

        ActivityLog::record(
            'update',
            "Tandai barang pengganti {$status}: {$info}",
            $replacement
        );

        return redirect()->back()
            ->with('success', "Data barang pengganti ditandai {$status}.");
    }

    public function destroy(Replacement $replacement)
    {
        abort_unless(
            \App\Support\MenuAccess::can(auth()->user(), 'barang-pengganti.delete'),
            403
        );

        $info = optional($replacement->product)->name ?? 'Produk';
        $replacement->delete();
        ActivityLog::record('delete', "Hapus barang pengganti: {$info}");

        return redirect()->route('replacements.index')
            ->with('success', 'Data barang pengganti berhasil dihapus.');
    }
}
