import { useCallback, useEffect, useRef, useState } from 'react';
import TrafoMaskot from './TrafoMaskot';
import latarBuram from '../../images/login-latar-blur.webp';

/*
 * Layar pembuka sesudah masuk (PRD 12): trafo "menyala", petir mengisi daya, centang, lalu layar terbuka ke aplikasi.
 * Hanya tampil tepat sesudah login berhasil (penanda sessionStorage 'au:intro-baru' dari halaman masuk).
 * Tidak tampil bila dimatikan di Tampilan, mode ringan, atau pengguna memilih kurangi gerak (diputuskan di AppLayout).
 * Dimuat menyusul (lazy) hanya saat memang akan tampil.
 *
 * Mulus: cahaya petir naik lewat SATU transisi CSS (--lg-i), isi keluar lebih dulu lalu layar memudar,
 * dan aplikasi di belakangnya "memfokus" pelan. Lewati (tombol, Esc, Enter, Spasi) ikut menutup dengan
 * transisi yang sama, bukan menghilang mendadak.
 */
const JEDA_KELUAR = 1000; // sama dengan transisi .gl-intro di glass.css

export default function Intro({ nama, pengguna, onSelesai }) {
    const [fase, setFase] = useState('isi'); // isi -> siap -> buka
    const [tingkat, setTingkat] = useState(0);
    const timer = useRef([]);
    const membuka = useRef(false);
    const selesaiRef = useRef(onSelesai); // onSelesai dari induk berganti tiap render; jadwal tidak boleh ikut mulai ulang
    selesaiRef.current = onSelesai;

    const buka = useCallback(() => {
        if (membuka.current) return;
        membuka.current = true;
        timer.current.forEach(clearTimeout);
        setFase('buka');
        document.documentElement.classList.add('gl-membuka');
        timer.current = [setTimeout(() => selesaiRef.current(), JEDA_KELUAR)];
        // Dilepas terpisah dari jadwal komponen: animasi "memfokus" aplikasi (1,1 dtk) harus selesai walau komponen sudah dicopot.
        setTimeout(() => document.documentElement.classList.remove('gl-membuka'), 1400);
    }, []);

    useEffect(() => {
        timer.current = [setTimeout(() => setTingkat(1), 120), setTimeout(() => setFase('siap'), 1750), setTimeout(buka, 2500)];
        const tombol = (e) => {
            if (['Escape', 'Enter', ' '].includes(e.key)) {
                e.preventDefault();
                buka();
            }
        };
        window.addEventListener('keydown', tombol);
        return () => {
            timer.current.forEach(clearTimeout);
            window.removeEventListener('keydown', tombol);
        };
    }, [buka]);

    const state = fase === 'isi' ? 'typing' : 'ok';
    const teksSub = fase === 'isi' ? 'Menyiapkan ' + nama + '...' : 'Siap digunakan';

    return (
        <div
            className={'gl-intro' + (fase === 'siap' ? ' is-siap' : '') + (fase === 'buka' ? ' is-buka' : '')}
            style={{ '--lg-foto': `url(${latarBuram})` }}
            role="status"
            aria-live="polite"
        >
            <span className="gl-intro-kilat" aria-hidden="true" />
            <div className="gl-intro-isi">
                <TrafoMaskot state={state} tingkat={tingkat} />
                <h2 className="gl-intro-salam">
                    Selamat datang{pengguna ? ', ' : ''}
                    {pengguna ? <b>{pengguna}</b> : null}
                </h2>
                <p className="gl-intro-sub">
                    <span key={fase === 'isi' ? 'isi' : 'siap'}>{teksSub}</span>
                </p>
                <div className="gl-intro-bar">
                    <i />
                </div>
                <button type="button" className="gl-intro-lewat" onClick={buka}>
                    Lewati
                </button>
            </div>
        </div>
    );
}
