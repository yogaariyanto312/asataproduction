<?php

namespace App\Services\Telegram;

use App\Models\BotSetting;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Support\Facades\Cache;

/**
 * Menerjemahkan pesan Telegram menjadi balasan.
 *
 * Sengaja TIDAK mengirim apa pun sendiri — hanya mengembalikan daftar balasan,
 * supaya seluruh aturannya bisa diuji tanpa jaringan sama sekali.
 *
 * ── Otorisasi ──
 * Dulu tidak ada sama sekali: siapa pun yang menemukan username bot bisa
 * mengirim /jadwal beserta foto, dan foto itu tersimpan sebagai jadwal produksi
 * resmi. Sekarang:
 *
 *  - identitas diambil dari `message.from.id` (ORANGNYA), bukan `chat.id`
 *    (TEMPATNYA) — di grup, banyak orang berbagi satu chat.id;
 *  - perintah yang MENULIS data menuntut akun tertaut DAN izin yang sama persis
 *    dengan yang berlaku di aplikasi (MenuAccess), jadi tidak ada daftar hak
 *    akses kedua yang bisa ketinggalan zaman;
 *  - perintah yang hanya MEMBACA juga boleh dari grup yang terdaftar di
 *    Settings, supaya tanya-jawab tetap berjalan di grup kerja.
 */
class BotPerintah
{
    /** Sesi unggah berlaku sebentar; kalau ditinggal, tidak menggantung selamanya. */
    private const SESI_MENIT = 10;

    public function __construct(
        private BotSetting $setting,
    ) {}

    /**
     * Daftar perintah: kunci => [keterangan, izin, contoh].
     * izin null = tidak menyentuh data, cukup boleh memakai bot.
     */
    public static function daftar(): array
    {
        return [
            '/help'      => ['Daftar perintah ini',                        null,                    '/help'],
            '/info'      => ['Ringkasan fitur & kabar otomatis',          null,                    '/info'],
            '/tutorial'  => ['Panduan pemakaian, per topik',              null,                    '/tutorial'],
            '/tautkan'   => ['Tautkan akun Telegram ke akun aplikasi',     null,                    '/tautkan ABC123'],
            '/produksi'  => ['Ringkasan produksi hari ini',                'riwayat-produksi',      '/produksi'],
            '/target'    => ['Progres target produksi yang sedang aktif',  'targets',               '/target'],
            '/reject'    => ['Produk dengan reject tertinggi hari ini',    'laporan',               '/reject'],
            '/pengganti' => ['Barang pengganti yang belum selesai',        'barang-pengganti',      '/pengganti'],
            '/jadwal'    => ['Lihat jadwal minggu ini (unggah: /jadwal upload)', 'targets',         '/jadwal'],
            '/gambar'    => ['Unggah gambar kerja (dituntun bertahap)',    'gambar-kerja.upload',   '/gambar'],
            '/lapor'     => ['Catat hasil produksi',                       'input-produksi.create', '/lapor <seri> <qty> [reject]'],
            '/batal'     => ['Batalkan unggahan yang sedang berjalan',     null,                    '/batal'],
        ];
    }

