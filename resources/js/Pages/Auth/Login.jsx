import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import '../../../css/login-robot.css';

const FEATURES = [
    { icon: 'M13 10V3L4 14h7v7l9-11h-7z', text: 'Input Cepat' },
    {
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        text: 'Grafik Real-time',
    },
    {
        icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        text: 'Export Laporan',
    },
    {
        icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        text: 'Aman & Terlindungi',
    },
];

const METER_LABELS = ['TIDAK MELIHAT', 'TERLALU PENDEK', 'LUMAYAN', 'KUAT', 'SANGAT KUAT'];

const SAY = {
    idle: 'Halo. Saya Volt, penjaga form ini.',
    identifierFocus: [
        'Ada tamu. Sebutkan identitas Anda.',
        'Terdeteksi ketikan. Silakan, saya perhatikan.',
    ],
    identifierEmpty: 'Dihapus. Sudah saya lupakan. Sebagian besar.',
    passwordFocus: 'Rahasia? Baik, saya balik badan.',
    passwordPeek: 'Mau ditampilkan? Untung saya menghadap tembok.',
    hypeOn: ['Ooh. Ayo. Tekan.', 'Ini bagian favorit saya.'],
    hypeOff: 'Tombolnya sudah kangen.',
    pressed: ['Ahh. Mantap.', 'Hmm. Memuaskan.', 'Bip. Sekali lagi dong.'],
    submitting: 'Sebentar, saya cek ke pusat data...',
    needIdentifier: 'Saya belum tahu Anda siapa.',
    needPassword: 'Passwordnya belum diisi.',
    strong: 'Password sekelas brankas. Hormat saya.',
};

function LogoMark({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            />
        </svg>
    );
}

function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

/** Skor kekuatan password 0-4, sama dengan sample. */
function scorePassword(value) {
    let score = 0;

    if (value.length >= 8) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^a-zA-Z0-9]/.test(value)) score++;
    if (value.length > 0 && score === 0) score = 1;

    return score;
}

