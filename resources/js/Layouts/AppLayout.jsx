import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import '../../css/asata-ui.css';

/**
 * Kerangka aplikasi — mengikuti layouts/app.blade.php Production-QC-Logging-System
 * (tema warna asata):
 *  - sidebar dengan logo = tombol kecilkan (PC), tooltip nama menu saat kecil,
 *    grup QC-Welding (developer), badge pesan belum dibaca di Chatting,
 *    kartu pengguna di bawah (Profil Saya / Logout);
 *  - header: judul halaman + tanggal & jam berjalan;
 *  - notifikasi berkala (pesan chat baru, catatan jatuh tempo), tombol kembali
 *    ke atas, footer, dan blokir DevTools bila diaktifkan di Settings.
 */

/** Pencocokan pola route ala Str::is ("production.*"). */
function routeMatches(patterns, routeName) {
    if (!routeName || !patterns) return false;

    return patterns.some((pattern) => {
        if (pattern === routeName) return true;
        if (!pattern.includes('*')) return false;
        const escaped = pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
        return new RegExp('^' + escaped + '$').test(routeName);
    });
}

function Icon({ path, paths, className }) {
    const list = paths && paths.length ? paths : path ? [path] : [];
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"
            strokeLinejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            {list.map((d, i) => (
                <path key={i} d={d} />
            ))}
        </svg>
    );
}

const ICON = {
    burger: 'M4 6h16M4 12h16M4 18h16',
    chevron: 'M19 9l-7 7-7-7',
    chevronUp: 'M5 15l7-7 7 7',
    logout: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
    user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    folder: 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
    clipboard: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    check: 'M5 13l4 4L19 7',
    alert: 'M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0l-7.1 12.25A2 2 0 004.98 19z',
};

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

/** Tanggal + jam berjalan di header ("Sabtu, 03 Oktober 2026 | 1:10:38 AM"). */
function Jam() {
    const [now, setNow] = useState(() => new Date());
    useEffect(() => {
        const t = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(t);
    }, []);
    let h = now.getHours();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    const pad = (n) => String(n).padStart(2, '0');
    return (
        <span className="au-jam">
            <span className="au-jam-tgl">
                {HARI[now.getDay()]}, {pad(now.getDate())} {BULAN[now.getMonth()]} {now.getFullYear()}
            </span>
            <span className="au-jam-sep" />
            <span className="au-jam-waktu">
                {h}:{pad(now.getMinutes())}:{pad(now.getSeconds())} {ampm}
            </span>
        </span>
    );
}

function NavLink({ item, active, badge, small }) {
    const className = 'au-nav-item sb-link' + (small ? ' is-sub' : '') + (active ? ' is-active' : '');
    const inner = (
        <>
            {item.icon || (item.paths && item.paths.length) ? <Icon path={item.icon} paths={item.paths} /> : null}
            <span className="sb-label">{item.label}</span>
            {badge ? <span className="au-nav-badge sb-badge">{badge > 99 ? '99+' : badge}</span> : null}
        </>
    );

    if (!item.url) {
        return (
            <span className={className} data-sb-tip={item.label}>
                {inner}
            </span>
        );
    }

    // Halaman yang belum dimigrasi masih Blade: <a> biasa (full reload).
    return item.spa ? (
        <Link href={item.url} className={className} data-sb-tip={item.label}>
            {inner}
        </Link>
    ) : (
        <a href={item.url} className={className} data-sb-tip={item.label}>
            {inner}
        </a>
    );
}

