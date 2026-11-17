/**
 * Penyimpanan berkas gambar kerja di browser.
 *
 * Service worker (public/sw.js) yang benar-benar menyimpan dan melayani
 * berkasnya; modul ini hanya mendaftarkannya dan menyediakan cara bagi halaman
 * untuk melihat berkas apa saja yang sudah tersimpan serta membersihkannya.
 */

export const NAMA_CACHE = 'asata-berkas-v1';

/**
 * Daftarkan service worker. URL-nya diambil dari <meta name="sw-url"> supaya
 * tetap benar ketika aplikasi dipasang di subfolder seperti
 * /asata-production/public.
 */
export function daftarkanServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    const meta = document.querySelector('meta[name="sw-url"]');
    const url = meta?.content;
    if (!url) return;

    // Scope = folder tempat sw.js berada, yaitu akar aplikasi.
    const scope = url.replace(/sw\.js$/, '');

    navigator.serviceWorker.register(url, { scope }).catch(() => {});

    // Minta browser memperlakukan penyimpanan ini sebagai permanen supaya tidak
    // dibuang otomatis saat disk menipis.
    navigator.storage?.persist?.().catch(() => {});
}

/** Berapa banyak dari daftar URL ini yang sudah tersimpan di browser. */
export async function berkasTersimpan(urls) {
    if (!('caches' in window) || !urls.length) return { jumlah: 0, byte: 0, set: new Set() };

    try {
        const cache = await caches.open(NAMA_CACHE);
        const set = new Set();
        let byte = 0;

        await Promise.all(
            urls.map(async (u) => {
                const kunci = new URL(u, window.location.href).pathname;
                const res = await cache.match(kunci);
                if (!res) return;
                set.add(u);
                byte += Number(res.headers.get('content-length') || 0);
            }),
        );

        return { jumlah: set.size, byte, set };
    } catch {
        return { jumlah: 0, byte: 0, set: new Set() };
    }
}

/** Hapus seluruh berkas yang tersimpan di browser ini. */
export async function bersihkanBerkas() {
    if (!('caches' in window)) return false;

    try {
        return await caches.delete(NAMA_CACHE);
    } catch {
        return false;
    }
}

/** 4,1 MB · 820 KB · 240 B, dan seterusnya. */
export function ukuran(byte) {
    if (!byte) return '0 B';
    if (byte < 1024) return byte + ' B';
    if (byte < 1024 * 1024) return Math.round(byte / 1024) + ' KB';
    return (byte / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
}

/** Batas total cache di public/sw.js — lebih dari ini, berkas terlama dibuang. */
export const BATAS_CACHE = 400 * 1024 * 1024;

/**
 * Alasan penyimpanan di perangkat tidak bisa dipakai, atau null bila bisa.
 * Service worker hanya ada di https:// atau localhost.
 */
export function alasanTakBisaSimpan() {
    if (!window.isSecureContext) {
        return 'Hanya bisa lewat alamat https:// — buka aplikasi lewat domainnya, bukan alamat IP.';
    }
    if (!('serviceWorker' in navigator) || !('caches' in window)) {
        return 'Browser ini tidak mendukung penyimpanan di perangkat.';
    }
    return null;
}

/**
 * Unduh daftar berkas supaya tersimpan di perangkat. Cukup memintanya lewat
 * fetch(): service worker yang menyimpan, dan tiap berkas hanya diunduh sekali.
 * Isi responsnya tidak dibaca (sudah ada di cache), jadi tidak makan memori.
 *
 * @param {Array<{url:string, ukuran:number}>} daftar
 * @param {{serentak?:number, sinyal?:AbortSignal, onKemajuan?:Function}} opsi
 */
export async function unduhSemua(daftar, { serentak = 3, sinyal, onKemajuan } = {}) {
    let selesai = 0;
    let byte = 0;
    let gagal = 0;
    let i = 0;

    async function pekerja() {
        while (i < daftar.length && !sinyal?.aborted) {
            const b = daftar[i++];
            try {
                const res = await fetch(b.url, { credentials: 'same-origin', signal: sinyal });
                if (!res.ok) throw new Error(String(res.status));
                await res.body?.cancel?.();
                byte += b.ukuran || 0;
            } catch (e) {
                if (sinyal?.aborted) return;
                gagal++;
            }
            selesai++;
            onKemajuan?.({ selesai, byte, gagal, total: daftar.length });
        }
    }

    await Promise.all(Array.from({ length: Math.min(serentak, daftar.length) }, pekerja));

    return { selesai, byte, gagal };
}