export default function Login({ backgroundUrl, forgotPasswordUrl, loginUrl, year }) {
    const { errors, flash } = usePage().props;

    const { data, setData, post, processing } = useForm({
        identifier: '',
        password: '',
        remember: false,
    });

    // ── state robot ──────────────────────────────────────────────────────
    const [mood, setMood] = useState('idle');
    const [turned, setTurned] = useState(false);
    const [hyped, setHyped] = useState(false);
    const [pressed, setPressed] = useState(false);
    const [spinning, setSpinning] = useState(false);
    const [bubble, setBubble] = useState(SAY.idle);
    const [popping, setPopping] = useState(false);
    const [blinking, setBlinking] = useState(false);
    const [meterLevel, setMeterLevel] = useState(0);
    const [panelLabel, setPanelLabel] = useState(METER_LABELS[0]);
    const [showPassword, setShowPassword] = useState(false);
    const [shaking, setShaking] = useState(false);

    // Saat login gagal, kursor user biasanya MASIH di atas tombol. Tanpa kunci
    // ini, mouseenter/mouseleave langsung menimpa mood 'error' dan mengganti
    // isi bubble, sehingga pesan kesalahan lenyap sebelum sempat dibaca.
    // Kunci dilepas begitu user mulai berinteraksi dengan field lagi.
    const [errorLatched, setErrorLatched] = useState(false);

    const sceneRef = useRef(null);
    const buttonRef = useRef(null);
    const eyesRef = useRef(null);
    const headRef = useRef(null);
    const lastSaidRef = useRef(SAY.idle);
    const celebratedRef = useRef(false);

    const reduceMotion =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ── helper ───────────────────────────────────────────────────────────

    const say = useCallback((text) => {
        if (!text || text === lastSaidRef.current) return;
        lastSaidRef.current = text;
        setBubble(text);
        setPopping(false);
        // frame berikutnya supaya animasi pop benar-benar diulang
        requestAnimationFrame(() => setPopping(true));
    }, []);

    const look = useCallback((x, y) => {
        const el = eyesRef.current;
        if (!el) return;
        el.style.setProperty('--lr-lx', x + 'px');
        el.style.setProperty('--lr-ly', y + 'px');
    }, []);

    const tilt = useCallback((ry, rx) => {
        const el = headRef.current;
        if (!el) return;
        el.style.setProperty('--lr-ry', ry + 'deg');
        el.style.setProperty('--lr-rx', rx + 'deg');
    }, []);

    const followTyping = useCallback(
        (value) => {
            const ratio = Math.min(value.length / 22, 1);
            look(-6 + 12 * ratio, 5);
            tilt(-5 + 10 * ratio, -8);
        },
        [look, tilt],
    );

    const shake = useCallback(() => {
        setShaking(false);
        requestAnimationFrame(() => setShaking(true));
    }, []);

    const confetti = useCallback(() => {
        const host = sceneRef.current;
        const origin = buttonRef.current;
        if (!host || !origin || reduceMotion) return;

        const colors = ['#ff6b4b', '#2ec4b6', '#ffc53d', '#23252d', '#fffdf8'];
        const originRect = origin.getBoundingClientRect();
        const hostRect = host.getBoundingClientRect();
        const ox = originRect.left - hostRect.left + originRect.width / 2;
        const oy = originRect.top - hostRect.top;

        for (let i = 0; i < 70; i++) {
            const bit = document.createElement('span');
            bit.className = 'lr-confetti';
            bit.style.background = pick(colors);
            if (Math.random() > 0.5) bit.style.borderRadius = '50%';
            host.appendChild(bit);

            const angle = -Math.PI / 2 + (Math.random() - 0.5) * 1.6;
            const speed = 240 + Math.random() * 380;
            const tx = Math.cos(angle) * speed;
            const ty = Math.sin(angle) * speed;
            const spin = 540 * (Math.random() > 0.5 ? 1 : -1);

            bit.animate(
                [
                    {
                        transform: 'translate(' + ox + 'px, ' + oy + 'px) rotate(0deg) scale(1)',
                        opacity: 1,
                    },
                    {
                        transform:
                            'translate(' +
                            (ox + tx) +
                            'px, ' +
                            (oy + ty + 320) +
                            'px) rotate(' +
                            spin +
                            'deg) scale(.6)',
                        opacity: 0,
                    },
                ],
                {
                    duration: 1100 + Math.random() * 700,
                    easing: 'cubic-bezier(.15,.6,.35,1)',
                },
            ).onfinish = () => bit.remove();
        }
    }, [reduceMotion]);

    // ── rate limiter: kunci form + hitung mundur ─────────────────────────

    const firstError = errors.identifier || Object.values(errors)[0] || null;
    const [lockedFor, setLockedFor] = useState(0);
    const [errorMessage, setErrorMessage] = useState(firstError);

    useEffect(() => {
        setErrorMessage(firstError);
        const match = firstError ? firstError.match(/Coba lagi dalam (\d+) detik/) : null;
        setLockedFor(match ? parseInt(match[1], 10) : 0);
    }, [firstError]);

    useEffect(() => {
        if (lockedFor <= 0) return undefined;

        const timer = setInterval(() => {
            setLockedFor((secs) => {
                if (secs <= 1) {
                    setErrorMessage(null);
                    return 0;
                }
                return secs - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, [lockedFor > 0]);

    // Error dari server: robot bereaksi, kartu bergetar.
    useEffect(() => {
        if (!firstError) return;
        setTurned(false);
        setMood('error');
        setErrorLatched(true);
        say(firstError);
        shake();
    }, [firstError, say, shake]);

    useEffect(() => {
        const pesan = flash && (flash.warning || flash.success);
        if (pesan) say(pesan);
    }, [flash, say]);

    // ── kedip berkala ────────────────────────────────────────────────────

    useEffect(() => {
        let timeout;

        const loop = () => {
            timeout = setTimeout(
                () => {
                    setBlinking((wasBlinking) => {
                        if (wasBlinking) return wasBlinking;
                        setTimeout(() => setBlinking(false), 150);
                        return true;
                    });
                    loop();
                },
                2600 + Math.random() * 2600,
            );
        };

        loop();

        return () => clearTimeout(timeout);
    }, []);

    // ── mata mengikuti kursor ────────────────────────────────────────────

    useEffect(() => {
        if (reduceMotion) return undefined;

        let pending = false;

        const onMove = (e) => {
            const active = document.activeElement;
            if (active && active.tagName === 'INPUT') return;
            if (pending) return;
            pending = true;

            requestAnimationFrame(() => {
                pending = false;
                const el = headRef.current;
                if (!el) return;

                const rect = el.getBoundingClientRect();
                const cx = rect.left + rect.width / 2;
                const cy = rect.top + rect.height / 2;
                const dx = Math.max(-1, Math.min(1, (e.clientX - cx) / 260));
                const dy = Math.max(-1, Math.min(1, (e.clientY - cy) / 260));

                look(dx * 7, dy * 6);
                if (!turned) tilt(dx * 12, -dy * 9);
            });
        };

        document.addEventListener('mousemove', onMove);

        return () => document.removeEventListener('mousemove', onMove);
    }, [look, tilt, turned, reduceMotion]);

    // ── handler form ─────────────────────────────────────────────────────

    const locked = lockedFor > 0;
    const disabled = locked || processing;

    function onIdentifierFocus() {
        setErrorLatched(false);
        setTurned(false);
        setMood('watching');
        say(pick(SAY.identifierFocus));
        followTyping(data.identifier);
    }

    function onIdentifierChange(e) {
        const value = e.target.value;
        setData('identifier', value);
        followTyping(value);

        const trimmed = value.trim();
        if (trimmed.length >= 2) {
            setMood('happy');
            say('"' + trimmed + '". Dicatat.');
        } else if (trimmed.length === 0) {
            setMood('watching');
            say(SAY.identifierEmpty);
        }
    }

    function onPasswordFocus() {
        setErrorLatched(false);
        setMood('shy');
        setTurned(true);
        look(0, 0);
        tilt(0, 0);
        say(SAY.passwordFocus);
    }

    function onPasswordBlur(e) {
        // Abaikan kalau fokus pindah ke tombol intip — kepala tetap membelakangi.
        if (e.relatedTarget && e.relatedTarget.dataset.lrPeek === 'true') return;
        setTurned(false);
    }

    function onPasswordChange(e) {
        const value = e.target.value;
        setData('password', value);

        const score = scorePassword(value);
        setMeterLevel(score);
        setPanelLabel(value.length === 0 ? METER_LABELS[0] : METER_LABELS[score]);

        // Perayaan kecil saat password pertama kali mencapai level tertinggi.
        if (score === 4 && !celebratedRef.current) {
            celebratedRef.current = true;
            say(SAY.strong);
            confetti();
        }
    }

    function togglePeek() {
        setShowPassword((visible) => {
            if (!visible) say(SAY.passwordPeek);
            return !visible;
        });
    }

    function hype(on) {
        if (pressed) return;

        // mouseleave/blur bisa terpicu tanpa pernah ada mouseenter (mis. kursor
        // kebetulan berada di atas tombol saat halaman dimuat). Tanpa penjaga
        // ini, sapaan pembuka langsung tergantikan "Tombolnya sudah kangen."
        if (!on && !hyped) return;

        setHyped(on);

        // Selagi pesan error masih ditampilkan, jangan ganti mood/bubble.
        if (errorLatched) return;

        if (on) {
            setTurned(false);
            setMood('excited');
            say(pick(SAY.hypeOn));
        } else {
            setMood('idle');
            say(SAY.hypeOff);
        }
    }

    function onPressDown() {
        setPressed(true);
        setMood('pressed');
        say(pick(SAY.pressed));
    }

    function releasePress() {
        setPressed(false);
        if (errorLatched) return;
        setMood((current) => (current === 'pressed' ? 'excited' : current));
    }

    function submit(e) {
        e.preventDefault();
        if (disabled) return;

        // Validasi sisi klien dulu supaya robot bisa protes tanpa ke server.
        //
        // Urutan penting: focus() dipanggil LEBIH DULU, baru say(). Handler
        // onFocus milik field ikut memanggil say() secara sinkron, jadi kalau
        // protesnya diucapkan duluan ia akan langsung tertimpa sapaan biasa dan
        // user tak pernah tahu kenapa form tidak terkirim.
        if (!data.identifier.trim()) {
            setTurned(false);
            document.getElementById('identifier')?.focus();
            setMood('watching');
            say(SAY.needIdentifier);
            shake();
            return;
        }

        if (!data.password) {
            document.getElementById('password')?.focus();
            setMood('watching');
            say(SAY.needPassword);
            shake();
            return;
        }

        setErrorLatched(false);
        setTurned(false);
        setHyped(false);
        setMood('excited');
        say(SAY.submitting);

        if (!reduceMotion) {
            setSpinning(true);
            setTimeout(() => setSpinning(false), 950);
        }

        post(loginUrl, { onFinish: () => setData('password', '') });
    }

    const robotClass = [
        'lr-robot',
        turned ? 'is-turned' : '',
        hyped ? 'is-hyped' : '',
        pressed ? 'is-pressed' : '',
        spinning ? 'is-spinning' : '',
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <>
            <Head title="Login" />

            <div className="lr-scene">
                {/* Panel kiri: foto + branding, seperti layout login sebelumnya.
                    Disembunyikan di bawah 1024px sehingga di ponsel hanya form. */}
                <aside
                    className="lr-brand-panel"
                    style={{ backgroundImage: 'url(' + backgroundUrl + ')' }}
                >
                    <div className="lr-brand-head">
                        <span className="lr-brand-mark">
                            <LogoMark />
                        </span>
                        <span className="lr-brand-name">Asata Production System</span>
                    </div>

                    <div className="lr-brand-body">
                        <h2 className="lr-brand-title">
                            Sistem Pencatatan
                            <br />
                            Produksi Digital
                        </h2>
                        <p className="lr-brand-text">
                            Gantikan pencatatan manual dengan sistem digital yang cepat, akurat,
                            dan mudah digunakan.
                        </p>

                        <div className="lr-features">
                            {FEATURES.map((feature) => (
                                <div className="lr-feature" key={feature.text}>
                                    <span className="lr-feature-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth="2"
                                                d={feature.icon}
                                            />
                                        </svg>
                                    </span>
                                    <span>{feature.text}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <p className="lr-brand-foot">&copy; {year} Asata Production System</p>
                </aside>

                {/* Panel kanan: robot + form. sceneRef dipasang di sini karena
                    confetti diposisikan relatif terhadap panel ini. */}
                <div className="lr-form-panel" ref={sceneRef}>
                <main className="lr-stage">
                    {/* ── robot ── */}
                    <div className={robotClass} data-mood={mood}>
                        <div
                            className={'lr-bubble' + (popping ? ' is-popping' : '')}
                            role="status"
                            aria-live="polite"
                        >
                            <span>{bubble}</span>
                        </div>

                        <div className="lr-antenna" aria-hidden="true">
                            <span className="lr-antenna-rod" />
                            <span className="lr-antenna-tip" />
                        </div>

                        <div className="lr-head3d" ref={headRef} aria-hidden="true">
                            <div className="lr-head">
                                <span className="lr-ear lr-ear--l" />
                                <span className="lr-ear lr-ear--r" />

                                <div className="lr-face lr-face--front">
                                    <div className="lr-visor">
                                        <div
                                            className={'lr-eyes' + (blinking ? ' is-blinking' : '')}
                                            ref={eyesRef}
                                        >
                                            <span className="lr-eye lr-eye--l" />
                                            <span className="lr-eye lr-eye--r" />
                                        </div>
                                        <span className="lr-cheek lr-cheek--l" />
                                        <span className="lr-cheek lr-cheek--r" />
                                        <span className="lr-mouth" />
                                    </div>
                                </div>

                                <div className="lr-face lr-face--back">
                                    <div className="lr-panel">
                                        <span className="lr-panel-lights">
                                            <i />
                                            <i />
                                            <i />
                                        </span>
                                        <div className="lr-meter" data-lvl={meterLevel}>
                                            {[0, 1, 2, 3].map((i) => (
                                                <i key={i} className={i < meterLevel ? 'on' : ''} />
                                            ))}
                                        </div>
                                        <p className="lr-panel-label">{panelLabel}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ── form ── */}
                    <form
                        className={'lr-card' + (shaking ? ' is-shaking' : '')}
                        onSubmit={submit}
                        onAnimationEnd={() => setShaking(false)}
                        noValidate
                    >
                        <span className="lr-hand lr-hand--l" aria-hidden="true" />
                        <span className="lr-hand lr-hand--r" aria-hidden="true" />

                        <h1 className="lr-title">Bip bup. Siapa di sana?</h1>
                        <p className="lr-subtitle">Asata Production System</p>

                        {errorMessage ? (
                            <div className="lr-alert" role="alert">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 2 1 21h22L12 2Zm1 14h-2v2h2v-2Zm0-6h-2v4h2v-4Z" />
                                </svg>
                                <span>{errorMessage}</span>
                            </div>
                        ) : null}

                        {flash && flash.success && !errorMessage ? (
                            <div className="lr-alert lr-alert--ok">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z" />
                                </svg>
                                <span>{flash.success}</span>
                            </div>
                        ) : null}

                        {/* "Sesi berakhir" setelah token CSRF kedaluwarsa. */}
                        {flash && flash.warning && !errorMessage ? (
                            <div className="lr-alert lr-alert--ok">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                                </svg>
                                <span>{flash.warning}</span>
                            </div>
                        ) : null}

                        <label className={'lr-field' + (errors.identifier ? ' has-error' : '')}>
                            <svg className="lr-field-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.5 4.5 0 0 0 12 12Zm0 2c-3.9 0-8 2-8 5v1.5h16V19c0-3-4.1-5-8-5Z" />
                            </svg>
                            <input
                                id="identifier"
                                name="identifier"
                                type="text"
                                value={data.identifier}
                                onFocus={onIdentifierFocus}
                                onChange={onIdentifierChange}
                                disabled={disabled}
                                autoComplete="username"
                                autoFocus
                                placeholder="Username atau email"
                                aria-label="Username atau email"
                            />
                        </label>

                        <label className="lr-field">
                            <svg className="lr-field-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 0 1 6 0v3H9Zm3 4a2 2 0 0 1 1 3.7V19h-2v-1.3a2 2 0 0 1 1-3.7Z" />
                            </svg>
                            <input
                                id="password"
                                name="password"
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                onFocus={onPasswordFocus}
                                onBlur={onPasswordBlur}
                                onChange={onPasswordChange}
                                disabled={disabled}
                                autoComplete="current-password"
                                placeholder="Password"
                                aria-label="Password"
                            />
                            <button
                                type="button"
                                className="lr-peek"
                                data-lr-peek="true"
                                onClick={togglePeek}
                                aria-pressed={showPassword}
                                aria-label={
                                    showPassword ? 'Sembunyikan password' : 'Tampilkan password'
                                }
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5c-5 0-9.3 3.1-11 7.5C2.7 16.9 7 20 12 20s9.3-3.1 11-7.5C21.3 8.1 17 5 12 5Zm0 12.5a5 5 0 1 1 5-5 5 5 0 0 1-5 5Zm0-8a3 3 0 1 0 3 3 3 3 0 0 0-3-3Z" />
                                </svg>
                            </button>
                        </label>

                        <div className="lr-row">
                            <label className="lr-remember">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    disabled={disabled}
                                />
                                <span>Ingat saya</span>
                            </label>
                            <a className="lr-link" href={forgotPasswordUrl}>
                                Lupa password?
                            </a>
                        </div>

                        <button
                            ref={buttonRef}
                            className="lr-btn"
                            type="submit"
                            disabled={disabled}
                            onMouseEnter={() => hype(true)}
                            onMouseLeave={() => hype(false)}
                            onFocus={() => hype(true)}
                            onBlur={() => hype(false)}
                            onPointerDown={onPressDown}
                            onPointerUp={releasePress}
                            onPointerCancel={releasePress}
                        >
                            <span className="lr-btn-bolt" aria-hidden="true">
                                ⚡
                            </span>
                            <span>
                                {locked
                                    ? 'TUNGGU ' + lockedFor + ' DETIK'
                                    : processing
                                      ? 'MEMPROSES...'
                                      : 'MASUK KE SISTEM'}
                            </span>
                        </button>

                        <p className="lr-footnote">
                            Hubungi administrator jika mengalami masalah login
                            {/* Copyright hanya untuk layar kecil: di desktop sudah
                                tercantum di panel kiri, jangan tampil dua kali. */}
                            <span className="lr-footnote-copy">
                                <br />
                                &copy; {year} Asata Production System
                            </span>
                        </p>

                        <span className="lr-foot lr-foot--l" aria-hidden="true" />
                        <span className="lr-foot lr-foot--r" aria-hidden="true" />
                    </form>
                </main>
                </div>
            </div>
        </>
    );
}
