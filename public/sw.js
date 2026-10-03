/* Service Worker Asata Production
 *
 * Tugasnya menyimpan berkas gambar kerja (gambar & PDF) di Cache Storage
 * browser. Berkas yang sudah pernah dibuka akan dilayani langsung dari
 * penyimpanan lokal — tidak diunduh ulang meski browser ditutup, komputer
 * dimatikan, atau pengguna keluar lalu masuk lagi.
 *
 * Cache Storage dipilih (bukan sekadar cache HTTP biasa) karena isinya
 * bertahan dan tidak ikut terbuang saat browser membersihkan cache sementara.
 */

const CACHE = 'asata-berkas-v1';

// Batas ruang: berkas raksasa tidak disimpan, dan total cache dijaga agar
// tidak memenuhi disk. Bila penuh, entri terlama dibuang lebih dulu.
const BATAS_TOTAL = 400 * 1024 * 1024;
const BATAS_BERKAS = 120 * 1024 * 1024;

// Hanya URL berkas (route storage.file) dan thumbnail kartu (storage.thumb)
// yang ditangani — keduanya tidak pernah berubah isinya. Diambil relatif
// terhadap lokasi sw.js supaya ikut benar saat aplikasi dipasang di subfolder.
// Nama cache sengaja TIDAK diganti: menggantinya menghapus semua berkas yang
// sudah tersimpan di perangkat pengguna.
const AWALAN = [
    new URL('./file/', self.location).pathname,
    new URL('./thumb/', self.location).pathname,
];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const nama = await caches.keys();
            await Promise.all(
                nama
                    .filter((n) => n.startsWith('asata-berkas-') && n !== CACHE)
                    .map((n) => caches.delete(n)),
            );
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    let url;
    try {
        url = new URL(req.url);
    } catch {
        return;
    }

    if (url.origin !== self.location.origin) return;
    if (!AWALAN.some((awal) => url.pathname.startsWith(awal))) return;

    event.respondWith(layani(req, url));
});

// Unduhan yang sedang berjalan, dikunci per berkas. Pemutar PDF bawaan browser
// meminta beberapa potongan berkas yang sama sekaligus; tanpa kunci ini setiap
// permintaan akan mengunduh sendiri-sendiri dan saling menimpa saat menyimpan.
const berjalan = new Map();

/**
 * Cache dulu, jaringan belakangan. Berkas hanya diunduh sekali; permintaan
 * berikutnya (termasuk saat browser dibuka lagi) dilayani dari lokal.
 */
async function layani(req, url) {
    const kunci = url.pathname;
    const rentang = req.headers.get('range');

    try {
        const cache = await caches.open(CACHE);
        let simpanan = await cache.match(kunci);

        if (!simpanan) {
            await pastikanTersimpan(cache, url, kunci);
            simpanan = await cache.match(kunci);
        }

        // Gagal disimpan (misalnya berkas terlalu besar) — ambil dari server.
        if (!simpanan) return fetch(req);

        return rentang ? potong(simpanan, rentang) : simpanan;
    } catch {
        // Apa pun yang gagal di sini tidak boleh membuat berkas gagal dibuka.
        return fetch(req);
    }
}

/** Unduh satu berkas utuh lalu simpan — hanya sekali walau diminta berkali-kali. */
function pastikanTersimpan(cache, url, kunci) {
    const adaTugas = berjalan.get(kunci);
    if (adaTugas) return adaTugas;

    const tugas = (async () => {
        // Selalu diminta utuh (tanpa header Range) supaya yang tersimpan adalah
        // berkas lengkap, bukan potongan.
        const respons = await fetch(url.href, { credentials: 'same-origin' });
        if (!respons.ok || respons.status !== 200) return;

        const besar = Number(respons.headers.get('content-length') || 0);
        if (besar && besar > BATAS_BERKAS) return;

        try {
            await cache.put(kunci, respons);
        } catch (e) {
            // "Entry already exists" berarti berkas sudah tersimpan — tidak apa-apa.
            if (e?.name !== 'InvalidAccessError') throw e;
        }

        await rapikan(cache);
    })()
        .catch(() => {})
        .finally(() => berjalan.delete(kunci));

    berjalan.set(kunci, tugas);

    return tugas;
}

/**
 * Pemutar PDF bawaan browser meminta potongan berkas (header Range). Karena
 * yang tersimpan adalah berkas utuh, potongannya dibuat di sini.
 */
async function potong(respons, rentang) {
    const buf = await respons.arrayBuffer();
    const total = buf.byteLength;
    const cocok = /bytes=(\d*)-(\d*)/.exec(rentang);

    if (!cocok) return new Response(buf, { status: 200, headers: respons.headers });

    let mulai = cocok[1] ? parseInt(cocok[1], 10) : 0;
    let akhir = cocok[2] ? parseInt(cocok[2], 10) : total - 1;

    // Bentuk "bytes=-500" berarti 500 byte terakhir.
    if (!cocok[1] && cocok[2]) {
        mulai = Math.max(0, total - parseInt(cocok[2], 10));
        akhir = total - 1;
    }

    akhir = Math.min(akhir, total - 1);

    if (mulai > akhir || mulai >= total) {
        return new Response(null, {
            status: 416,
            headers: { 'Content-Range': 'bytes */' + total },
        });
    }

    const bagian = buf.slice(mulai, akhir + 1);

    return new Response(bagian, {
        status: 206,
        statusText: 'Partial Content',
        headers: {
            'Content-Type': respons.headers.get('content-type') || 'application/octet-stream',
            'Content-Length': String(bagian.byteLength),
            'Content-Range': 'bytes ' + mulai + '-' + akhir + '/' + total,
            'Accept-Ranges': 'bytes',
        },
    });
}

/** Buang entri terlama sampai total cache kembali di bawah batas. */
async function rapikan(cache) {
    const kunci = await cache.keys();
    const isi = [];
    let total = 0;

    for (const k of kunci) {
        const r = await cache.match(k);
        const n = Number(r?.headers.get('content-length') || 0);
        isi.push({ k, n });
        total += n;
    }

    for (const e of isi) {
        if (total <= BATAS_TOTAL) break;
        await cache.delete(e.k);
        total -= e.n;
    }
}
