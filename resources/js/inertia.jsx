import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { daftarkanServiceWorker } from './offlineFiles';
import { pantauCsrf } from './csrf';

const appName = import.meta.env.VITE_APP_NAME || 'Asata Production';

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

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
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
