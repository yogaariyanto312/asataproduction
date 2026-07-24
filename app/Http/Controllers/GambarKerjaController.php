<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GambarKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GambarKerjaController extends Controller
{
    public function index(Request $request)
    {
        $query = GambarKerja::selectRaw(
                'judul, seri, kva, tahun,
                 YEAR(MIN(created_at)) as upload_year,
                 COUNT(*) as total,
                 MIN(id) as first_id,
                 MAX(thumbnail_path) as group_thumbnail,
                 MAX(kategori_seri) as kategori_seri,
                 MAX(keterangan) as keterangan'
            )
            ->groupBy('judul', 'seri', 'kva', 'tahun');

        if ($request->search) {
            $search = $request->search;
            $query->where(fn($q) => $q->where('judul', 'like', "%{$search}%")
                ->orWhere('seri', 'like', "%{$search}%")
                ->orWhere('kva', 'like', "%{$search}%"));
        }

        $allGroups  = $query->get();
        $firstFiles = GambarKerja::whereIn('id', $allGroups->pluck('first_id'))->get()->keyBy('id');

        // Kelompokkan berdasar tahun (fallback ke tahun upload), urut KVA dalam setiap tahun
        $yearGroups = $allGroups
            ->map(fn($g) => tap($g, fn($g) => $g->display_year = $g->tahun ?? $g->upload_year))
            ->sortByDesc('display_year')
            ->groupBy('display_year')
            ->map(fn($items) => $items->sortBy(fn($g) => (int) ($g->kva ?? 0))->values());

        return view('gambar-kerja.index', compact('yearGroups', 'firstFiles'));
    }

    public function byGroup(Request $request)
    {
        $judul = $request->judul;
        $seri  = $request->seri ?: null;
        $kva   = $request->kva ?: null;
        $tahun = $request->tahun ? (int) $request->tahun : null;

        $gambarKerja = GambarKerja::where('judul', $judul)
            ->where('seri', $seri)
            ->where('kva', $kva)
            ->where('tahun', $tahun)
            ->with('uploader')
            ->orderBy('urutan')
            ->get();

        $thumbnailPath = $gambarKerja->first()?->thumbnail_path;
        $kategoriSeri  = $gambarKerja->first()?->kategori_seri ?? 'pln';

        return view('gambar-kerja.by-group', compact('judul', 'seri', 'kva', 'tahun', 'gambarKerja', 'thumbnailPath', 'kategoriSeri'));
    }

    public function updateInfo(Request $request)
    {
        $request->validate([
            'judul_baru'  => ['required', 'string', 'max:150'],
            'keterangan'  => ['nullable', 'string', 'max:300'],
        ]);

        $judul = $request->judul;
        $seri  = $request->seri ?: null;
        $kva   = $request->kva ?: null;
        $tahun = $request->tahun ? (int) $request->tahun : null;

        GambarKerja::where('judul', $judul)
            ->where('seri', $seri)
            ->where('kva', $kva)
            ->where('tahun', $tahun)
            ->update([
                'judul'      => $request->judul_baru,
                'keterangan' => $request->keterangan,
            ]);

        ActivityLog::record('update', "Edit info gambar kerja: {$judul} → {$request->judul_baru}");

        return redirect()->route('gambar-kerja.by-group', [
            'judul' => $request->judul_baru,
            'seri'  => $seri,
            'kva'   => $kva,
            'tahun' => $tahun,
        ])->with('success', 'Judul dan keterangan berhasil diperbarui.');
    }

    public function updateKategori(Request $request)
    {
        $request->validate(['kategori_seri' => ['required', 'in:pln,swasta,typetest']]);

        $judul = $request->judul;
        $seri  = $request->seri ?: null;
        $kva   = $request->kva ?: null;
        $tahun = $request->tahun ? (int) $request->tahun : null;

        GambarKerja::where('judul', $judul)
            ->where('seri', $seri)
            ->where('kva', $kva)
            ->where('tahun', $tahun)
            ->update(['kategori_seri' => $request->kategori_seri]);

        ActivityLog::record('update', "Update kategori seri gambar kerja: {$judul} → {$request->kategori_seri}");

        return back()->with('success', 'Kategori seri berhasil diperbarui.');
    }

    public function create()
    {
        return view('gambar-kerja.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'judul'    => ['required', 'string', 'max:150'],
            'seri'     => ['nullable', 'string', 'max:150'],
            'kva'      => ['nullable', 'string', 'max:50'],
            'tahun'    => ['nullable', 'integer', 'min:2025', 'max:' . (now()->year + 5)],
            'files'    => ['required', 'array', 'min:1'],
            'files.*'  => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:102400'],
            'kategori_seri' => ['nullable', 'in:pln,swasta,typetest'],
            'keterangan'    => ['nullable', 'string', 'max:300'],
        ], [
            'judul.required' => 'Judul gambar kerja wajib diisi.',
            'files.required' => 'File gambar kerja wajib diupload.',
            'files.*.mimes'  => 'Format file harus JPG, PNG, atau PDF.',
            'files.*.max'    => 'Ukuran setiap file maksimal 100MB.',
        ]);

        $judul = $request->judul;
        $seri  = $request->seri ?: null;
        $kva   = $request->kva ?: null;
        $tahun = $request->tahun ? (int) $request->tahun : null;

        $nextUrutan = GambarKerja::where('judul', $judul)
            ->where('seri', $seri)
            ->where('kva', $kva)
            ->where('tahun', $tahun)
            ->max('urutan') + 1;

        foreach ($request->file('files') as $index => $file) {
            $ext      = $file->getClientOriginalExtension();
            $fileType = in_array(strtolower($ext), ['jpg', 'jpeg', 'png']) ? 'image' : 'pdf';
            $filePath = $file->storeAs('gambar-kerja', Str::uuid() . '.' . $ext, 'public');
            $urutan   = $nextUrutan + $index;

            GambarKerja::create([
                'judul'         => $judul,
                'seri'          => $seri,
                'kva'           => $kva,
                'kategori_seri' => $request->kategori_seri ?: 'pln',
                'tahun'         => $tahun,
                'file_path'     => $filePath,
                'file_type'     => $fileType,
                'keterangan'    => $request->keterangan,
                'uploaded_by'   => auth()->user()?->id,
                'urutan'        => $urutan,
            ]);
        }

        $seriKva = $seri . ($kva ? "({$kva})" : '');
        $label   = $judul . ($seriKva ? " · {$seriKva}" : '');
        ActivityLog::record('create', "Upload gambar kerja: {$label}");

        return redirect()->route('gambar-kerja.by-group', ['judul' => $judul, 'seri' => $seri, 'kva' => $kva, 'tahun' => $tahun])
            ->with('success', count($request->file('files')) . " file berhasil diupload.");
    }

    public function destroyByGroup(Request $request)
    {
        $judul = $request->judul;
        $seri  = $request->seri ?: null;
        $kva   = $request->kva ?: null;
        $tahun = $request->tahun ? (int) $request->tahun : null;

        $gambarList = GambarKerja::where('judul', $judul)->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)->get();
        foreach ($gambarList as $g) {
            Storage::disk('public')->delete($g->file_path);
        }
        GambarKerja::where('judul', $judul)->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)->delete();

        $seriKva = $seri . ($kva ? "({$kva})" : '');
        $label   = $judul . ($seriKva ? " · {$seriKva}" : '');
        ActivityLog::record('delete', "Hapus semua gambar kerja: {$label}");

        return redirect()->route('gambar-kerja.index')
            ->with('success', "Semua gambar kerja '{$label}' berhasil dihapus.");
    }

    public function serveFile(string $path)
    {
        abort_unless(Storage::disk('public')->exists($path), 404);
        return response()->file(Storage::disk('public')->path($path));
    }

    public function deleteThumbnail(Request $request)
    {
        $judul = $request->judul;
        $seri  = $request->seri ?: null;
        $kva   = $request->kva ?: null;
        $tahun = $request->tahun ? (int) $request->tahun : null;

        $path = GambarKerja::where('judul', $judul)
            ->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)
            ->whereNotNull('thumbnail_path')
            ->value('thumbnail_path');

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        GambarKerja::where('judul', $judul)
            ->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)
            ->update(['thumbnail_path' => null]);

        return back()->with('success', 'Thumbnail berhasil dihapus.');
    }

    public function uploadThumbnail(Request $request)
    {
        $request->validate([
            'judul'     => ['required', 'string'],
            'seri'      => ['nullable', 'string'],
            'kva'       => ['nullable', 'string'],
            'tahun'     => ['nullable', 'integer'],
            'thumbnail' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'thumbnail.required' => 'File gambar thumbnail wajib dipilih.',
            'thumbnail.image'    => 'File harus berupa gambar.',
            'thumbnail.max'      => 'Ukuran thumbnail maksimal 5MB.',
        ]);

        $judul = $request->judul;
        $seri  = $request->seri ?: null;
        $kva   = $request->kva ?: null;
        $tahun = $request->tahun ? (int) $request->tahun : null;

        // Hapus thumbnail lama dari storage jika ada
        $existing = GambarKerja::where('judul', $judul)
            ->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)
            ->whereNotNull('thumbnail_path')
            ->value('thumbnail_path');

        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        // Simpan thumbnail baru
        $path = $request->file('thumbnail')->store('gambar-kerja/thumbnails', 'public');

        // Update semua record dalam grup
        GambarKerja::where('judul', $judul)
            ->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)
            ->update(['thumbnail_path' => $path]);

        return back()->with('success', 'Thumbnail berhasil diperbarui.');
    }

    public function destroy(GambarKerja $gambarKerja)
    {
        $judul = $gambarKerja->judul;
        $seri  = $gambarKerja->seri;
        $kva   = $gambarKerja->kva;
        $tahun = $gambarKerja->tahun;

        Storage::disk('public')->delete($gambarKerja->file_path);
        $gambarKerja->delete();

        GambarKerja::where('judul', $judul)
            ->where('seri', $seri)
            ->where('kva', $kva)
            ->where('tahun', $tahun)
            ->orderBy('urutan')
            ->get()
            ->each(fn($g, $i) => $g->update(['urutan' => $i + 1]));

        ActivityLog::record('delete', "Hapus file gambar kerja dari: {$judul}");

        return redirect()->route('gambar-kerja.by-group', ['judul' => $judul, 'seri' => $seri, 'kva' => $kva, 'tahun' => $tahun])
            ->with('success', "File berhasil dihapus dan nomor urut diperbarui.");
    }
}