    /** @return array<int,array> daftar balasan untuk dikirim */
    public function tangani(array $message): array
    {
        $teks     = trim((string) ($message['text'] ?? ''));
        $fromId   = isset($message['from']['id']) ? (string) $message['from']['id'] : null;
        $chatId   = (string) ($message['chat']['id'] ?? '');
        $pengirim = PenautanAkun::pengguna($fromId);

        // Berkas (foto/dokumen) hanya berarti kalau ada sesi unggah berjalan.
        if ($teks === '') {
            return $this->tanganiBerkas($message, $fromId);
        }

        [$perintah, $argumen] = $this->pisah($teks);

        if ($perintah === null) {
            // Bukan perintah. Kalau orangnya sedang dituntun mengisi sesuatu,
            // jawabannya diterima langsung — tidak perlu mengetik perintah lagi.
            $sesi     = $fromId ? Cache::get($this->kunciSesi($fromId)) : null;
            $menunggu = in_array($sesi['langkah'] ?? '', ['judul', 'seri'], true);
            $pribadi  = ($message['chat']['type'] ?? '') === 'private';

            if ($sesi && ($menunggu || $pribadi)) {
                return $this->lanjutkanSesi($sesi, $teks, $fromId);
            }

            return [];   // obrolan biasa di grup — didiamkan, bukan dibalas
        }

        if (! array_key_exists($perintah, static::daftar())) {
            return [$this->teks(
                'Perintah ' . Teks::kode($perintah) . " tidak dikenal.\n\n"
                . 'Ketik ' . Teks::kode('/help') . ' untuk melihat daftar perintah.'
            )];
        }

        // /tautkan satu-satunya yang boleh dipakai orang yang belum tertaut —
        // kalau tidak, tidak akan pernah ada yang bisa menautkan akunnya.
        if ($perintah === '/tautkan') {
            return $this->tautkan($argumen, $fromId);
        }

        if (! $this->bolehMemakai($pengirim, $chatId)) {
            return [$this->belumTertaut()];
        }

        return match ($perintah) {
            '/help'      => [$this->bantuan($pengirim)],
            '/info'      => [$this->teks(Panduan::info($pengirim, $this->setting))],
            '/tutorial'  => $this->tutorial($argumen, $pengirim),
            '/batal'     => $this->batal($fromId),
            '/produksi'  => $this->baca($pengirim, 'riwayat-produksi', fn () => Ringkasan::produksiHariIni(), 'production.index'),
            '/target'    => $this->baca($pengirim, 'targets',          fn () => Ringkasan::targetAktif(), 'production.targets.index'),
            '/reject'    => $this->baca($pengirim, 'laporan',          fn () => Ringkasan::rejectTertinggi(), 'reports.index'),
            '/pengganti' => $this->baca($pengirim, 'barang-pengganti', fn () => Ringkasan::penggantiBelumSelesai(), 'replacements.index'),
            '/jadwal'    => $this->jadwal($argumen, $pengirim, $fromId),
            '/gambar'    => $this->mulaiGambar($argumen, $pengirim, $fromId),
            '/lapor'     => $this->lapor($argumen, $pengirim),
            default      => [],
        };
    }

    /* ───────────────────────── Otorisasi ───────────────────────── */

    /** Boleh memakai bot sama sekali: tertaut, atau dari grup yang terdaftar. */
    private function bolehMemakai(?User $pengirim, string $chatId): bool
    {
        return $pengirim !== null || $this->chatTerdaftar($chatId);
    }

    private function chatTerdaftar(string $chatId): bool
    {
        $terdaftar = array_map('strval', array_filter([
            $this->setting->telegram_chat_id,
            $this->setting->telegram_report_chat_id,
        ]));

        return $chatId !== '' && in_array($chatId, $terdaftar, true);
    }

    /**
     * Perintah baca: orang tertaut dinilai lewat izinnya di aplikasi. Grup yang
     * terdaftar dipercaya apa adanya — memang grup internal, dan isinya sama
     * dengan yang sudah dikirim bot sebagai notifikasi rutin.
     */
    private function baca(?User $pengirim, string $izin, callable $isi, ?string $route = null): array
    {
        if ($pengirim && ! MenuAccess::can($pengirim, $izin)) {
            return [$this->teks('Anda tidak punya izin untuk data ini.')];
        }

        $balasan = $this->teks($isi());

        if ($route && $tombol = Tautan::tombol('Buka di aplikasi', $route)) {
            $balasan['tombol'] = $tombol;
        }

        return [$balasan];
    }

    /** Perintah tulis: wajib tertaut DAN berizin. Dari grup saja tidak cukup. */
    private function periksaTulis(?User $pengirim, string $izin): ?array
    {
        if (! $pengirim) {
            return [$this->teks(
                "Perintah ini mengubah data, jadi bot harus tahu Anda siapa.\n\n"
                . 'Tautkan akun dulu lewat ' . Teks::tebal('Profil') . ' di aplikasi, lalu kirim '
                . Teks::kode('/tautkan KODE') . ".\n\n"
                . '<i>Penautan khusus ' . PenautanAkun::daftarPeran() . '.</i>'
            )];
        }

        if (! MenuAccess::can($pengirim, $izin)) {
            return [$this->teks('Anda tidak punya izin untuk melakukan ini.')];
        }

        return null;
    }

