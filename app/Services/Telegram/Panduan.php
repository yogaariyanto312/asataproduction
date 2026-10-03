<?php

namespace App\Services\Telegram;

use App\Models\BotSetting;
use App\Models\Changelog;
use App\Models\User;
use App\Support\MenuAccess;

/**
 * Isi perintah /info dan /tutorial.
 *
 * Dipisah dari BotPerintah karena sifatnya berbeda: yang di sini adalah
 * TULISAN yang sering berubah, bukan aturan. Menaruhnya di router membuat
 * berkas aturan ikut panjang dan sulit dibaca.
 *
 * Keduanya disaring dengan izin pemakainya — menampilkan fitur yang pasti
 * ditolak hanya membuat orang mencoba lalu kecewa.
 */
class Panduan
{
    /**
     * Topik tutorial: kunci => [label tombol, izin, penyusun isi].
     * izin null = boleh dibaca siapa pun yang boleh memakai bot.
     */
    public static function topik(): array
    {
        return [
            'tautkan' => ['Tautkan Akun',   null,                    'tautkan'],
            'tanya'   => ['Tanya Data',     'riwayat-produksi',      'tanya'],
            'jadwal'  => ['Jadwal Mingguan','targets',               'jadwal'],
            'gambar'  => ['Gambar Kerja',   'gambar-kerja.upload',   'gambar'],
            'lapor'   => ['Catat Produksi', 'input-produksi.create', 'lapor'],
            'kabar'   => ['Kabar Otomatis', null,                    'kabar'],
        ];
    }

    /* ─────────────────────────── /info ─────────────────────────── */

    public static function info(?User $pengirim, BotSetting $setting): string
    {
        $baris = [
            '🤖 ' . Teks::tebal('Asata Production System'),
            'Versi ' . Teks::aman(Changelog::versiAplikasi()),
            '',
        ];

        // Siapa yang bertanya — supaya jelas kenapa daftarnya bisa berbeda
        // antara satu orang dan lainnya.
        if ($pengirim) {
            $baris[] = 'Anda masuk sebagai ' . Teks::tebal($pengirim->name)
                     . ' (' . Teks::aman($pengirim->role) . ').';
        } else {
            $baris[] = '<i>Akun Telegram Anda belum tertaut, jadi daftar di bawah masih umum.</i>';
        }

        $baris[] = '';
        $baris[] = Teks::tebal('Yang bisa Anda lakukan di sini');

        $kelompok = [
            'Bertanya data' => [
                ['riwayat-produksi', '/produksi', 'ringkasan produksi hari ini'],
                ['targets',          '/target',   'progres target yang sedang berjalan'],
                ['laporan',          '/reject',   'produk dengan reject tertinggi'],
                ['barang-pengganti', '/pengganti','barang pengganti yang belum selesai'],
                ['targets',          '/jadwal',   'jadwal minggu ini, dikirim berkasnya'],
            ],
            'Mengubah data' => [
                ['targets.edit',           '/jadwal upload', 'unggah jadwal untuk satu minggu'],
                ['gambar-kerja.upload',    '/gambar',        'unggah gambar kerja, dituntun bertahap'],
                ['input-produksi.create',  '/lapor',         'catat hasil produksi dari lapangan'],
            ],
        ];

        foreach ($kelompok as $judul => $isi) {
            $boleh = array_filter(
                $isi,
                fn ($b) => ! $pengirim || MenuAccess::can($pengirim, $b[0])
            );

            if ($boleh === []) {
                continue;
            }

            $baris[] = '';
            $baris[] = '<u>' . Teks::aman($judul) . '</u>';

            foreach ($boleh as [, $perintah, $arti]) {
                $baris[] = '· ' . Teks::kode($perintah) . ' — ' . Teks::aman($arti);
            }
        }

        // Kabar otomatis hanya disebut kalau memang menyala — menjanjikan
        // notifikasi yang tidak pernah datang lebih buruk daripada diam.
        $baris[] = '';
        $baris[] = Teks::tebal('Kabar otomatis');

        if ($setting->telegram_enabled) {
            $baris[] = '· Ringkasan pagi, tiap hari 07:00';
            $baris[] = '· Pengingat catatan jatuh tempo, 07:05';
            $baris[] = '· Peringatan target tertinggal, 15:00 hari kerja';
            $baris[] = '· Laporan mingguan, Senin 07:30';

            if ($setting->report_enabled) {
                $baris[] = '· Laporan harian produksi, 22:00';
            }

            $baris[] = '· Peringatan saat reject melewati ' . Teks::tebal($setting->reject_threshold . '%');
        } else {
            $baris[] = '<i>Sedang dimatikan dari menu Settings.</i>';
        }

        $baris[] = '';
        $baris[] = 'Panduan langkah demi langkah: ' . Teks::kode('/tutorial');
        $baris[] = 'Daftar perintah singkat: ' . Teks::kode('/help');

        return implode("\n", $baris);
    }

