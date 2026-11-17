import { Component } from 'react';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { daftarkanServiceWorker } from './offlineFiles';
import { pantauCsrf } from './csrf';

const appName = import.meta.env.VITE_APP_NAME || 'Asata Production';

/*
 * Pemulih layar kosong. Dua penyebab yang dijaga:
 *  1. Berkas JS halaman gagal dimuat (jaringan putus sesaat, atau tab lama setelah deploy: nama berkas berganti).
 *  2. Galat saat menggambar halaman: tanpa pembatas, React membongkar SELURUH pohon dan yang tersisa hanya latar.
 * Pemulihan = muat ulang halaman tujuan, paling banyak sekali per 20 detik (tanpa itu, galat tetap bisa memutar tanpa henti).
 */
let urlTujuan = null;
function segarkanSekali() {
    try {
        const t = Number(sessionStorage.getItem('au:segar')) || 0;
        if (Date.now() - t < 20000) return false;
        sessionStorage.setItem('au:segar', String(Date.now()));
    } catch (_) {
        /* tanpa sessionStorage: tetap segarkan */
    }
    window.location.assign(urlTujuan || window.location.href);
    return true;
}
router.on('before', (e) => {
    urlTujuan = e.detail.visit.method === 'get' ? e.detail.visit.url.href : null;
});
window.addEventListener('vite:preloadError', (e) => {
    e.preventDefault();
    segarkanSekali();
});
window.addEventListener('unhandledrejection', (e) => {
    if (/dynamically imported module|Importing a module script failed|Failed to fetch dynamically/i.test(String(e.reason && e.reason.message))) segarkanSekali();
});

class PembatasGalat extends Component {
    state = { galat: false };
    static getDerivedStateFromError() {
        return { galat: true };
    }
    componentDidCatch(err) {
        console.error('Halaman gagal digambar:', err);
        segarkanSekali();
    }
    render() {
        if (!this.state.galat) return this.props.children;
        return (
            <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 24, font: '14px system-ui, sans-serif', color: '#e8f0f2', background: '#0d1a20' }}>
                <div style={{ maxWidth: 360, textAlign: 'center' }}>
                    <p style={{ margin: '0 0 6px', fontSize: 16, fontWeight: 700 }}>Halaman belum bisa ditampilkan</p>
                    <p style={{ margin: '0 0 16px', opacity: 0.75 }}>Terjadi gangguan sesaat. Memuat ulang otomatis; bila tetap begini, tekan tombol di bawah.</p>
                    <button type="button" onClick={() => window.location.reload()} style={{ padding: '9px 18px', borderRadius: 999, border: 0, background: '#14b8a6', color: '#06231f', fontWeight: 700, cursor: 'pointer' }}>
                        Muat ulang
                    </button>
                </div>
            </div>
        );
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    // Semua halaman React tinggal di resources/js/Pages. Nama halaman yang
    // dikirim Inertia::render('Production/Index') dipetakan ke
    // resources/js/Pages/Production/Index.jsx.
    //
    // Dimuat per halaman (lazy), bukan sekaligus: dulu semua halaman — termasuk
    // Chart.js milik dashboard — ikut terunduh di setiap halaman (±680 KB),
    // terasa berat di HP & koneksi pabrik 10 Mbps.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        const page = pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Halaman Inertia tidak ditemukan: ./Pages/${name}.jsx`);
        }

        return page();
    },
    // (kegagalan memuat berkas halaman ditangani 'vite:preloadError' dan 'unhandledrejection' di atas)

    setup({ el, App, props }) {
        createRoot(el).render(
            <PembatasGalat>
                <App {...props} />
            </PembatasGalat>,
        );
    },

    progress: {
        color: '#3b82f6',
    },
});

// Berkas gambar kerja yang sudah dibuka disimpan di browser agar tidak diunduh
// ulang setiap kali aplikasi dibuka kembali.
daftarkanServiceWorker();

// Token CSRF diambil ulang saat aplikasi kembali aktif (cegah 419 di HP/PWA).
pantauCsrf(document.querySelector('meta[name=csrf-refresh]')?.content);