    private function belumTertaut(): array
    {
        return $this->teks(
            "Akun Telegram Anda belum tertaut.\n\n"
            . 'Buka ' . Teks::tebal('Profil') . ' di aplikasi, tekan ' . Teks::tebal('Tautkan Telegram')
            . ", lalu kirim ke sini:\n" . Teks::kode('/tautkan KODE') . "\n\n"
            . '<i>Penautan khusus ' . PenautanAkun::daftarPeran() . '.</i>'
        );
    }

    /* ───────────────────────── Perintah ───────────────────────── */

    private function tautkan(string $argumen, ?string $fromId): array
    {
        if (! $fromId) {
            return [$this->teks('Tidak bisa mengenali akun Telegram Anda.')];
        }

        $kode = trim($argumen);

        if ($kode === '') {
            return [$this->teks(
                "Kirim bersama kodenya, contoh:\n" . Teks::kode('/tautkan ABC123') . "\n\n"
                . 'Kodenya diambil dari menu ' . Teks::tebal('Profil') . ' di aplikasi.'
            )];
        }

        $user = PenautanAkun::tukarkan($kode, $fromId);

        if (! $user) {
            return [$this->teks(
                "Kode tidak dikenal atau sudah kedaluwarsa.\n\n"
                . 'Kode hanya berlaku ' . PenautanAkun::BERLAKU_MENIT
                . ' menit. Ambil kode baru di menu ' . Teks::tebal('Profil') . ".\n\n"
                . '<i>Penautan khusus ' . PenautanAkun::daftarPeran() . '.</i>'
            )];
        }

        return [$this->teks(
            '✅ Akun tertaut: ' . Teks::tebal($user->name) . ' (' . Teks::aman($user->role) . ").\n\n"
            . 'Ketik ' . Teks::kode('/help') . ' untuk melihat apa saja yang bisa Anda lakukan dari sini.'
        )];
    }

    private function bantuan(?User $pengirim): array
    {
        $baris = ['<b>Perintah yang tersedia</b>', ''];

        foreach (static::daftar() as [$ket, $izin, $contoh]) {
            // Perintah yang pasti ditolak tidak perlu ditampilkan — daftar yang
            // penuh pilihan mati hanya membingungkan.
            if ($izin && $pengirim && ! MenuAccess::can($pengirim, $izin)) {
                continue;
            }

            $baris[] = Teks::kode($contoh);
            $baris[] = '   ' . Teks::aman($ket);
        }

        if (! $pengirim) {
            $baris[] = '';
            $baris[] = '<i>Akun Anda belum tertaut, jadi perintah yang mengubah data belum bisa dipakai.</i>';
        }

        return $this->teks(implode("\n", $baris));
    }

    /**
     * /jadwal            → lihat jadwal minggu ini
     * /jadwal depan      → minggu depan
     * /jadwal 2026-09-22 → minggu yang memuat tanggal itu
     * /jadwal upload ... → mulai unggah untuk minggu tersebut
     */
    private function jadwal(string $argumen, ?User $pengirim, ?string $fromId): array
    {
        $argumen = trim($argumen);

        if (str_starts_with(strtolower($argumen), 'upload')) {
            return $this->mulaiJadwal(trim(substr($argumen, 6)), $pengirim, $fromId);
        }

        if ($pengirim && ! MenuAccess::can($pengirim, 'targets')) {
            return [$this->teks('Anda tidak punya izin untuk melihat jadwal.')];
        }

        return Ringkasan::jadwal($this->mingguDari($argumen));
    }

    /**
     * Terjemahkan argumen minggu: kosong = minggu ini, "depan" = minggu depan,
     * selain itu tanggal apa pun yang lalu dibulatkan ke hari Senin minggunya.
     */
    private function mingguDari(string $argumen): string
    {
        $argumen = strtolower(trim($argumen));

        if (in_array($argumen, ['depan', 'next'], true)) {
            return Tanggal::awalMinggu(now()->addWeek()->toDateString());
        }

        if (in_array($argumen, ['lalu', 'last'], true)) {
            return Tanggal::awalMinggu(now()->subWeek()->toDateString());
        }

        return Tanggal::awalMinggu(Tanggal::urai($argumen));
    }

