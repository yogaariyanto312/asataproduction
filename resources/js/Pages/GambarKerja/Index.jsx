import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON, Icon, Input } from '../../Components/Ui';
import { konfirmasi } from '../../dialog';
import {
    BATAS_CACHE,
    alasanTakBisaSimpan,
    berkasTersimpan,
    bersihkanBerkas,
    ukuran,
    unduhSemua,
} from '../../offlineFiles';

const CAL = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';
const DOC =
    'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
const CHEV = 'M9 5l7 7-7 7';
const URUTAN = [
    ['seri', 'Seri terkecil'],
    ['seri_desc', 'Seri terbesar'],
    ['kva', 'KVA terkecil'],
    ['kva_desc', 'KVA terbesar'],
    ['judul', 'Judul A–Z'],
];

/**
 * Sub-kelompok per jenis seri, persis versi Blade: Standar/PLN abu-abu,
 * Swasta amber, Typetest ungu.
 */
const SUBS = [
    { key: 'pln', label: 'Standar / PLN', tone: 'slate', badge: null },
    { key: 'swasta', label: 'Seri Swasta', tone: 'amber', badge: 'Seri Swasta' },
    { key: 'typetest', label: 'Seri Typetest', tone: 'purple', badge: 'Seri Typetest' },
];

/**
 * Tata letak versi lama: panel filter, seksi per TAHUN (pill biru + garis +
 * jumlah dokumen), lalu sub-seksi per jenis seri, dan kartu bergambar dengan
 * jumlah berkas, judul, seri, keterangan, tautan "Lihat gambar", serta tombol
 * hapus satu kelompok di kaki kartu.
 */
