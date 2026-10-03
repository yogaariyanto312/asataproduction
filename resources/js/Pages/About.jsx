import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { DeleteButton, Icon } from '../Components/Ui';

const FITUR = [
    ['M12 4v16m8-8H4', 'Input Produksi'],
    ['M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'Laporan & Ekspor'],
    ['M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'Gambar Kerja'],
    ['M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z', 'Chat Internal'],
    ['M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'Dashboard & Grafik'],
    ['M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'Manajemen User'],
];
const TEKNOLOGI = ['Laravel 13', 'PHP 8', 'React', 'Inertia.js', 'Chart.js', 'MySQL'];
const BADGE = {
    fix: ['Fix', 'is-fix'],
    improvement: ['Improvement', 'is-improvement'],
    security: ['Security', 'is-security'],
    feature: ['Feature', 'is-feature'],
};
const PAPAN = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2';
const BUKA = 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14';
const TAMPIL = 6;

/**
 * Tentang Aplikasi — mengikuti Production-QC-Logging-System: kartu judul
 * (versi dari Riwayat Update terbaru), Tentang Sistem + fitur, kartu
 * pengembang (foto, bio, angka ringkas, tautan), portfolio, teknologi, dan
 * Riwayat Update (6 entri terlihat, sisanya digulir; form tambah developer).
 */
export default function About({ versi, year, githubUser, developer, changelogs, canManage, storeUrl, baseUrl }) {
    const { appName } = usePage().props;
    const [repo, setRepo] = useState(githubUser ? null : '—');
    const [form, setForm] = useState(false);

    useEffect(() => {
        if (!githubUser) return;
        fetch('https://api.github.com/users/' + encodeURIComponent(githubUser))
            .then((r) => r.json())
            .then((d) => setRepo(d.public_repos !== undefined ? String(d.public_repos) : '—'))
            .catch(() => setRepo('—'));
    }, [githubUser]);

    const links = developer
        ? [
              ['Instagram', developer.instagram],
              ['GitHub', developer.github],
              ['Portfolio', developer.portfolio],
              ['Email', developer.email ? 'mailto:' + developer.email : null],
          ].filter(([, url]) => url)
        : [];
    const bio = developer?.bio || 'Quality Control Engineer sekaligus developer internal sistem ini. Bertanggung jawab atas desain, pengembangan, dan pemeliharaan aplikasi.';

    return (
        <AppLayout title="Tentang Aplikasi" subtitle="Informasi sistem & pengembang">
            <div className="au-ab">
                {/* Versi diambil dari entri Riwayat Update terbaru (Changelog::versiAplikasi). */}
                <div className="au-ab-kartu au-ab-hero">
                    <span className="au-ab-hero-ikon">
                        <Icon path={PAPAN} />
                    </span>
                    <div style={{ minWidth: 0 }}>
                        <div className="au-ab-hero-judul">
                            <h1>{appName || 'Asata Production'} System</h1>
                            <span data-versi-app="" className="au-ab-versi">
                                {versi}
                            </span>
                        </div>
                        <p>Sistem Pencatatan &amp; Monitoring Produksi</p>
                    </div>
                </div>

                <div className="au-ab-kartu au-ab-pad">
                    <h2 className="au-ab-judul">Tentang Sistem</h2>
                    <p className="au-ab-teks">
                        Aplikasi ini dirancang khusus untuk membantu tim QC dalam mencatat, memantau, dan menganalisis data produksi harian secara efisien. Mulai dari input
                        produksi, manajemen produk, hingga laporan rekap bulanan semua tersedia dalam satu platform.
                    </p>
                    <div className="au-ab-fitur">
                        {FITUR.map(([ikon, label]) => (
                            <div key={label}>
                                <span>
                                    <Icon path={ikon} />
                                </span>
                                {label}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="au-ab-kartu au-ab-dev">
                    <div className="au-ab-dev-foto">
                        <div className="au-ab-dev-avatar">
                            {developer?.avatar ? (
                                <img src={developer.avatar} alt={developer.name} onError={(e) => (e.currentTarget.style.display = 'none')} />
                            ) : (
                                <span>{(developer?.name || 'Y').charAt(0).toUpperCase()}</span>
                            )}
                        </div>
                        <div style={{ textAlign: 'center' }}>
                            <p className="au-ab-dev-nama">{developer?.name || 'Developer'}</p>
                            {developer?.handle ? <p className="au-ab-dev-handle">@{developer.handle.replace(/^@/, '')}</p> : null}
                        </div>
                        <span className="au-ab-dev-chip">
                            <i />
                            Developer
                        </span>
                    </div>
                    <div className="au-ab-dev-isi">
                        {bio
                            .split('\n\n')
                            .filter(Boolean)
                            .map((p, i) => (
                                <p key={i} className="au-ab-teks" style={{ marginTop: i ? 8 : 0, whiteSpace: 'pre-line' }}>
                                    {p.trim()}
                                </p>
                            ))}
                        <div className="au-ab-angka">
                            <div>
                                <b id="github-repos">{repo ?? <span className="au-ab-memuat">···</span>}</b>
                                <small>Repo GitHub</small>
                            </div>
                            <div>
                                <b data-versi-app="" className="au-mono">
                                    {versi}
                                </b>
                                <small>Versi App</small>
                            </div>
                            <div>
                                <b>{year}</b>
                                <small>Tahun</small>
                            </div>
                        </div>
                        {links.length ? (
                            <div className="au-ab-tautan">
                                {links.map(([label, url]) => (
                                    <a key={label} href={url} target={label === 'Email' ? undefined : '_blank'} rel="noopener noreferrer">
                                        {label}
                                    </a>
                                ))}
                            </div>
                        ) : null}
                    </div>
                </div>

                {developer?.portfolio ? (
                    <div className="au-ab-kartu" style={{ overflow: 'hidden' }}>
                        <div className="au-ab-kepala">
                            <h2 className="au-ab-judul" style={{ flex: 1 }}>
                                Portfolio
                            </h2>
                            <a className="au-ab-buka" href={developer.portfolio} target="_blank" rel="noopener noreferrer">
                                <Icon path={BUKA} />
                                Buka
                            </a>
                        </div>
                        <div className="au-ab-portfolio">
                            <iframe src={developer.portfolio} title="Portfolio" loading="lazy" allowFullScreen />
                        </div>
                    </div>
                ) : null}

                <div className="au-ab-kartu au-ab-pad">
                    <h2 className="au-ab-judul">Teknologi</h2>
                    <div className="au-ab-tek">
                        {TEKNOLOGI.map((t) => (
                            <span key={t}>{t}</span>
                        ))}
                    </div>
                </div>

                <Riwayat changelogs={changelogs} canManage={canManage} storeUrl={storeUrl} baseUrl={baseUrl} form={form} setForm={setForm} />
            </div>
        </AppLayout>
    );
}

function Riwayat({ changelogs, canManage, storeUrl, baseUrl, form, setForm }) {
    const kotak = useRef(null);
    const [sisa, setSisa] = useState(0);
    const { data, setData, post, processing, errors, reset } = useForm({ type: 'feature', version: '', title: '', description: '' });

    /* Hanya 6 entri yang terlihat; tinggi diukur dari posisi entri ke-7 karena
       tiap entri beda tinggi (deskripsi opsional). */
    useLayoutEffect(() => {
        const atur = () => {
            const el = kotak.current;
            if (!el) return;
            const entri = el.querySelectorAll('[data-entri]');
            el.style.maxHeight = '';
            if (entri.length <= TAMPIL) return setSisa(0);
            const tinggi = entri[TAMPIL].offsetTop - entri[0].offsetTop;
            if (tinggi > 0) el.style.maxHeight = tinggi + 'px';
            setSisa(entri.length - TAMPIL);
        };
        atur();
        window.addEventListener('resize', atur);
        return () => window.removeEventListener('resize', atur);
    }, [changelogs]);

    function simpan(e) {
        e.preventDefault();
        post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setForm(false);
            },
        });
    }

    return (
        <div className="au-ab-kartu" style={{ overflow: 'hidden' }}>
            <div className="au-ab-kepala">
                <h2 className="au-ab-judul">Riwayat Update</h2>
                {changelogs.length ? <span className="au-ab-jumlah">{changelogs.length}</span> : null}
                <span style={{ flex: 1 }} />
                {canManage ? (
                    <button type="button" className="au-ab-tambah" onClick={() => setForm(!form)}>
                        <Icon path="M12 4v16m8-8H4" />
                        Tambah
                    </button>
                ) : null}
            </div>

            {canManage && form ? (
                <div id="changelog-form" className="au-ab-form">
                    <form method="POST" action={storeUrl} onSubmit={simpan}>
                        <div className="au-form-grid au-form-grid-2">
                            <div>
                                <label className="au-ab-label">Tipe</label>
                                <select className="au-select" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                    <option value="feature">Feature</option>
                                    <option value="fix">Fix</option>
                                    <option value="improvement">Improvement</option>
                                    <option value="security">Security</option>
                                </select>
                            </div>
                            <div>
                                <label className="au-ab-label">
                                    Versi <span className="au-redup">(opsional)</span>
                                </label>
                                <input className="au-input au-mono" placeholder="v1.2" value={data.version} onChange={(e) => setData('version', e.target.value)} />
                                <p className="au-ab-kecil">Versi terbaru yang diisi di sini menjadi versi aplikasi di halaman ini.</p>
                                {errors.version ? <span className="au-error">{errors.version}</span> : null}
                            </div>
                        </div>
                        <div>
                            <label className="au-ab-label">
                                Judul <span className="au-req">*</span>
                            </label>
                            <input
                                className={'au-input' + (errors.title ? ' has-error' : '')}
                                required
                                placeholder="Contoh: Tambah fitur changelog di halaman About"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                            />
                            {errors.title ? <span className="au-error">{errors.title}</span> : null}
                        </div>
                        <div>
                            <label className="au-ab-label">
                                Deskripsi <span className="au-redup">(opsional)</span>
                            </label>
                            <textarea
                                className="au-textarea"
                                rows={4}
                                style={{ resize: 'none' }}
                                placeholder="Penjelasan lebih detail tentang perubahan ini..."
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                        </div>
                        <div className="au-ab-form-aksi">
                            <button type="button" className="au-ab-batal" onClick={() => setForm(false)}>
                                Batal
                            </button>
                            <button type="submit" className="au-ab-simpan" disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </button>
                        </div>
                    </form>
                </div>
            ) : null}

            {changelogs.length ? (
                <>
                    <div ref={kotak} data-riwayat="" className="au-ab-riwayat no-scrollbar">
                        {changelogs.map((c) => {
                            const [label, kelas] = BADGE[c.type] || BADGE.feature;
                            return (
                                <div data-entri="" className="au-ab-entri" key={c.id}>
                                    <div className="au-ab-entri-kiri">
                                        <span className={'au-ab-badge ' + kelas}>{label}</span>
                                        <small>{c.date}</small>
                                    </div>
                                    <div style={{ minWidth: 0, flex: 1 }}>
                                        <div className="au-ab-entri-judul">
                                            <p>{c.title}</p>
                                            {c.version ? <span className="au-mono">{c.version}</span> : null}
                                        </div>
                                        {c.description ? <p className="au-ab-entri-ket">{c.description}</p> : null}
                                    </div>
                                    {canManage ? (
                                        <span className="au-ab-hapus">
                                            <DeleteButton url={baseUrl + '/' + c.id} title="Hapus Changelog" text="Hapus entry changelog ini?" />
                                        </span>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>
                    {sisa > 0 ? <div className="au-ab-sisa">{sisa} update lainnya — gulir untuk melihat</div> : null}
                </>
            ) : (
                <div className="au-ab-kosong">Belum ada riwayat update.</div>
            )}
        </div>
    );
}