    private function mulaiJadwal(string $argumen, ?User $pengirim, ?string $fromId): array
    {
        if ($tolak = $this->periksaTulis($pengirim, 'targets.edit')) {
            return $tolak;
        }

        if (! $fromId) {
            return [$this->teks('Tidak bisa mengenali akun Telegram Anda.')];
        }

        $minggu = $this->mingguDari($argumen);

        $this->simpanSesi($fromId, [
            'jenis'   => 'jadwal',
            'user_id' => $pengirim->id,
            'langkah' => 'berkas',
            'tanggal' => $minggu,
        ]);

        // Rentangnya disebut jelas karena foto jadwal berlaku SEMINGGU, bukan
        // sehari. Bot lama menyimpannya dengan kunci tanggal hari ini, sehingga
        // halaman Target Produksi — yang mencarinya di tanggal Senin — tidak
        // pernah menemukannya, dan fotonya seolah hilang keesokan harinya.
        return [$this->teks(
            "<b>Unggah Jadwal</b>\n\n"
            . 'Untuk minggu: ' . Teks::tebal(Tanggal::rentangMinggu($minggu)) . "\n\n"
            . 'Silakan kirim ' . Teks::tebal('foto') . ' atau ' . Teks::tebal('berkas PDF') . " sekarang.\n\n"
            . "<i>Jadwal berlaku satu minggu penuh dan dibersihkan otomatis tiap Senin.</i>\n"
            . '<i>Minggu lain: ' . Teks::kode('/jadwal upload depan') . ' · Batal: ' . Teks::kode('/batal') . '</i>'
        )];
    }

    /* ─────────────────── Gambar kerja: dituntun bertahap ─────────────────── */

    public const KATEGORI = ['pln' => 'PLN', 'swasta' => 'Swasta', 'typetest' => 'Type Test'];

    /**
     * Dulu bot hanya menerima judul, lalu mengunci kategori ke "pln" dan
     * mengosongkan seri/KVA — padahal ketiganya dipakai aplikasi untuk
     * mengelompokkan gambar. Akibatnya unggahan dari Telegram tidak pernah
     * sejajar dengan yang diunggah lewat halaman web.
     */
    private function mulaiGambar(string $argumen, ?User $pengirim, ?string $fromId): array
    {
        if ($tolak = $this->periksaTulis($pengirim, 'gambar-kerja.upload')) {
            return $tolak;
        }

        if (! $fromId) {
            return [$this->teks('Tidak bisa mengenali akun Telegram Anda.')];
        }

        $judul = trim($argumen);
        $sesi  = ['jenis' => 'gambar', 'user_id' => $pengirim->id];

        if ($judul === '') {
            $sesi['langkah'] = 'judul';
            $this->simpanSesi($fromId, $sesi);

            return [$this->teks(
                "<b>Unggah Gambar Kerja</b> (1/4)\n\n"
                . "Ketik <b>judul</b> gambarnya.\n"
                . 'Contoh: ' . Teks::kode('Trafo Distribusi Jakarta') . "\n\n"
                . '<i>Batal: ' . Teks::kode('/batal') . '</i>'
            )];
        }

        $sesi['judul']   = $judul;
        $sesi['langkah'] = 'kategori';
        $this->simpanSesi($fromId, $sesi);

        return [$this->tanyaKategori($judul)];
    }

    private function tanyaKategori(string $judul): array
    {
        $tombol = [array_map(
            fn ($kunci, $label) => ['text' => $label, 'callback_data' => 'kat:' . $kunci],
            array_keys(self::KATEGORI),
            array_values(self::KATEGORI),
        )];

        return [
            'jenis'  => 'teks',
            'teks'   => "<b>Unggah Gambar Kerja</b> (2/4)\n\n"
                      . 'Judul: ' . Teks::tebal($judul) . "\n\n"
                      . 'Pilih <b>kategori seri</b>:',
            'tombol' => $tombol,
        ];
    }

    private function tanyaSeri(array $sesi): array
    {
        return $this->teks(
            "<b>Unggah Gambar Kerja</b> (3/4)\n\n"
            . 'Kategori: ' . Teks::tebal(self::KATEGORI[$sesi['kategori']] ?? '-') . "\n\n"
            . "Ketik <b>seri</b> dan <b>KVA</b>, dipisah spasi.\n"
            . 'Contoh: ' . Teks::kode('26T0282061 4000') . "\n\n"
            . 'Ketik ' . Teks::kode('-') . ' kalau tidak ada.'
        );
    }

