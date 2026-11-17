/**
 * Token CSRF untuk request fetch() manual (halaman yang tidak lewat Inertia
 * router). Inertia sendiri memakai cookie XSRF-TOKEN lewat axios, yang ikut
 * diperbarui setiap respons.
 */
export function csrf() {
    const el = document.querySelector('meta[name=csrf-token]');
    return el ? el.content : '';
}

let sibuk = false;

/**
 * Ambil ulang token dari server lalu tulis ke <meta>. Dipakai saat halaman
 * kembali terlihat: PWA di HP bisa dibangunkan dari latar belakang setelah
 * sesi berganti, dan tanpa ini submit pertama ditolak 419 "Page Expired".
 */
export async function segarkanCsrf(url) {
    if (sibuk || !url) return;
    sibuk = true;
    try {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const data = res.ok ? await res.json() : null;
        if (data && data.token) {
            const meta = document.querySelector('meta[name=csrf-token]');
            if (meta) meta.setAttribute('content', data.token);
        }
    } catch (_) {
        /* offline — biarkan token lama */
    } finally {
        sibuk = false;
    }
}

/** Segarkan token setiap kali tab/aplikasi kembali aktif. */
export function pantauCsrf(url) {
    if (!url) return;
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') segarkanCsrf(url);
    });
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) segarkanCsrf(url);
    });
}
