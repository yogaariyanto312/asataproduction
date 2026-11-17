import { useEffect, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import TrafoMaskot from '../../Components/TrafoMaskot';
import IntroLogin from '../../Components/IntroLogin';
import { introAktif, ringanAktif } from '../../Components/Glass';
import { Bingkai } from './Shell';

const FITUR = [
    { ikon: 'M13 10V3L4 14h7v7l9-11h-7z', teks: 'Input Cepat' },
    {
        ikon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        teks: 'Grafik Real-time',
    },
    {
        ikon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        teks: 'Export Laporan',
    },
    {
        ikon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        teks: 'Aman & Terlindungi',
    },
];

const Fitur = ({ f }) => (
    <div className="lg-fitur">
        <span className="lg-fitur-ikon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={f.ikon} />
            </svg>
        </span>
        <span>{f.teks}</span>
    </div>
);

export default function Login({ forgotPasswordUrl, loginUrl }) {
    const { errors, flash } = usePage().props;

    const { data, setData, post, processing } = useForm({
        identifier: '',
        password: '',
        remember: false,
    });

    // Intro sebelum formulir: sekali per sesi peramban; tidak tampil bila dimatikan di Tampilan, mode ringan, atau kurangi gerak.
    const [intro, setIntro] = useState(() => {
        try {
            const boleh = !sessionStorage.getItem('au:intro-login') && introAktif() && !ringanAktif() && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (boleh) sessionStorage.setItem('au:intro-login', '1');
            return boleh;
        } catch (_) {
            return false;
        }
    });
    const [fokus, setFokus] = useState(null); // 'identifier' | 'password' | null
    const [lihat, setLihat] = useState(false);
    const [bergetar, setBergetar] = useState(false);
    const [berhasil, setBerhasil] = useState(false);
    const [galatLokal, setGalatLokal] = useState(null);
    const [salah, setSalah] = useState(false); // galat server belum "dibaca" (sampai pengguna mengetik lagi)

    const galatServer = errors.identifier || Object.values(errors)[0] || null;
    const pesanGalat = galatLokal || galatServer;

    // Pembatasan laju: server mengirim "Coba lagi dalam N detik"; form dikunci dan dihitung mundur.
    const [kunciDetik, setKunciDetik] = useState(0);
    const terkunci = kunciDetik > 0;
    const nonaktif = terkunci || processing;

    useEffect(() => {
        const cocok = galatServer ? galatServer.match(/Coba lagi dalam (\d+) detik/) : null;
        setKunciDetik(cocok ? parseInt(cocok[1], 10) : 0);
        if (galatServer) {
            setSalah(true);
            setBergetar(true);
        }
    }, [galatServer]);

    useEffect(() => {
        if (kunciDetik <= 0) return undefined;
        const t = setInterval(() => setKunciDetik((d) => Math.max(0, d - 1)), 1000);
        return () => clearInterval(t);
    }, [kunciDetik > 0]);

    let state = 'idle';
    if (terkunci) state = 'kunci';
    else if (berhasil) state = 'ok';
    else if (processing) state = 'proses';
    else if (salah || galatLokal) state = 'error';
    else if (fokus === 'password') state = 'secret';
    else if (fokus === 'identifier' && data.identifier) state = 'typing';

    const tingkat = Math.min(data.identifier.length / 18, 1);

    function ubah(kolom, nilai) {
        setData(kolom, nilai);
        setSalah(false);
        setGalatLokal(null);
    }

    function kirim(e) {
        e.preventDefault();
        if (nonaktif) return;

        if (!data.identifier.trim()) {
            setGalatLokal('Username atau email belum diisi.');
            setBergetar(true);
            document.getElementById('identifier')?.focus();
            return;
        }
        if (!data.password) {
            setGalatLokal('Password belum diisi.');
            setBergetar(true);
            document.getElementById('password')?.focus();
            return;
        }

        setGalatLokal(null);
        setSalah(false);
        // Penanda untuk layar pembuka di AppLayout; harus ada SEBELUM halaman berikutnya dimuat.
        try {
            sessionStorage.setItem('au:intro-baru', '1');
        } catch (_) {
            /* abaikan */
        }
        post(loginUrl, {
            onSuccess: () => setBerhasil(true),
            onError: () => {
                try {
                    sessionStorage.removeItem('au:intro-baru');
                } catch (_) {
                    /* abaikan */
                }
            },
            onFinish: () => setData('password', ''),
        });
    }

    return (
        <Bingkai title="Masuk">
            {intro ? <IntroLogin onSelesai={() => setIntro(false)} /> : null}
            <div className="lg-kolom">
                <div className="lg-samping lg-samping--kiri">
                    <Fitur f={FITUR[0]} />
                    <Fitur f={FITUR[1]} />
                </div>

                <div className="lg-inti">
                    <TrafoMaskot state={state} tingkat={tingkat} />
                    <h1 className="lg-judul">Selamat datang kembali</h1>
                    <p className="lg-sub">Catat produksi harian, pantau target, dan lihat laporan dalam satu tempat.</p>

                    <form
                        className={'lg-kartu' + (bergetar ? ' is-bergetar' : '')}
                        onSubmit={kirim}
                        onAnimationEnd={() => setBergetar(false)}
                        noValidate
                    >
                        {pesanGalat ? (
                            <div className="lg-alert" role="alert">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 2 1 21h22L12 2Zm1 14h-2v2h2v-2Zm0-6h-2v4h2v-4Z" />
                                </svg>
                                <span>{pesanGalat}</span>
                            </div>
                        ) : null}

                        {flash && flash.success && !pesanGalat ? (
                            <div className="lg-alert lg-alert--ok">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z" />
                                </svg>
                                <span>{flash.success}</span>
                            </div>
                        ) : null}

                        {/* "Sesi berakhir" setelah token CSRF kedaluwarsa. */}
                        {flash && flash.warning && !pesanGalat ? (
                            <div className="lg-alert lg-alert--ok">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                                </svg>
                                <span>{flash.warning}</span>
                            </div>
                        ) : null}

                        <label className={'lg-field' + (errors.identifier || galatLokal === 'Username atau email belum diisi.' ? ' has-error' : '')}>
                            <svg className="lg-field-ikon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.5 4.5 0 0 0 12 12Zm0 2c-3.9 0-8 2-8 5v1.5h16V19c0-3-4.1-5-8-5Z" />
                            </svg>
                            <input
                                id="identifier"
                                name="identifier"
                                type="text"
                                value={data.identifier}
                                onFocus={() => setFokus('identifier')}
                                onBlur={() => setFokus(null)}
                                onChange={(e) => ubah('identifier', e.target.value)}
                                disabled={nonaktif}
                                autoComplete="username"
                                autoFocus
                                placeholder="Username atau email"
                                aria-label="Username atau email"
                            />
                        </label>

                        <label className={'lg-field' + (galatLokal === 'Password belum diisi.' ? ' has-error' : '')}>
                            <svg className="lg-field-ikon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 0 1 6 0v3H9Zm3 4a2 2 0 0 1 1 3.7V19h-2v-1.3a2 2 0 0 1 1-3.7Z" />
                            </svg>
                            <input
                                id="password"
                                name="password"
                                type={lihat ? 'text' : 'password'}
                                value={data.password}
                                onFocus={() => setFokus('password')}
                                onBlur={() => setFokus(null)}
                                onChange={(e) => ubah('password', e.target.value)}
                                disabled={nonaktif}
                                autoComplete="current-password"
                                placeholder="Password"
                                aria-label="Password"
                            />
                            <button
                                type="button"
                                className="lg-mata"
                                onClick={() => setLihat((v) => !v)}
                                aria-pressed={lihat}
                                aria-label={lihat ? 'Sembunyikan password' : 'Tampilkan password'}
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    {lihat ? (
                                        <path d="M2 4.3 3.3 3l17.7 17.7-1.3 1.3-3.2-3.2A11.6 11.6 0 0 1 12 20C7 20 2.7 16.9 1 12.5A12 12 0 0 1 5.4 7.1L2 4.3Zm10 3.2a5 5 0 0 1 5 5c0 .6-.1 1.1-.3 1.6l-6.3-6.3c.5-.2 1-.3 1.6-.3ZM12 5c5 0 9.3 3.1 11 7.5a12.3 12.3 0 0 1-3 4.3l-3-3a5 5 0 0 0-6.8-6.8L8 5.7C9.2 5.2 10.6 5 12 5Z" />
                                    ) : (
                                        <path d="M12 5c-5 0-9.3 3.1-11 7.5C2.7 16.9 7 20 12 20s9.3-3.1 11-7.5C21.3 8.1 17 5 12 5Zm0 12.5a5 5 0 1 1 5-5 5 5 0 0 1-5 5Zm0-8a3 3 0 1 0 3 3 3 3 0 0 0-3-3Z" />
                                    )}
                                </svg>
                            </button>
                        </label>

                        <div className="lg-baris">
                            <label className="lg-ingat">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    disabled={nonaktif}
                                />
                                <span>Ingat saya</span>
                            </label>
                            <a className="lg-link" href={forgotPasswordUrl}>
                                Lupa password?
                            </a>
                        </div>

                        <button className="lg-tombol" type="submit" disabled={nonaktif}>
                            <span>{terkunci ? 'Tunggu ' + kunciDetik + ' detik' : processing ? 'Memproses...' : 'Masuk ke sistem'}</span>
                        </button>

                        <p className="lg-catatan">Hubungi administrator jika mengalami masalah login</p>
                    </form>
                </div>

                <div className="lg-samping lg-samping--kanan">
                    <Fitur f={FITUR[2]} />
                    <Fitur f={FITUR[3]} />
                </div>
            </div>
        </Bingkai>
    );
}