    private function tanyaBerkas(array $sesi): array
    {
        $baris = [
            '<b>Unggah Gambar Kerja</b> (4/4)',
            '',
            'Judul: ' . Teks::tebal($sesi['judul']),
            'Kategori: ' . Teks::tebal(self::KATEGORI[$sesi['kategori']] ?? '-'),
        ];

        if (! empty($sesi['seri'])) {
            $baris[] = 'Seri: ' . Teks::tebal($sesi['seri'])
                     . (! empty($sesi['kva']) ? ' · ' . Teks::tebal($sesi['kva'] . ' KVA') : '');
        }

        if (! empty($sesi['tahun'])) {
            $baris[] = 'Tahun: ' . Teks::tebal((string) $sesi['tahun']);
        }

        $baris[] = '';
        $baris[] = 'Silakan kirim ' . Teks::tebal('foto') . ' atau ' . Teks::tebal('berkas PDF') . ' sekarang.';
        $baris[] = '<i>Boleh beberapa berkas — sesi tetap terbuka sampai Anda ketik /batal.</i>';

        return $this->teks(implode("\n", $baris));
    }

    /**
     * Pesan teks biasa saat ada sesi yang sedang menunggu isian.
     *
     * Tanpa ini, orang yang sedang dituntun harus tetap mengetik perintah —
     * padahal yang alami adalah langsung menjawab pertanyaannya.
     */
    private function lanjutkanSesi(array $sesi, string $teks, string $fromId): array
    {
        $langkah = $sesi['langkah'] ?? 'berkas';

        if ($langkah === 'judul') {
            $sesi['judul']   = trim($teks);
            $sesi['langkah'] = 'kategori';
            $this->simpanSesi($fromId, $sesi);

            return [$this->tanyaKategori($sesi['judul'])];
        }

        if ($langkah === 'seri') {
            [$seri, $kva] = $this->uraiSeri($teks);

            $sesi['seri'] = $seri;
            $sesi['kva']  = $kva;

            // Dua angka pertama seri = tahun, aturan yang sama dipakai halaman
            // Input Produksi untuk kategori berseri manual (26T... = 2026).
            $sesi['tahun'] = ($seri && preg_match('/^(\d{2})/', $seri, $m))
                ? 2000 + (int) $m[1]
                : null;

            $sesi['langkah'] = 'berkas';
            $this->simpanSesi($fromId, $sesi);

            return [$this->tanyaBerkas($sesi)];
        }

        // Sedang menunggu berkas, tapi yang datang teks biasa.
        return [$this->teks(
            'Masih menunggu ' . Teks::tebal('foto') . ' atau ' . Teks::tebal('berkas PDF')
            . ".\n\nKetik " . Teks::kode('/batal') . ' kalau mau berhenti.'
        )];
    }

    /** "26T0282061 4000" atau "26T0282061 4000KVA" → ["26T0282061", "4000"] */
    private function uraiSeri(string $teks): array
    {
        $teks = trim($teks);

        if ($teks === '' || $teks === '-') {
            return [null, null];
        }

        $bagian = preg_split('/\s+/', $teks, 2);
        $seri   = $bagian[0];
        $kva    = isset($bagian[1]) ? trim($bagian[1]) : null;

        if ($kva !== null) {
            $kva = trim(preg_replace('/kva/i', '', $kva)) ?: null;
        }

        return [$seri, $kva];
    }

    /**
     * /tutorial          → daftar topik berupa tombol
     * /tutorial jadwal   → langsung ke topiknya
     */
    private function tutorial(string $argumen, ?User $pengirim): array
    {
        $kunci = strtolower(trim($argumen));

        if ($kunci === '') {
            return [Panduan::daftarTutorial($pengirim)];
        }

        $isi = Panduan::isiTopik($kunci, $pengirim);

        if ($isi === null) {
            $ada = implode(', ', array_map(
                fn ($k) => '<code>' . $k . '</code>',
                array_keys(Panduan::topik()),
            ));

            return [$this->teks(
                'Topik ' . Teks::kode($kunci) . " tidak dikenal.

Yang ada: " . $ada
            )];
        }

        return [$this->teks($isi)];
    }

