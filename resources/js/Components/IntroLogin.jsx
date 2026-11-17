import { useCallback, useEffect, useRef } from 'react';

/*
 * Intro sebelum formulir masuk (PRD 12), mengikuti contoh qc-intro: delapan titik pemandu, cincin tergambar,
 * centang, cakram muncul, lalu "QC-AsataProduction", tagline, dan bar muat.
 * Tanpa foto latar: lapisannya transparan (kaca buram) sehingga formulir masuk terlihat samar di belakang
 * lalu menajam saat intro terbuka. Hanya sekali per sesi peramban; diputuskan di Login.jsx.
 * Lewati: klik di mana saja atau Esc.
 */
const TITIK = [
    [100, 12], [162, 38], [188, 100], [162, 162], [100, 188], [38, 162], [12, 100], [38, 38],
];

export default function IntroLogin({ onSelesai }) {
    const akar = useRef(null);
    const selesaiRef = useRef(onSelesai);
    selesaiRef.current = onSelesai;
    const menutup = useRef(false);
    const timer = useRef([]);

    const tutup = useCallback(() => {
        if (menutup.current) return;
        menutup.current = true;
        timer.current.forEach(clearTimeout);
        akar.current?.classList.add('is-keluar');
        timer.current = [setTimeout(() => selesaiRef.current(), 900)];
    }, []);

    useEffect(() => {
        timer.current = [setTimeout(tutup, 3900)];
        const tombol = (e) => {
            if (e.key === 'Escape') tutup();
        };
        window.addEventListener('keydown', tombol);
        return () => {
            timer.current.forEach(clearTimeout);
            window.removeEventListener('keydown', tombol);
        };
    }, [tutup]);

    return (
        <div className="lgi" ref={akar} onClick={tutup} role="presentation">
            <div className="lgi-panggung">
                <div className="lgi-tanda">
                    <svg viewBox="0 0 200 200" aria-hidden="true">
                        <circle className="lgi-pemandu" cx="100" cy="100" r="88" />
                        <g className="lgi-titik">
                            {TITIK.map(([cx, cy]) => (
                                <circle key={cx + '-' + cy} cx={cx} cy={cy} r="4" />
                            ))}
                        </g>
                        <circle className="lgi-cakram" cx="100" cy="100" r="64" />
                        <circle className="lgi-cincin" cx="100" cy="100" r="70" pathLength="100" />
                        <path className="lgi-centang" d="M64,104 L90,130 L140,72" pathLength="100" />
                    </svg>
                </div>
                <div>
                    <div className="lgi-kata">
                        <span>QC</span>-AsataProduction
                    </div>
                    <div className="lgi-tagline">Quality Checked · Production Ready</div>
                </div>
                <div className="lgi-bar" />
            </div>
        </div>
    );
}
