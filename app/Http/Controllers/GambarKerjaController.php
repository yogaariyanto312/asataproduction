<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GambarKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GambarKerjaController extends Controller
{
    public function index(Request $request)
    {
        // MIN(created_at) diambil apa adanya lalu tahunnya dihitung di PHP —
        // YEAR() hanya ada di MySQL sehingga query lama tidak bisa diuji di SQLite.
        $query = GambarKerja::selectRaw(
                'judul, seri, kva, tahun,
                 MIN(created_at) as first_created,
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

        // Kelompokkan berdasar tahun (fallback ke tahun upload), urut sesuai pilihan (bawaan: seri terkecil)
        $sort = in_array($request->input('sort'), ['seri', 'seri_desc', 'kva', 'kva_desc', 'judul'], true)
            ? $request->input('sort') : 'seri';
        $key  = match ($sort) {
            'kva', 'kva_desc' => fn($g) => [$this->kvaValue($g), $this->seriValue($g), $g->judul],
            'judul'           => fn($g) => [strtolower($g->judul)],
            default           => fn($g) => [$this->seriValue($g), $this->kvaValue($g), $g->judul],
        };
        $desc = str_ends_with($sort, '_desc');

        $yearGroups = $allGroups
            ->map(fn($g) => tap($g, fn($g) => $g->display_year = $g->tahun
                ?? ($g->first_created ? (int) \Illuminate\Support\Carbon::parse($g->first_created)->year : now()->year)))
            ->sortByDesc('display_year')
            ->groupBy('display_year')
            ->map(fn($items) => $items->sort(fn($a, $b) => $desc ? $key($b) <=> $key($a) : $key($a) <=> $key($b))->values());

        return Inertia::render('GambarKerja/Index', [
            'search'    => $request->search,
            'sort'      => $sort,
            'indexUrl'  => route('gambar-kerja.index'),
            'createUrl' => route('gambar-kerja.create'),
            'groupUrl'  => route('gambar-kerja.by-group'),
            'destroyUrl' => route('gambar-kerja.destroy-by-group'),
            'pollUrl'   => route('api.gambar-kerja.poll'),
            'berkasUrl' => route('api.gambar-kerja.berkas'),
            'can'       => [
                'upload' => \App\Support\MenuAccess::can(auth()->user(), 'gambar-kerja.upload'),
                'delete' => \App\Support\MenuAccess::can(auth()->user(), 'gambar-kerja.delete'),
            ],
            'years'     => $yearGroups->map(fn ($items, $year) => [
                'year'   => (string) $year,
                'groups' => $items->map(function ($g) use ($firstFiles) {
                    $first = $firstFiles->get($g->first_id);
                    $thumb = $g->group_thumbnail
                        ?: (($first && $first->file_type !== 'pdf') ? $first->file_path : null);

                    return [
                        // Tanpa pratinjau: versi lama membedakan berkas PDF
                        // dengan ikon merah, bukan kotak kosong biasa.
                        'isPdf'      => ! $thumb && $first && $first->file_type === 'pdf',
                        'judul'      => $g->judul,
                        'seri'       => $g->seri,
                        'kva'        => $g->kva,
                        'tahun'      => $g->tahun,
                        'kategori'   => $g->kategori_seri,
                        'keterangan' => $g->keterangan,
                        'total'      => (int) $g->total,
                        // Kartu memakai turunan kecil (storage.thumb), bukan gambar
                        // penuh — jauh lebih hemat bandwidth di HP & koneksi lambat.
                        'thumbnail'  => $thumb ? route('storage.thumb', ['path' => $thumb]) : null,
                        'query'      => array_filter([
                            'judul' => $g->judul,
                            'seri'  => $g->seri,
                            'kva'   => $g->kva,
                            'tahun' => $g->tahun,
                        ], fn ($v) => $v !== null && $v !== ''),
                    ];
                })->values(),
            ])->values(),
        ]);
    }

    /** Nilai KVA dari kolom kva, fallback parse dari judul/seri (mis. "26F0122103-250KVA" → 250). */
    private function kvaValue($g): int
    {
        if (is_numeric($g->kva)) return (int) $g->kva;
        foreach ([$g->kva, $g->judul, $g->seri] as $text) {
            if ($text && preg_match('/(\d+)\s*KVA/i', $text, $m)) return (int) $m[1];
        }
        return PHP_INT_MAX;
    }

    /** Nomor urut seri = 4 digit terakhir kode sebelum "-" (mis. "26M0272101-1250KVA" → 2101). */
    private function seriValue($g): int
    {
        foreach ([$g->seri, $g->judul] as $text) {
            if ($text && preg_match('/(\d{1,4})(?=\s*-|\s*$)/', explode('-', $text)[0], $m)) return (int) $m[1];
        }
        return PHP_INT_MAX;
    }

    /** Lokasi PDF.js lokal untuk viewer kanvas (dipakai di HP). */
    public static function pdfjs(): array
    {
        return [
            'lib'    => asset('vendor/pdfjs/pdf.min.js'),
            'worker' => asset('vendor/pdfjs/pdf.worker.min.js'),
            // PDF.js menyambung nama berkas langsung ke URL ini → garis miring wajib.
            'cmaps'  => asset('vendor/pdfjs/cmaps') . '/',
            'fonts'  => asset('vendor/pdfjs/standard_fonts') . '/',
            'viewer' => asset('vendor/pdfjs/qc-pdf-viewer.js'),
        ];
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

        return Inertia::render('GambarKerja/Group', [
            'backUrl'   => route('gambar-kerja.index'),
            'infoUrl'   => route('gambar-kerja.update-info'),
            'kategoriUrl' => route('gambar-kerja.update-kategori'),
            'thumbUrl'  => route('gambar-kerja.upload-thumbnail'),
            'thumbDestroyUrl' => route('gambar-kerja.delete-thumbnail'),
            'groupDestroyUrl' => route('gambar-kerja.destroy-by-group'),
            'addFileUrl' => route('gambar-kerja.create', array_filter([
                'judul' => $judul, 'seri' => $seri, 'kva' => $kva, 'tahun' => $tahun,
            ], fn ($v) => $v !== null && $v !== '')),
            'baseUrl'   => url('/gambar-kerja'),
            'can'       => [
                'upload'   => \App\Support\MenuAccess::can(auth()->user(), 'gambar-kerja.upload'),
                'edit'     => \App\Support\MenuAccess::can(auth()->user(), 'gambar-kerja.edit'),
                'delete'   => \App\Support\MenuAccess::can(auth()->user(), 'gambar-kerja.delete'),
                'download' => auth()->user()->isPrivileged(),
                // Tombol unduh di viewer PDF: semua kecuali visitor (seperti referensi).
                'pdfDownload' => ! auth()->user()->isVisitor(),
            ],
            'pdfjs'     => self::pdfjs(),
            'group'     => [
                'judul'    => $judul,
                'seri'     => $seri,
                'kva'      => $kva,
                'tahun'    => $tahun,
                'kategori' => $kategoriSeri,
                'thumbnail'=> $thumbnailPath ? route('storage.file', ['path' => $thumbnailPath]) : null,
                'keterangan' => $gambarKerja->first()?->keterangan,
            ],
            'files'     => $gambarKerja->map(fn ($f) => [
                'id'       => $f->id,
                'url'      => route('storage.file', ['path' => $f->file_path]),
                'type'     => $f->file_type,
                'isPdf'    => $f->file_type === 'pdf',
                'urutan'   => (int) $f->urutan,
                'keterangan' => $f->keterangan,
                'uploader' => $f->uploader->name ?? null,
                'at'       => $f->created_at?->locale('id')->isoFormat('D MMM YYYY'),
                'deleteUrl'=> route('gambar-kerja.destroy', $f->id),
            ])->values(),
        ]);
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

    public function create(Request $request)
    {
        // Dari tombol "Tambah File" di halaman kelompok: identitas grup dibawa
        // lewat query agar berkas baru masuk ke kelompok yang sama.
        $prefill = array_filter([
            'judul' => $request->judul,
            'seri'  => $request->seri,
            'kva'   => $request->kva,
            'tahun' => $request->tahun,
        ], fn ($v) => $v !== null && $v !== '');

        return Inertia::render('GambarKerja/Create', [
            'action'   => route('gambar-kerja.store'),
            'indexUrl' => $prefill
                ? route('gambar-kerja.by-group', $prefill)
                : route('gambar-kerja.index'),
            'maxYear'  => now()->year + 5,
            'prefill'  => $prefill ?: null,
        ]);
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
            $this->hapusBerkas($g->file_path);
        }

        // Thumbnail grup dipakai bersama semua record, jadi cukup sekali dibuang.
        // Sebelumnya tidak ikut terhapus dan tertinggal sebagai berkas yatim.
        foreach ($gambarList->pluck('thumbnail_path')->filter()->unique() as $thumb) {
            $this->hapusBerkas($thumb);
        }
        GambarKerja::where('judul', $judul)->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)->delete();

        $seriKva = $seri . ($kva ? "({$kva})" : '');
        $label   = $judul . ($seriKva ? " · {$seriKva}" : '');
        ActivityLog::record('delete', "Hapus semua gambar kerja: {$label}");

        return redirect()->route('gambar-kerja.index')
            ->with('success', "Semua gambar kerja '{$label}' berhasil dihapus.");
    }

    public function serveFile(Request $request, string $path)
    {
        // Urutan penting: path dibersihkan & izin dicek SEBELUM memeriksa apakah
        // berkasnya ada, supaya orang tanpa izin tidak bisa menebak berkas mana
        // yang ada (403 untuk semua, ada atau tidak).
        $path = $this->pathAman($path);
        $this->pastikanBoleh($request, $path);
        abort_unless(Storage::disk('public')->exists($path), 404);

        $berkas = Storage::disk('public')->path($path);

        // Nama berkas memakai UUID dan tidak pernah ditimpa — isinya tetap
        // sama selamanya. Karena itu browser boleh menyimpannya lama supaya
        // gambar/PDF yang sudah pernah dibuka tidak perlu diunduh lagi.
        // "private" dipakai agar hanya browser pengguna yang menyimpan, bukan
        // proxy bersama, sebab berkas ini di balik login.
        $response = response()->file($berkas);

        $response->setAutoLastModified();
        $response->setAutoEtag();

        // Ditulis setelah response dibuat: BinaryFileResponse memasang
        // "public" sendiri, dan header di bawah ini harus yang menang.
        $response->headers->set('Cache-Control', 'private, max-age=31536000, immutable');

        // Kalau browser sudah punya salinan yang sama, cukup balas 304 tanpa
        // mengirim ulang isinya.
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Sajikan versi KECIL sebuah gambar untuk kartu daftar. Turunan dibuat sekali
     * lalu di-cache (lihat [[App\Support\ImageThumbnail]]); kalau sumbernya tak
     * bisa diproses, berkas asli yang disajikan agar gambar tetap tampil.
     */
    public function serveThumb(Request $request, string $path)
    {
        $path = $this->pathAman($path);
        $this->pastikanBoleh($request, $path);
        abort_unless(Storage::disk('public')->exists($path), 404);

        $kecil  = \App\Support\ImageThumbnail::untuk($path) ?? $path;
        $berkas = Storage::disk('public')->path($kecil);

        $response = response()->file($berkas);
        $response->setAutoLastModified();
        $response->setAutoEtag();
        $response->headers->set('Cache-Control', 'private, max-age=31536000, immutable');
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Tolak path yang mencoba keluar dari disk public ("../", path absolut,
     * byte nol) sebelum menyentuh sistem berkas.
     */
    private function pathAman(string $path): string
    {
        $bersih = str_replace(chr(92), '/', $path);

        abort_if(str_starts_with($bersih, '/'), 404);
        abort_if(preg_match('#(^|/)[.][.](/|$)#', $bersih) === 1, 404);
        abort_if(str_contains($bersih, chr(0)), 404);

        return $bersih;
    }

    /** Izin per folder — lihat [[App\Support\AksesBerkas]]. */
    private function pastikanBoleh(Request $request, string $path): void
    {
        abort_unless(\App\Support\AksesBerkas::boleh($request->user(), $path), 403,
            'Akses ditolak. Anda tidak memiliki izin untuk berkas ini.');
    }

    /** Sinyal ringan untuk auto-refresh daftar: berubah bila ada data baru/diubah. */
    public function poll()
    {
        return response()->json([
            'ts'    => GambarKerja::max('updated_at'),
            'count' => GambarKerja::count(),
        ]);
    }

    /**
     * Daftar semua berkas gambar kerja (+ thumbnail kartu) beserta ukurannya,
     * untuk tombol "Unduh Semua" — menyimpan semuanya di perangkat lebih dulu
     * supaya saat dibutuhkan langsung terbuka tanpa menunggu unduhan.
     */
    public function daftarBerkas()
    {
        $disk  = Storage::disk('public');
        $semua = GambarKerja::orderBy('id')->get(['id', 'judul', 'seri', 'kva', 'tahun', 'file_path', 'file_type', 'thumbnail_path']);

        $berkas = [];
        $tambah = function (string $url, string $path) use (&$berkas, $disk) {
            if (isset($berkas[$url]) || ! $disk->exists($path)) {
                return;
            }
            $berkas[$url] = ['url' => $url, 'ukuran' => (int) $disk->size($path)];
        };

        foreach ($semua as $g) {
            $tambah(route('storage.file', ['path' => $g->file_path]), $g->file_path);
        }

        // Thumbnail kartu, aturannya sama dengan index(): sampul grup; kalau tidak
        // ada, berkas pertama asal berupa gambar. Ukurannya dari turunan kecil.
        foreach ($semua->groupBy(fn ($g) => implode('|', [$g->judul, $g->seri, $g->kva, $g->tahun])) as $grup) {
            $pertama = $grup->first();
            $sampul  = $grup->pluck('thumbnail_path')->filter()->max()
                ?? ($pertama->isImage() ? $pertama->file_path : null);

            if ($sampul) {
                $kecil = \App\Support\ImageThumbnail::untuk($sampul) ?? $sampul;
                $url   = route('storage.thumb', ['path' => $sampul]);
                if (! isset($berkas[$url]) && $disk->exists($kecil)) {
                    $berkas[$url] = ['url' => $url, 'ukuran' => (int) $disk->size($kecil)];
                }
            }
        }

        return response()->json([
            'berkas' => array_values($berkas),
            // Pustaka viewer PDF ikut disimpan supaya PDF tetap terbuka tanpa jaringan.
            'aset'   => [self::pdfjs()['lib'], self::pdfjs()['worker'], self::pdfjs()['viewer']],
        ]);
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
            $this->hapusBerkas($path);
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
            $this->hapusBerkas($existing);
        }

        // Simpan thumbnail baru, lalu kecilkan di tempat. Foto dari HP/scan biasanya
        // ribuan piksel & ratusan KB, padahal hanya dipakai untuk kartu kecil.
        $path = $request->file('thumbnail')->store('gambar-kerja/thumbnails', 'public');
        if ($kecil = \App\Support\ImageThumbnail::untuk($path)) {
            Storage::disk('public')->put($path, Storage::disk('public')->get($kecil));
            Storage::disk('public')->delete($kecil);
        }

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

        $thumbnail = $gambarKerja->thumbnail_path;

        $this->hapusBerkas($gambarKerja->file_path);
        $gambarKerja->delete();

        // Berkas terakhir di grup: thumbnail grup tidak punya pemilik lagi.
        $sisa = GambarKerja::where('judul', $judul)
            ->where('seri', $seri)->where('kva', $kva)->where('tahun', $tahun)->count();
        if ($sisa === 0) {
            $this->hapusBerkas($thumbnail);
        }

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

    /** Hapus sebuah berkas beserta turunan kecilnya. */
    private function hapusBerkas(?string $path): void
    {
        if (! $path) {
            return;
        }

        \App\Support\ImageThumbnail::hapusTurunan($path);
        Storage::disk('public')->delete($path);
    }
}
