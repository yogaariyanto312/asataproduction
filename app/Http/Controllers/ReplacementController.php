<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Replacement;
use Illuminate\Http\Request;

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

        $years = Replacement::selectRaw('DISTINCT YEAR(replacement_date) as yr')
            ->orderByDesc('yr')->pluck('yr');

        return view('replacements.index', compact('replacements', 'products', 'years', 'departments', 'deptFilter'));
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