    /* ───────────────────────── /tutorial ───────────────────────── */

    /** Daftar topik beserta tombolnya. */
    public static function daftarTutorial(?User $pengirim): array
    {
        $tersedia = array_filter(
            self::topik(),
            fn ($t) => $t[1] === null || ! $pengirim || MenuAccess::can($pengirim, $t[1])
        );

        $tombol = [];
        $baris  = [];

        // Dua tombol per baris supaya terbaca di layar ponsel.
        foreach (array_chunk($tersedia, 2, true) as $pasangan) {
            $tombol[] = array_map(
                fn ($kunci, $t) => ['text' => $t[0], 'callback_data' => 'tut:' . $kunci],
                array_keys($pasangan),
                array_values($pasangan),
            );
        }

        $teks = implode("\n", [
            '📘 ' . Teks::tebal('Tutorial Bot'),
            '',
            'Pilih topik yang ingin dipelajari.',
            '',
            '<i>Bisa juga langsung: ' . Teks::kode('/tutorial jadwal') . '</i>',
        ]);

        return ['jenis' => 'teks', 'teks' => $teks, 'tombol' => $tombol];
    }

    /** Isi satu topik, atau null kalau topiknya tidak dikenal. */
    public static function isiTopik(string $kunci, ?User $pengirim): ?string
    {
        $daftar = self::topik();

        if (! isset($daftar[$kunci])) {
            return null;
        }

        [$label, $izin] = $daftar[$kunci];

        if ($izin && $pengirim && ! MenuAccess::can($pengirim, $izin)) {
            return '📘 ' . Teks::tebal($label) . "\n\n"
                 . 'Anda tidak punya izin untuk fitur ini, jadi tutorialnya tidak akan berguna. '
                 . 'Minta Developer membukanya lewat menu Hak Akses Menu.';
        }

        return '📘 ' . Teks::tebal($label) . "\n\n" . self::{'tutorial' . ucfirst($kunci)}();
    }

    /* ───────────────────────── Isi tiap topik ───────────────────────── */

    private static function tutorialTautkan(): string
    {
        return implode("\n", [
            'Bot perlu tahu Anda siapa sebelum boleh mengubah data. Sekali saja, lalu selamanya.',
            '',
            '<i>Khusus ' . PenautanAkun::daftarPeran() . '.</i>',
            '',
            Teks::tebal('Langkahnya'),
            '1. Buka aplikasi, masuk ke menu ' . Teks::tebal('Profil') . '.',
            '2. Tekan ' . Teks::tebal('Tautkan Telegram') . '. Muncul kode 6 huruf.',
            '3. Kirim ke sini: ' . Teks::kode('/tautkan KODE'),
            '',
            '<i>Kode berlaku 10 menit dan hanya bisa dipakai sekali.</i>',
            '',
            'Kalau sudah tertaut, izin Anda di bot sama persis dengan izin di aplikasi — '
            . 'tidak ada pengaturan kedua yang perlu diurus.',
        ]);
    }

    private static function tutorialTanya(): string
    {
        return implode("\n", [
            'Semua perintah ini hanya membaca, tidak mengubah apa pun. Aman dipakai kapan saja.',
            '',
            Teks::kode('/produksi') . ' — total hari ini per kategori, plus reject dan persentasenya.',
            Teks::kode('/target') . ' — tiap target aktif: sudah berapa dari berapa, sisa berapa.',
            Teks::kode('/reject') . ' — produk dengan reject tertinggi hari ini.',
            Teks::kode('/pengganti') . ' — barang pengganti yang belum ditandai selesai.',
            '',
            '<i>Tiap jawaban membawa tombol "Buka di aplikasi" kalau ingin melihat detailnya.</i>',
        ]);
    }

