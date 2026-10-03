import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
// Halaman ini tidak memakai AppLayout, jadi CSS-nya diimpor sendiri.
import '../../css/asata-ui.css';

const GERIGI =
    'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z';
const KELUAR = 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1';
const pad = (n) => String(n).padStart(2, '0');

/**
 * Layar maintenance — dikirim MaintenanceMode untuk semua halaman selama
 * Settings → Maintenance aktif (selain developer). Tampilan mengikuti versi
 * Blade lama: ikon gerigi, lencana "Under Maintenance", pesan, hitung mundur
 * bila ada waktu selesai (lalu muat ulang otomatis), kartu akun, tombol keluar.
 */
export default function Maintenance({ message, until, untilLabel, user, logoutUrl }) {
    const [sisa, setSisa] = useState(() => (until ? new Date(until).getTime() - Date.now() : null));

    useEffect(() => {
        if (!until) return undefined;
        const akhir = new Date(until).getTime();
        const t = setInterval(() => {
            const d = akhir - Date.now();
            if (d <= 0) {
                clearInterval(t);
                window.location.reload();
                return;
            }
            setSisa(d);
        }, 1000);
        return () => clearInterval(t);
    }, [until]);

    // Tanpa waktu selesai: cek berkala apakah maintenance sudah dimatikan.
    useEffect(() => {
        if (until) return undefined;
        const t = setInterval(() => document.visibilityState === 'visible' && router.reload(), 60000);
        return () => clearInterval(t);
    }, [until]);

    const jam = sisa != null ? Math.max(0, Math.floor(sisa / 3600000)) : null;
    const menit = sisa != null ? Math.max(0, Math.floor((sisa % 3600000) / 60000)) : null;
    const detik = sisa != null ? Math.max(0, Math.floor((sisa % 60000) / 1000)) : null;

    return (
        <div className="au-mt-layar">
            <Head title="Maintenance" />
            <div className="au-mt-blob is-kiri" />
            <div className="au-mt-blob is-kanan" />
            <div className="au-mt-isi">
                <div className="au-mt-ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d={GERIGI} />
                    </svg>
                </div>
                <span className="au-mt-lencana">
                    <i />
                    Under Maintenance
                </span>
                <h1>Sistem Sedang Maintenance</h1>
                <p className="au-mt-pesan">{message}</p>

                {until ? (
                    <div className="au-mt-hitung">
                        <p className="au-mt-hitung-judul">Estimasi Selesai</p>
                        <p className="au-mt-hitung-tgl">{untilLabel}</p>
                        <div className="au-mt-angka">
                            <div>
                                <b>{jam != null ? pad(jam) : '--'}</b>
                                <small>Jam</small>
                            </div>
                            <span>:</span>
                            <div>
                                <b>{menit != null ? pad(menit) : '--'}</b>
                                <small>Menit</small>
                            </div>
                            <span>:</span>
                            <div>
                                <b className="is-detik">{detik != null ? pad(detik) : '--'}</b>
                                <small>Detik</small>
                            </div>
                        </div>
                    </div>
                ) : null}

                <div className="au-mt-akun">
                    <span>{(user?.name || '?').charAt(0).toUpperCase()}</span>
                    <div>
                        <p>{user?.name}</p>
                        <small>{user?.role}</small>
                    </div>
                </div>

                <button type="button" className="au-mt-keluar" onClick={() => router.post(logoutUrl)}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d={KELUAR} />
                    </svg>
                    Keluar
                </button>
            </div>
        </div>
    );
}
