import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * Settings → Keamanan: blokir klik kanan & pintasan DevTools (F12,
 * Ctrl+Shift+I/J/C, Ctrl+U) untuk semua selain developer — termasuk tamu di
 * halaman login. Nilai `disableDevtools` dibagikan server ke semua halaman.
 *
 * Pencegahan dasar saja: kode sisi peramban tetap bisa dibaca lewat cara lain.
 *
 * @param {(pesan: string) => void} [beriTahu] tampilkan pesan saat diblokir
 */
export function useBlokirDevtools(beriTahu) {
    const aktif = !!usePage().props.disableDevtools;
    const pesan = useRef(beriTahu);
    pesan.current = beriTahu;

    useEffect(() => {
        if (!aktif) return undefined;
        const ctx = (e) => {
            e.preventDefault();
            pesan.current?.('Klik kanan dinonaktifkan oleh Developer.');
        };
        const key = (e) => {
            const k = (e.key || '').toUpperCase();
            const diblok = e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(k)) || (e.ctrlKey && k === 'U');
            if (!diblok) return;
            e.preventDefault();
            e.stopPropagation();
            pesan.current?.('Pintasan ini diblokir oleh Developer.');
        };
        document.addEventListener('contextmenu', ctx);
        document.addEventListener('keydown', key, true);
        return () => {
            document.removeEventListener('contextmenu', ctx);
            document.removeEventListener('keydown', key, true);
        };
    }, [aktif]);
}