    private static function tutorialJadwal(): string
    {
        return implode("\n", [
            Teks::tebal('Jadwal berlaku satu minggu penuh') . ', bukan per hari. '
            . 'Sistem menyimpannya di tanggal Senin, lalu membersihkannya otomatis tiap Senin 01:00.',
            '',
            Teks::tebal('Melihat'),
            Teks::kode('/jadwal') . ' — jadwal minggu ini, dikirim berkasnya langsung.',
            Teks::kode('/jadwal depan') . ' — minggu depan.',
            '',
            Teks::tebal('Mengunggah'),
            '1. Ketik ' . Teks::kode('/jadwal upload') . ' (atau ' . Teks::kode('/jadwal upload depan') . ').',
            '2. Bot menyebut rentang minggunya — pastikan benar.',
            '3. Kirim foto atau berkas PDF.',
            '',
            '<i>Satu minggu hanya punya satu jadwal; mengunggah lagi akan menggantikan yang lama.</i>',
        ]);
    }

    private static function tutorialGambar(): string
    {
        return implode("\n", [
            'Bot menuntun empat langkah supaya hasilnya sama rapi dengan unggahan lewat web.',
            '',
            '1. Ketik ' . Teks::kode('/gambar') . ' (boleh langsung ' . Teks::kode('/gambar Trafo PLN') . ').',
            '2. Ketik judulnya.',
            '3. Pilih kategori lewat tombol: PLN, Swasta, atau Type Test.',
            '4. Ketik seri dan KVA, contoh ' . Teks::kode('26T0282061 4000') . '. '
            . 'Ketik ' . Teks::kode('-') . ' kalau tidak ada.',
            '5. Kirim foto atau PDF.',
            '',
            Teks::tebal('Beberapa lembar sekaligus') . ': setelah berkas pertama tersimpan, '
            . 'sesinya tetap terbuka. Kirim terus sisanya, lalu ketik ' . Teks::kode('/batal') . ' untuk selesai.',
            '',
            '<i>Tahun terisi otomatis dari dua angka pertama seri (26T… = 2026).</i>',
        ]);
    }

    private static function tutorialLapor(): string
    {
        return implode("\n", [
            'Mencatat hasil produksi tanpa membuka aplikasi.',
            '',
            Teks::tebal('Produk biasa'),
            Teks::kode('/lapor <seri> <qty> [reject]'),
            'Contoh: ' . Teks::kode('/lapor 2601 12 1'),
            '',
            Teks::tebal('Produk Channel') . ' (UP dan BT dicatat terpisah)',
            Teks::kode('/lapor <seri> up <n> bt <n> [reject <n>]'),
            'Contoh: ' . Teks::kode('/lapor CH01 up 10 bt 8 reject 1'),
            '',
            'Kalau seri produk sudah ada isinya hari itu, angkanya ' . Teks::tebal('ditambahkan')
            . ' ke entri yang sama — sama seperti di halaman Input Produksi.',
            '',
            '<i>Kategori berseri manual sengaja ditolak di sini: kategori itu membuat produk baru '
            . 'otomatis dari seri yang diketik, jadi salah ketik lewat chat akan meninggalkan '
            . 'produk sampah yang permanen. Pakai aplikasi untuk yang itu.</i>',
        ]);
    }

    private static function tutorialKabar(): string
    {
        return implode("\n", [
            'Bot mengirim sendiri tanpa diminta:',
            '',
            '· ' . Teks::tebal('07:00') . ' ringkasan pagi — target yang belum tuntas & catatan jatuh tempo.',
            '· ' . Teks::tebal('07:05') . ' pengingat catatan, dikirim japri ke masing-masing orangnya.',
            '· ' . Teks::tebal('15:00') . ' peringatan target yang masih jauh tertinggal (hari kerja).',
            '· ' . Teks::tebal('Senin 07:30') . ' laporan mingguan beserta perbandingan minggu lalu.',
            '· ' . Teks::tebal('22:00') . ' laporan harian produksi.',
            '· Peringatan langsung saat reject sebuah produk melewati batas.',
            '',
            'Kalau tidak ada yang perlu dikabarkan, bot sengaja ' . Teks::tebal('diam') . ' — '
            . 'pesan rutin yang isinya "tidak ada apa-apa" cepat membuat orang berhenti membacanya.',
            '',
            '<i>Pengingat japri hanya sampai ke orang yang sudah menautkan akunnya.</i>',
        ]);
    }
}
