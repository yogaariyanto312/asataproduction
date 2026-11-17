import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import '../../../css/glass-token.css';
import '../../../css/login-baru.css';
import { TAMPILAN, tampilanSekarang, terapkanTampilan } from '../../Components/Glass';
import { useBlokirDevtools } from '../../blokirDevtools';
import latarBuram from '../../../images/login-latar-blur.webp';

const MEREK =
    'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2';

/** Titik warna kecil untuk mengganti tema langsung dari halaman masuk. */
export function TemaTitik() {
    const [pilih, setPilih] = useState('coral');

    useEffect(() => {
        setPilih(tampilanSekarang());
        const sinkron = () => setPilih(tampilanSekarang());
        window.addEventListener('au:tema', sinkron);
        return () => window.removeEventListener('au:tema', sinkron);
    }, []);

    return (
        <div className="lg-tema" role="group" aria-label="Pilih tema warna">
            {TAMPILAN.map((t) => (
                <button
                    type="button"
                    key={t.k}
                    className="lg-tema-titik"
                    aria-pressed={pilih === t.k}
                    aria-label={'Tema ' + t.nama}
                    title={t.nama}
                    style={{ '--a': t.a, '--b': t.b }}
                    onClick={() => {
                        terapkanTampilan(t.k);
                        setPilih(t.k);
                    }}
                />
            ))}
        </div>
    );
}

/** Kerangka bersama: foto pabrik lama yang diburamkan sebagai latar, bilah merek, pemilih tema, dan kaki halaman. */
export function Bingkai({ title, children }) {
    useBlokirDevtools();
    return (
        <>
            <Head title={title} />
            <div className="lg-scene" style={{ '--lg-foto': `url(${latarBuram})` }}>
                <header className="lg-atas">
                    <div className="lg-merek">
                        <span className="lg-merek-ikon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={MEREK} />
                            </svg>
                        </span>
                        <span>Asata Production System</span>
                    </div>
                    <TemaTitik />
                </header>

                <main className="lg-tengah">{children}</main>

                <footer className="lg-kaki">&copy; {new Date().getFullYear()} Asata Production System</footer>
            </div>
        </>
    );
}

/** Halaman auth selain masuk (lupa & reset password): judul + kartu kaca. */
export default function AuthShell({ title, subtitle, children }) {
    return (
        <Bingkai title={title}>
            <div className="lg-inti">
                <h1 className="lg-judul lg-judul--kecil">{title}</h1>
                <p className="lg-sub">{subtitle}</p>
                <div className="lg-kartu">{children}</div>
            </div>
        </Bingkai>
    );
}