export default function GambarKerjaIndex({
    years,
    search,
    sort,
    indexUrl,
    createUrl,
    groupUrl,
    destroyUrl,
    pollUrl,
    berkasUrl,
    can,
}) {
    const [q, setQ] = useState(search || '');
    const [urut, setUrut] = useState(sort || 'seri');
    // Dibaca saat jeda ketik selesai, supaya urutan yang baru dipilih tidak tertimpa.
    const urutRef = useRef(urut);
    urutRef.current = urut;

    // Disaring sambil mengetik (tanpa Enter), seperti referensi: hanya daftar
    // yang diambil ulang, kotak cari tetap fokus.
    const pertama = useRef(true);
    useEffect(() => {
        if (pertama.current) {
            pertama.current = false;
            return undefined;
        }
        const t = setTimeout(() => muat(q, urutRef.current), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    function muat(cari, urutan) {
        const param = {};
        if (cari) param.search = cari;
        if (urutan && urutan !== 'seri') param.sort = urutan;
        router.get(indexUrl, param, { preserveState: true, preserveScroll: true, replace: true, only: ['years', 'search', 'sort'] });
    }

    // Auto-refresh ringan: cek sinyal tiap 20 dtk, muat ulang hanya bila ada
    // perubahan (upload/hapus dari pengguna lain) tanpa mengganggu kotak cari.
    const stamp = useRef(null);
    useEffect(() => {
        if (!pollUrl) return undefined;
        let alive = true;
        async function poll() {
            try {
                const res = await fetch(pollUrl, { credentials: 'same-origin' });
                if (!res.ok || !alive) return;
                const d = await res.json();
                const key = d.ts + '|' + d.count;
                if (stamp.current !== null && stamp.current !== key) {
                    router.reload({ only: ['years'], preserveScroll: true });
                }
                stamp.current = key;
            } catch (_) {
                /* abaikan — jaringan sementara */
            }
        }
        const id = setInterval(poll, 20000);
        poll();
        return () => {
            alive = false;
            clearInterval(id);
        };
    }, [pollUrl]);

    function applyFilter(e) {
        e.preventDefault();
        muat(q, urut);
    }

    function resetFilter() {
        setQ('');
        setUrut('seri');
        router.get(indexUrl, {}, { preserveState: true, replace: true });
    }

    async function hapusKelompok(g) {
        const ok = await konfirmasi('Hapus semua ' + g.total + " file gambar kerja '" + g.judul + "'?", {
            title: 'Hapus Gambar Kerja',
            okText: 'Hapus Semua',
        });
        if (!ok) return;
        router.delete(destroyUrl, {
            data: { judul: g.judul, seri: g.seri, kva: g.kva, tahun: g.tahun },
            preserveScroll: true,
        });
    }

    const total = years.reduce((s, y) => s + y.groups.length, 0);

    return (
        <AppLayout title="Gambar Kerja" subtitle="Dokumen gambar teknik produksi">
            <div className="au-panel">
                <form className="au-filter" onSubmit={applyFilter}>
                    <div className="au-filter-field is-wide">
                        <label className="au-filter-label">Cari Judul / Seri / KVA</label>
                        <Input
                            value={q}
                            placeholder="Nama judul, seri, atau KVA..."
                            autoComplete="off"
                            onChange={(e) => setQ(e.target.value)}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Urutkan</label>
                        <select
                            className="au-select"
                            value={urut}
                            onChange={(e) => {
                                setUrut(e.target.value);
                                muat(q, e.target.value);
                            }}
                        >
                            {URUTAN.map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="au-filter-actions">
                        <Btn type="submit">Filter</Btn>
                        <Btn tone="ghost" onClick={resetFilter}>
                            Reset
                        </Btn>
                        {can.upload ? (
                            <Btn as="a" href={createUrl} icon={ICON.plus} className="au-btn--green">
                                Upload Gambar
                            </Btn>
                        ) : null}
                    </div>
                </form>
            </div>

            {berkasUrl ? <SimpanPerangkat berkasUrl={berkasUrl} /> : null}

            {total ? (
                years.map((y) => (
                    <div className="au-section" key={y.year}>
                        <div className="au-section-head">
                            <span className="au-section-pill">
                                <Icon path={CAL} />
                                {y.year}
                            </span>
                            <span className="au-section-rule" />
                            <span className="au-section-count">{y.groups.length} dokumen</span>
                        </div>

                        {SUBS.map((sub) => {
                            const items = y.groups.filter(
                                (g) => (g.kategori || 'pln') === sub.key,
                            );
                            if (!items.length) return null;

                            return (
                                <div className="au-subsection" key={y.year + '-' + sub.key}>
                                    <div className="au-subsection-head">
                                        <span className={'au-dot au-dot--' + sub.tone} />
                                        <span className={'au-subsection-label is-' + sub.tone}>
                                            {sub.label}
                                        </span>
                                        <span className="au-section-rule" />
                                        <span className="au-section-count">
                                            {items.length} dokumen
                                        </span>
                                    </div>

                                    <div className="au-gk-grid">
                                        {items.map((g, i) => (
                                            <div className="au-gk-card" key={g.judul + '-' + i}>
                                                <a
                                                    className="au-gk-link"
                                                    href={
                                                        groupUrl +
                                                        '?' +
                                                        new URLSearchParams(g.query)
                                                    }
                                                >
                                                    <div className="au-gk-thumb">
                                                        {g.thumbnail ? (
                                                            <img
                                                                src={g.thumbnail}
                                                                alt={g.judul}
                                                                onError={(e) => {
                                                                    e.currentTarget.style.display =
                                                                        'none';
                                                                }}
                                                            />
                                                        ) : g.isPdf ? (
                                                            <span className="au-gk-pdf">
                                                                <Icon path={DOC} />
                                                                PDF
                                                            </span>
                                                        ) : (
                                                            <span className="au-gk-noimg">
                                                                <Icon path={DOC} />
                                                            </span>
                                                        )}
                                                        <span className="au-gk-count">
                                                            {g.total} file
                                                        </span>
                                                    </div>

                                                    <div className="au-gk-body">
                                                        <h3 className="au-gk-title">{g.judul}</h3>

                                                        {sub.badge ? (
                                                            <span
                                                                className={
                                                                    'au-prodtag au-gk-tag--' +
                                                                    sub.tone
                                                                }
                                                            >
                                                                {sub.badge}
                                                            </span>
                                                        ) : null}

                                                        {g.seri || g.kva ? (
                                                            <p className="au-gk-meta">
                                                                {g.seri}
                                                                {g.kva ? '-' + g.kva + 'KVA' : ''}
                                                            </p>
                                                        ) : null}

                                                        {g.keterangan ? (
                                                            <p className="au-gk-note">
                                                                {g.keterangan}
                                                            </p>
                                                        ) : null}

                                                        <span className="au-gk-more">
                                                            Lihat gambar
                                                            <Icon path={CHEV} />
                                                        </span>
                                                    </div>
                                                </a>

                                                {can.delete ? (
                                                    <div className="au-gk-foot">
                                                        <button
                                                            type="button"
                                                            className="au-gk-del"
                                                            onClick={() => hapusKelompok(g)}
                                                        >
                                                            <Icon path={ICON.trash} />
                                                            Hapus Semua
                                                        </button>
                                                    </div>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ))
            ) : (
                <div className="au-blank">
                    <Icon path={DOC} />
                    <p>Belum ada gambar kerja</p>
                    {can.upload ? (
                        <Btn as="a" href={createUrl}>
                            Upload Sekarang
                        </Btn>
                    ) : null}
                </div>
            )}
        </AppLayout>
    );
}

/**
 * "Unduh Semua": simpan seluruh gambar kerja di perangkat lebih dulu, supaya
 * saat dibutuhkan langsung terbuka tanpa menunggu unduhan. Penyimpanannya
 * dikerjakan service worker (public/sw.js) — sama dengan cache "pernah dibuka".
 * Sengaja dipicu manual: di koneksi 10 Mbps, mengunduh semua otomatis akan
 * menyita jaringan bersama.
 */
function SimpanPerangkat({ berkasUrl }) {
    const alasan = alasanTakBisaSimpan();
    const [daftar, setDaftar] = useState(null);
    const [tersimpan, setTersimpan] = useState({ jumlah: 0, byte: 0, set: new Set() });
    const [jalan, setJalan] = useState(null); // { selesai, total, byte, gagal }
    const [pesan, setPesan] = useState('');
    const batalRef = useRef(null);

    async function periksa(list) {
        const hasil = await berkasTersimpan(list.map((b) => b.url));
        setTersimpan(hasil);
        return hasil;
    }

    useEffect(() => {
        if (alasan) return undefined;
        let alive = true;
        fetch(berkasUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then(async (d) => {
                if (!alive || !d) return;
                setDaftar(d.berkas || []);
                await periksa(d.berkas || []);
            })
            .catch(() => {});
        return () => {
            alive = false;
            batalRef.current?.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [berkasUrl]);

    if (alasan) {
        return (
            <div id="gk-offline" className="au-gk-off">
                <div className="au-gk-off-baris">
                    <span className="au-gk-off-ikon is-off">
                        <Icon path={ICON.download} />
                    </span>
                    <div className="au-gk-off-info">
                        <p className="au-gk-off-judul">Simpan di perangkat</p>
                        <p className="au-gk-off-status">{alasan}</p>
                    </div>
                </div>
            </div>
        );
    }

    const totalByte = (daftar || []).reduce((n, b) => n + (b.ukuran || 0), 0);
    const kurang = (daftar || []).filter((b) => !tersimpan.set.has(b.url));
    const kurangByte = kurang.reduce((n, b) => n + (b.ukuran || 0), 0);
    const lengkap = !!daftar && daftar.length > 0 && kurang.length === 0;
    const terlaluBesar = totalByte > BATAS_CACHE;

    async function mulai() {
        if (!kurang.length) return;
        const ctrl = new AbortController();
        batalRef.current = ctrl;
        setPesan('');
        setJalan({ selesai: 0, total: kurang.length, byte: 0, gagal: 0 });
        const hasil = await unduhSemua(kurang, {
            sinyal: ctrl.signal,
            onKemajuan: (k) => setJalan({ ...k }),
        });
        setJalan(null);
        batalRef.current = null;
        await periksa(daftar);
        if (ctrl.signal.aborted) setPesan('Dibatalkan.');
        else if (hasil.gagal) setPesan(hasil.gagal + ' berkas gagal diunduh — coba lagi.');
    }

    function batal() {
        batalRef.current?.abort();
    }

    async function hapus() {
        if (!(await konfirmasi('Hapus semua gambar kerja yang tersimpan di perangkat ini?', { title: 'Kosongkan Penyimpanan', type: 'warning', okText: 'Hapus' }))) return;
        await bersihkanBerkas();
        await periksa(daftar || []);
        setPesan('Penyimpanan di perangkat dikosongkan.');
    }

    let status;
    if (!daftar) status = 'Memeriksa…';
    else if (!daftar.length) status = 'Belum ada berkas.';
    else if (jalan) {
        status = 'Mengunduh ' + jalan.selesai + ' / ' + jalan.total + ' berkas · ' + ukuran(jalan.byte);
    } else if (lengkap) {
        status =
            'Semua ' + daftar.length + ' berkas tersimpan (' + ukuran(tersimpan.byte) +
            ') — bisa dibuka tanpa menunggu.';
    } else {
        status =
            tersimpan.jumlah + ' dari ' + daftar.length + ' berkas tersimpan · perlu unduh ' +
            ukuran(kurangByte) + ' lagi';
    }

    const persen = jalan && jalan.total ? Math.round((jalan.selesai / jalan.total) * 100) : 0;

    return (
        <div id="gk-offline" className="au-gk-off">
            <div className="au-gk-off-baris">
                <span className={'au-gk-off-ikon' + (lengkap ? ' is-ok' : '')}>
                    <Icon path={lengkap ? ICON.check : ICON.download} />
                </span>
                <div className="au-gk-off-info">
                    <p className="au-gk-off-judul">Simpan di perangkat</p>
                    <p className="au-gk-off-status">{status}</p>
                    {terlaluBesar ? (
                        <p className="au-gk-off-status au-gk-off-warn">
                            Total {ukuran(totalByte)} melebihi batas {ukuran(BATAS_CACHE)} — berkas terlama
                            akan tergeser.
                        </p>
                    ) : null}
                    {pesan ? <p className="au-gk-off-status">{pesan}</p> : null}
                </div>
                <div className="au-gk-off-aksi">
                    {jalan ? (
                        <Btn tone="ghost" sm onClick={batal}>
                            Batal
                        </Btn>
                    ) : (
                        <>
                            {!lengkap && daftar && daftar.length ? (
                                <Btn sm icon={ICON.download} onClick={mulai}>
                                    Unduh Semua
                                </Btn>
                            ) : null}
                            {tersimpan.jumlah ? (
                                <Btn tone="ghost" sm onClick={hapus}>
                                    Hapus
                                </Btn>
                            ) : null}
                        </>
                    )}
                </div>
            </div>
            {jalan ? (
                <div className="au-gk-off-bilah">
                    <span style={{ width: persen + '%' }} />
                </div>
            ) : null}
        </div>
    );
}