/** Notifikasi berkala: pesan chat baru, catatan jatuh tempo, badge belum dibaca. */
function useNotifikasi({ notifUrl, chatUrl, notesUrl, routeName, tambahToast }) {
    const [unread, setUnread] = useState(0);
    const routeRef = useRef(routeName);
    routeRef.current = routeName;

    useEffect(() => {
        if (!notifUrl) return undefined;
        const MSG_KEY = 'asata:last_msg_id';
        const NOTE_KEY = 'asata:shown_notes';
        let lastId = 0;
        let sinkron = false;
        let timer = null;
        try {
            lastId = parseInt(localStorage.getItem(MSG_KEY) || '0', 10) || 0;
        } catch (_) {
            /* abaikan */
        }

        async function poll() {
            try {
                const res = await fetch(notifUrl + '?last_msg_id=' + lastId, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                // 503 = maintenance baru dinyalakan: muat ulang supaya halaman
                // yang masih terbuka berganti ke layar maintenance.
                if (res.status === 503) {
                    window.location.reload();
                    return;
                }
                if (!res.ok) return;
                const d = await res.json();
                const serverLast = parseInt(d.last_msg_id || lastId, 10);
                const pesan = Array.isArray(d.messages) ? d.messages : [];
                const diChat = routeRef.current === 'chatting';

                // Poll pertama hanya menetapkan patokan, tidak memunculkan toast lama.
                if (sinkron && !diChat && pesan.length) {
                    pesan.slice(0, 3).forEach((m) =>
                        tambahToast({ type: 'chat', title: m.sender_name, text: m.preview, href: chatUrl }),
                    );
                    if (pesan.length > 3) {
                        tambahToast({ type: 'chat', title: '+' + (pesan.length - 3) + ' pesan lainnya', text: 'Buka chatting untuk melihat semua', href: chatUrl });
                    }
                }
                sinkron = true;
                if (serverLast > lastId) {
                    lastId = serverLast;
                    try {
                        localStorage.setItem(MSG_KEY, String(lastId));
                    } catch (_) {
                        /* abaikan */
                    }
                }

                setUnread(parseInt(d.unread_count || 0, 10));

                // Catatan jatuh tempo: tampil sekali per catatan per hari.
                const catatan = Array.isArray(d.note_reminders) ? d.note_reminders : [];
                if (catatan.length) {
                    const hariIni = new Date().toDateString();
                    let tampil = [];
                    try {
                        tampil = JSON.parse(localStorage.getItem(NOTE_KEY) || '[]').filter((n) => n.date === hariIni);
                    } catch (_) {
                        tampil = [];
                    }
                    const sudah = new Set(tampil.map((n) => n.id));
                    catatan.forEach((n) => {
                        if (sudah.has(n.id)) return;
                        tambahToast({ type: 'note', title: 'Catatan jatuh tempo hari ini', text: n.title, href: notesUrl });
                        tampil.push({ id: n.id, date: hariIni });
                    });
                    try {
                        localStorage.setItem(NOTE_KEY, JSON.stringify(tampil));
                    } catch (_) {
                        /* abaikan */
                    }
                }
            } catch (_) {
                /* gagal — coba lagi di putaran berikutnya */
            }
        }

        // Berhenti saat tab tidak aktif untuk hemat koneksi.
        const mulai = () => {
            if (timer) return;
            poll();
            timer = setInterval(poll, 30000);
        };
        const henti = () => {
            clearInterval(timer);
            timer = null;
        };
        const onVis = () => (document.hidden ? henti() : mulai());
        document.addEventListener('visibilitychange', onVis);
        mulai();

        return () => {
            henti();
            document.removeEventListener('visibilitychange', onVis);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [notifUrl]);

    return unread;
}

/** Sidebar kecil (PC): logo = tombol, tooltip nama menu saat hanya ikon. */
function useSidebarKecil() {
    const [mini, setMini] = useState(() => document.documentElement.classList.contains('sb-mini'));
    const [tip, setTip] = useState(null);

    useEffect(() => {
        // Transisi dinyalakan setelah tampilan awal tergambar — pilihan tersimpan
        // langsung berlaku tanpa animasi menyusut tiap buka halaman.
        requestAnimationFrame(() => requestAnimationFrame(() => document.documentElement.classList.add('sb-siap')));
    }, []);

    function toggle() {
        if (!window.matchMedia('(min-width: 1024px)').matches) return; // di HP logo hanya logo
        const jadi = document.documentElement.classList.toggle('sb-mini');
        try {
            localStorage.setItem('qc:sidebar-mini', jadi ? '1' : '0');
        } catch (_) {
            /* abaikan */
        }
        setMini(jadi);
        setTip(null);
        // Grafik & tabel lebar ikut menyesuaikan setelah animasi selesai.
        setTimeout(() => window.dispatchEvent(new Event('resize')), 320);
    }

    function onOver(e) {
        const el = e.target.closest ? e.target.closest('[data-sb-tip]') : null;
        if (!el || !document.documentElement.classList.contains('sb-mini') || !window.matchMedia('(min-width: 1024px)').matches) {
            if (!el) setTip(null);
            return;
        }
        const r = el.getBoundingClientRect();
        setTip({ text: el.getAttribute('data-sb-tip'), left: r.right + 10, top: r.top + r.height / 2 });
    }

    return { mini, toggle, tip, onOver, hideTip: () => setTip(null) };
}

/*
 * Transisi pindah halaman.
 * - Masuk: isi halaman memudar + naik sedikit — hanya bila BENAR-BENAR pindah
 *   halaman (path berubah), bukan saat filter/cari di halaman yang sama.
 * - Keluar: selama halaman berikut dimuat, isi lama meredup (dengan jeda kecil
 *   agar muatan cepat tidak berkedip). Reload parsial (polling, cari langsung)
 *   tidak ikut.
 */
let jalurTerakhir = null;
let pantauPindahTerpasang = false;
function pasangPantauPindah() {
    if (pantauPindahTerpasang || typeof document === 'undefined') return;
    pantauPindahTerpasang = true;
    const akar = document.documentElement;
    router.on('start', (e) => {
        const v = e.detail.visit;
        const pindah = v.method === 'get' && !(v.only && v.only.length) && v.url && v.url.pathname !== window.location.pathname;
        if (pindah) akar.classList.add('au-pindah');
    });
    router.on('finish', () => akar.classList.remove('au-pindah'));
}

function useAnimasiMasuk() {
    const [animasi] = useState(() => {
        const jalur = window.location.pathname;
        const beda = jalurTerakhir !== null && jalurTerakhir !== jalur;
        jalurTerakhir = jalur;
        return beda;
    });
    return animasi;
}

export default function AppLayout({ title, subtitle, children }) {
    pasangPantauPindah();
    const animasiMasuk = useAnimasiMasuk();
    const { auth, menu, flash, routeName, logoutUrl, profileUrl, notifUrl, chatUrl, notesUrl, appName, disableDevtools, maintenanceAktif } =
        usePage().props;
    const user = auth.user;

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [userMenu, setUserMenu] = useState(false);
    const [toasts, setToasts] = useState([]);
    const [atas, setAtas] = useState(false);
    const userRef = useRef(null);
    const sb = useSidebarKecil();

    function tambahToast(t) {
        const id = Date.now() + Math.random();
        setToasts((list) => [...list, { id, ...t }]);
        setTimeout(() => setToasts((list) => list.filter((x) => x.id !== id)), t.type === 'chat' || t.type === 'note' ? 6000 : 4500);
    }

    const unread = useNotifikasi({ notifUrl, chatUrl, notesUrl, routeName, tambahToast });

    // Grup "produksi" sebagai dropdown QC-Welding — khusus developer.
    const isDeveloper = user && user.role === 'developer';
    const groupItems = isDeveloper ? menu.filter((m) => m.group === 'produksi') : [];
    const groupActive = groupItems.some((m) => routeMatches(m.match, routeName));
    const [groupOpen, setGroupOpen] = useState(groupActive);

    useEffect(() => {
        if (groupActive) setGroupOpen(true);
    }, [groupActive]);

    useEffect(() => {
        setSidebarOpen(false);
        setUserMenu(false);
    }, [routeName]);

    // Flash dari server → toast.
    useEffect(() => {
        if (flash?.success) tambahToast({ type: 'ok', text: flash.success });
        if (flash?.warning) tambahToast({ type: 'warn', text: flash.warning });
        if (flash?.error) tambahToast({ type: 'error', text: flash.error });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash]);

    // Tombol kembali ke atas.
    useEffect(() => {
        const f = () => setAtas(window.scrollY > 300);
        window.addEventListener('scroll', f, { passive: true });
        return () => window.removeEventListener('scroll', f);
    }, []);

    // Tutup menu pengguna saat klik di luar.
    useEffect(() => {
        if (!userMenu) return undefined;
        const f = (e) => {
            if (userRef.current && !userRef.current.contains(e.target)) setUserMenu(false);
        };
        document.addEventListener('mousedown', f);
        return () => document.removeEventListener('mousedown', f);
    }, [userMenu]);

    // Blokir klik kanan & pintasan DevTools (Settings → non-developer).
    useEffect(() => {
        if (!disableDevtools) return undefined;
        const ctx = (e) => {
            e.preventDefault();
            tambahToast({ type: 'error', text: 'Klik kanan dinonaktifkan oleh Developer.' });
        };
        const key = (e) => {
            const k = (e.key || '').toUpperCase();
            const diblok =
                e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(k)) ||
                (e.ctrlKey && k === 'U');
            if (!diblok) return;
            e.preventDefault();
            e.stopPropagation();
            tambahToast({ type: 'error', text: 'Pintasan ini diblokir oleh Developer.' });
        };
        document.addEventListener('contextmenu', ctx);
        document.addEventListener('keydown', key, true);
        return () => {
            document.removeEventListener('contextmenu', ctx);
            document.removeEventListener('keydown', key, true);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [disableDevtools]);

    const initial = user ? user.name.trim().charAt(0).toUpperCase() : '?';
    const renderedGroupKeys = new Set(groupItems.map((m) => m.key));

    return (
        <div className="au-app">
            <Head title={title} />

            <div className="au-shell">
                <aside
                    id="sidebar"
                    className={'au-sidebar' + (sidebarOpen ? ' is-open' : '')}
                    onMouseOver={sb.onOver}
                    onMouseLeave={sb.hideTip}
                >
                    <div className="au-brand sb-logo">
                        <button
                            type="button"
                            id="sb-mini-toggle"
                            className="au-brand-mark"
                            onClick={sb.toggle}
                            title={sb.mini ? 'Besarkan sidebar' : 'Kecilkan sidebar'}
                            aria-label={sb.mini ? 'Besarkan sidebar' : 'Kecilkan sidebar'}
                            aria-controls="sidebar"
                            aria-expanded={!sb.mini}
                        >
                            <Icon path={ICON.clipboard} />
                        </button>
                        <span className="sb-label">
                            <span className="au-brand-name">{appName || 'Asata Production'}</span>
                            <br />
                            <span className="au-brand-sub">Sistem Pencatatan</span>
                        </span>
                    </div>

                    <nav className="au-nav" onScroll={sb.hideTip}>
                        {menu.map((item) => {
                            const inGroup = renderedGroupKeys.has(item.key);
                            if (inGroup && item.key !== 'input-produksi') return null;

                            if (inGroup) {
                                return (
                                    <div className="au-nav-group" key="group-produksi">
                                        <button
                                            type="button"
                                            className={'au-nav-item sb-link' + (groupActive ? ' is-group-active' : '')}
                                            onClick={() => setGroupOpen((o) => !o)}
                                            aria-expanded={groupOpen}
                                            data-sb-tip="QC-Welding"
                                        >
                                            <Icon path={ICON.folder} />
                                            <span className="sb-label">QC-Welding</span>
                                            <Icon path={ICON.chevron} className={'au-chevron sb-label' + (groupOpen ? ' is-open' : '')} />
                                        </button>
                                        {/* Selalu dirender supaya buka/tutup bisa dianimasikan
                                            (tinggi 0fr → 1fr); saat tertutup dibuat inert agar
                                            tautannya tidak bisa difokus lewat Tab. */}
                                        <div className={'au-nav-lipat' + (groupOpen ? ' is-buka' : '')} inert={!groupOpen}>
                                            <div className="au-nav-children sb-sub">
                                                {groupItems.map((child, i) => (
                                                    <div className="au-nav-anak" style={{ '--urut': i }} key={child.key}>
                                                        <NavLink item={child} small active={routeMatches(child.match, routeName)} />
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                );
                            }

                            return (
                                <NavLink
                                    key={item.key}
                                    item={item}
                                    active={routeMatches(item.match, routeName)}
                                    badge={item.key === 'chatting' ? unread : 0}
                                />
                            );
                        })}
                    </nav>

                    {user ? (
                        <div className="au-sb-user sb-user" ref={userRef}>
                            <button type="button" className="au-sb-user-btn" onClick={() => setUserMenu((o) => !o)} aria-expanded={userMenu}>
                                <span className="au-avatar">
                                    {user.avatar_url ? (
                                        <img src={user.avatar_url} alt="" onError={(e) => { e.currentTarget.style.display = 'none'; }} />
                                    ) : null}
                                    <span className="au-avatar-inisial">{initial}</span>
                                </span>
                                <span className="sb-label au-sb-user-meta">
                                    <span className="au-sb-user-name">{user.name}</span>
                                    <span className="au-sb-user-role">
                                        {user.role}
                                        {user.department ? ' · ' + user.department : ''}
                                    </span>
                                </span>
                                <Icon path={ICON.chevronUp} className={'au-chevron sb-label' + (userMenu ? '' : ' is-open')} />
                            </button>
                            {userMenu ? (
                                <div className="au-sb-menu sb-menu">
                                    <p className="au-sb-menu-email">{user.email || user.username}</p>
                                    <Link href={profileUrl} className="au-sb-menu-item">
                                        <Icon path={ICON.user} /> Profil Saya
                                    </Link>
                                    <button type="button" className="au-sb-menu-item is-danger" onClick={() => router.post(logoutUrl)}>
                                        <Icon path={ICON.logout} /> Logout
                                    </button>
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                </aside>

                <div className={'au-overlay' + (sidebarOpen ? ' is-open' : '')} onClick={() => setSidebarOpen(false)} aria-hidden="true" />

                <div className="au-main" id="app-main">
                    <header className="au-topbar" id="app-header">
                        <button type="button" className="au-burger" onClick={() => setSidebarOpen(true)} aria-label="Buka menu">
                            <Icon path={ICON.burger} />
                        </button>
                        <div style={{ minWidth: 0 }}>
                            <h1 className="au-page-title">{title}</h1>
                            {subtitle ? <p className="au-page-sub">{subtitle}</p> : null}
                        </div>
                        <Jam />
                    </header>

                    <main className={'au-content' + (animasiMasuk ? ' au-masuk' : '')}>
                        {maintenanceAktif ? (
                            <div className="au-mt-banner">
                                <span>⚠ Mode maintenance aktif — pengguna selain developer hanya melihat layar maintenance.</span>
                                <a href={maintenanceAktif.settingsUrl}>Buka Settings</a>
                            </div>
                        ) : null}
                        {children}
                    </main>

                    <footer className="au-footer">
                        &copy; {new Date().getFullYear()} {appName || 'Asata Production'}. All rights reserved.
                    </footer>
                </div>
            </div>

            {sb.tip ? (
                <div className="au-sb-tip" role="tooltip" style={{ left: sb.tip.left, top: sb.tip.top }}>
                    {sb.tip.text}
                </div>
            ) : null}

            <button
                type="button"
                className={'au-ke-atas' + (atas ? ' is-tampil' : '')}
                onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                title="Kembali ke atas"
                aria-label="Kembali ke atas"
            >
                <Icon path={ICON.chevronUp} />
            </button>

            {toasts.length ? (
                <div className="au-toasts">
                    {toasts.map((t) => {
                        const isi = (
                            <>
                                <Icon path={t.type === 'error' || t.type === 'warn' ? ICON.alert : t.type === 'ok' ? ICON.check : t.type === 'note' ? ICON.clipboard : ICON.user} />
                                <span>
                                    {t.title ? <b className="au-toast-judul">{t.title}</b> : null}
                                    {t.text}
                                </span>
                            </>
                        );
                        const cls = 'au-toast au-toast--' + t.type + (t.type === 'error' ? ' au-toast--error' : '');
                        return t.href ? (
                            <Link key={t.id} href={t.href} className={cls} role="status">
                                {isi}
                            </Link>
                        ) : (
                            <div key={t.id} className={cls} role="status">
                                {isi}
                            </div>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}