    /** Penekanan tombol: kategori gambar kerja, atau topik tutorial. */
    public function tanganiTombol(array $callback): array
    {
        $data   = (string) ($callback['data'] ?? '');
        $fromId = isset($callback['from']['id']) ? (string) $callback['from']['id'] : null;

        if (! $fromId) {
            return [];
        }

        if (str_starts_with($data, 'tut:')) {
            $pengirim = PenautanAkun::pengguna($fromId);
            $isi      = Panduan::isiTopik(substr($data, 4), $pengirim);

            return $isi === null ? [] : [$this->teks($isi)];
        }

        if (! str_starts_with($data, 'kat:')) {
            return [];
        }

        $sesi = Cache::get($this->kunciSesi($fromId));

        if (! $sesi || ($sesi['jenis'] ?? null) !== 'gambar') {
            return [$this->teks('Sesi unggahnya sudah berakhir. Mulai lagi dengan ' . Teks::kode('/gambar') . '.')];
        }

        $kategori = substr($data, 4);

        if (! array_key_exists($kategori, self::KATEGORI)) {
            return [];
        }

        $sesi['kategori'] = $kategori;
        $sesi['langkah']  = 'seri';
        $this->simpanSesi($fromId, $sesi);

        return [$this->tanyaSeri($sesi)];
    }

    private function simpanSesi(string $fromId, array $sesi): void
    {
        Cache::put($this->kunciSesi($fromId), $sesi, now()->addMinutes(self::SESI_MENIT));
    }

    private function batal(?string $fromId): array
    {
        if ($fromId) {
            Cache::forget($this->kunciSesi($fromId));
        }

        return [$this->teks('Unggahan dibatalkan.')];
    }

    private function lapor(string $argumen, ?User $pengirim): array
    {
        if ($tolak = $this->periksaTulis($pengirim, 'input-produksi.create')) {
            return $tolak;
        }

        return [$this->teks(Pelaporan::catat($argumen, $pengirim))];
    }

    /* ───────────────────────── Berkas masuk ───────────────────────── */

    private function tanganiBerkas(array $message, ?string $fromId): array
    {
        if (! $fromId) {
            return [];
        }

        $kunci = $this->kunciSesi($fromId);
        $sesi  = Cache::get($kunci);

        if (! $sesi) {
            return [];   // tidak ada sesi berjalan — kiriman itu bukan untuk bot
        }

        $berkas = Berkas::dariPesan($message);

        if ($berkas === null) {
            return [$this->teks(
                'Hanya ' . Teks::tebal('foto') . ' atau ' . Teks::tebal('berkas PDF')
                . ' yang bisa diterima, maksimal 20 MB.'
            )];
        }

        // Pengunduhan dikerjakan pemanggil (butuh jaringan), bukan di sini.
        return [[
            'jenis'  => 'unduh',
            'berkas' => $berkas,
            'sesi'   => $sesi,
            'kunci'  => $kunci,
            // Gambar kerja sering terdiri dari beberapa lembar, jadi sesinya
            // dibiarkan terbuka sampai ditutup sendiri dengan /batal. Jadwal
            // hanya satu berkas per minggu, jadi langsung ditutup.
            'tutup'  => ($sesi['jenis'] ?? '') !== 'gambar',
        ]];
    }

    private function kunciSesi(string $fromId): string
    {
        return "tg_sesi_{$fromId}";
    }

    /* ───────────────────────── Bantuan kecil ───────────────────────── */

    /** Pisahkan "/jadwal upload 2026-01-01" menjadi ["/jadwal", "upload 2026-01-01"]. */
    private function pisah(string $teks): array
    {
        if (! str_starts_with($teks, '/')) {
            return [null, ''];
        }

        $bagian   = preg_split('/\s+/', $teks, 2);
        $perintah = strtolower($bagian[0]);

        // Di grup, Telegram menambahkan nama bot: "/help@qcbot".
        if (str_contains($perintah, '@')) {
            $perintah = substr($perintah, 0, strpos($perintah, '@'));
        }

        return [$perintah, $bagian[1] ?? ''];
    }

    private function teks(string $isi): array
    {
        return ['jenis' => 'teks', 'teks' => $isi];
    }
}
