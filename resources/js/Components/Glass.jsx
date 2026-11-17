import { useEffect, useRef, useState } from 'react';

/*
 * Komponen tampilan kaca (PRD 11): latar, pemilih tampilan, dan pembantu tema.
 * Pilihan disimpan di localStorage dan dipasang ke <html> oleh skrip di inertia.blade.php
 * sebelum CSS dimuat, supaya tidak berkedip.
 *   au:tema   'terang' | 'gelap'      (nama lama dipertahankan, dibaca Dashboard dan skrip lain)
 *   au:warna  'coral' | 'violet' | 'ocean'
 *   au:ringan '1'  mode ringan (tanpa blur dan animasi latar)
 *   au:intro  '0'  layar pembuka dimatikan
 */

export const TAMPILAN = [
    { k: 'coral', nama: 'Coral', a: '#f07a5f', b: '#d6432e' },
    { k: 'violet', nama: 'Violet', a: '#9a82ff', b: '#6240de' },
    { k: 'ocean', nama: 'Ocean', a: '#2cbfb5', b: '#0c7d77' },
    { k: 'gelap', nama: 'Gelap', a: '#3b82f6', b: '#101a36' },
];

const simpan = (k, v) => {
    try {
        localStorage.setItem(k, v);
    } catch (_) {
        /* mode privat: berlaku sampai halaman ditutup */
    }
};
const baca = (k) => {
    try {
        return localStorage.getItem(k);
    } catch (_) {
        return null;
    }
};

export function tampilanSekarang() {
    const d = document.documentElement;
    return d.dataset.tema === 'gelap' ? 'gelap' : d.dataset.warna || 'coral';
}

export function terapkanTampilan(k) {
    const d = document.documentElement;
    if (k === 'gelap') {
        d.dataset.tema = 'gelap';
        delete d.dataset.warna;
        d.classList.add('dark');
        simpan('au:tema', 'gelap');
    } else {
        d.dataset.tema = 'terang';
        d.dataset.warna = k;
        d.classList.remove('dark');
        simpan('au:tema', 'terang');
        simpan('au:warna', k);
    }
    window.dispatchEvent(new Event('au:tema'));
}

export const ringanAktif = () => document.documentElement.classList.contains('g-ringan');

export function aturRingan(on) {
    document.documentElement.classList.toggle('g-ringan', on);
    simpan('au:ringan', on ? '1' : '0');
    window.dispatchEvent(new Event('au:tema'));
}

export const introAktif = () => baca('au:intro') !== '0';
export const aturIntro = (on) => simpan('au:intro', on ? '1' : '0');

/** Latar ruangan buram, murni CSS (tanpa gambar). */
export function Scene() {
    return (
        <div className="gl-scene" aria-hidden="true">
            <i className="gl-wall" />
            <i className="gl-window" />
            <i className="gl-lamp" />
            <i className="gl-floor" />
            <i className="gl-vignette" />
        </div>
    );
}

export function Saklar({ nyala, ubah, label }) {
    return <button type="button" role="switch" aria-checked={nyala} aria-label={label} className="gl-saklar" onClick={ubah} />;
}

const IKON_TAMPILAN = (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <circle cx="13.5" cy="6.5" r="1" /><circle cx="17.5" cy="10.5" r="1" /><circle cx="8.5" cy="7.5" r="1" /><circle cx="6.5" cy="12.5" r="1" />
        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.75 1.5-1.5 0-.4-.15-.74-.4-1-.24-.27-.4-.6-.4-1 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-4.42-4.48-8-10-8z" />
    </svg>
);

/** Tombol "Tampilan" di header: pilih tema, mode ringan, dan layar pembuka. */
export function PemilihTampilan() {
    const [buka, setBuka] = useState(false);
    const [pilih, setPilih] = useState(tampilanSekarang);
    const [ringan, setRingan] = useState(ringanAktif);
    const [intro, setIntro] = useState(introAktif);
    const ref = useRef(null);

    useEffect(() => {
        const sinkron = () => {
            setPilih(tampilanSekarang());
            setRingan(ringanAktif());
        };
        window.addEventListener('au:tema', sinkron);
        return () => window.removeEventListener('au:tema', sinkron);
    }, []);

    useEffect(() => {
        if (!buka) return undefined;
        const luar = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setBuka(false);
        };
        const esc = (e) => e.key === 'Escape' && setBuka(false);
        document.addEventListener('mousedown', luar);
        document.addEventListener('keydown', esc);
        return () => {
            document.removeEventListener('mousedown', luar);
            document.removeEventListener('keydown', esc);
        };
    }, [buka]);

    return (
        <div className="gl-tampilan" ref={ref}>
            <button type="button" className="gl-tombol" onClick={() => setBuka((b) => !b)} aria-haspopup="true" aria-expanded={buka} title="Tampilan" aria-label="Tampilan">
                {IKON_TAMPILAN}
            </button>
            {buka ? (
                <div className="gl-pop" role="dialog" aria-label="Pengaturan tampilan">
                    <p className="gl-pop-judul">Tema</p>
                    <div className="gl-swatch-baris">
                        {TAMPILAN.map((t) => (
                            <button
                                type="button"
                                key={t.k}
                                className="gl-swatch"
                                aria-pressed={pilih === t.k}
                                title={t.nama}
                                onClick={() => {
                                    terapkanTampilan(t.k);
                                    setPilih(t.k);
                                }}
                            >
                                <i style={{ background: `linear-gradient(135deg, ${t.a}, ${t.b})` }} />
                                <span>{t.nama}</span>
                            </button>
                        ))}
                    </div>
                    <div className="gl-pop-baris">
                        <span>
                            <b>Mode ringan</b>
                            <small>Tanpa blur dan animasi latar. Hemat baterai dan lebih lancar di HP lama.</small>
                        </span>
                        <Saklar nyala={ringan} label="Mode ringan" ubah={() => { aturRingan(!ringan); setRingan(!ringan); }} />
                    </div>
                    <div className="gl-pop-baris">
                        <span>
                            <b>Layar pembuka</b>
                            <small>Animasi pembuka sebelum login (sekali per sesi) dan animasi trafo sesudah berhasil masuk.</small>
                        </span>
                        <Saklar nyala={intro} label="Layar pembuka" ubah={() => { aturIntro(!intro); setIntro(!intro); }} />
                    </div>
                </div>
            ) : null}
        </div>
    );
}

/** Tombol pencarian menu (membuka palet perintah, Ctrl+K). */
export function TombolCari({ onBuka }) {
    const mac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || '');
    return (
        <button type="button" className="gl-cari" onClick={onBuka} aria-label="Cari menu atau aksi (Ctrl+K)" title="Cari menu atau aksi">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4.5 4.5" /></svg>
            <span>Cari…</span>
            <span className="gl-kbd">{mac ? '⌘K' : 'Ctrl K'}</span>
        </button>
    );
}

/**
 * Angka berputar (odometer): tiap digit adalah gulungan 0-9 yang digeser. Mulai dari 0 lalu bergeser
 * ke nilai sebenarnya; saat nilai berubah (data live) digit bergeser lagi. Teks asli tetap dibaca pembaca layar.
 */
export function Odometer({ nilai }) {
    const teks = Math.round(Number(nilai) || 0).toLocaleString('id-ID');
    const [mulai, setMulai] = useState(false);
    useEffect(() => {
        const id = requestAnimationFrame(() => requestAnimationFrame(() => setMulai(true)));
        return () => cancelAnimationFrame(id);
    }, []);
    return (
        <span className="gl-odo" role="text" aria-label={teks}>
            {[...teks].map((c, i) =>
                /\d/.test(c) ? (
                    <span className="gl-odo-d" key={i} aria-hidden="true">
                        <i style={{ '--n': mulai ? Number(c) : 0, '--td': i * 60 + 'ms' }}>
                            {'0123456789'.split('').map((x) => <b key={x}>{x}</b>)}
                        </i>
                    </span>
                ) : (
                    <span className="gl-odo-s" key={i} aria-hidden="true">{c}</span>
                ),
            )}
        </span>
    );
}

/** Grafik mini mulus (kurva Catmull-Rom) dengan titik di nilai terakhir. */
export function Sparkline({ data, label }) {
    if (!data || data.length < 2) return null;
    const w = 74;
    const h = 28;
    const pad = 3;
    const max = Math.max(...data);
    const min = Math.min(...data);
    const pts = data.map((v, i) => [pad + (i * (w - 2 * pad)) / (data.length - 1), h - pad - ((v - min) / (max - min || 1)) * (h - 2 * pad)]);
    let d = `M${pts[0][0]},${pts[0][1]}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i - 1] || pts[i];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[i + 2] || p2;
        d += `C${p1[0] + (p2[0] - p0[0]) / 6},${p1[1] + (p2[1] - p0[1]) / 6} ${p2[0] - (p3[0] - p1[0]) / 6},${p2[1] - (p3[1] - p1[1]) / 6} ${p2[0]},${p2[1]}`;
    }
    const akhir = pts[pts.length - 1];
    return (
        <svg className="gl-spark" viewBox={`0 0 ${w} ${h}`} role="img" aria-label={label || 'Grafik mini'}>
            <path className="gl-spark-ar" d={`${d}L${akhir[0]},${h}L${pts[0][0]},${h}Z`} />
            <path className="gl-spark-ln" d={d} pathLength="1" />
            <circle className="gl-spark-dot" cx={akhir[0]} cy={akhir[1]} r="2.6" />
        </svg>
    );
}

/**
 * Cincin komposisi: satu busur per butir ({ nama, nilai }), isi tengah = jumlah nilai, legenda di bawahnya.
 * Busur putih dengan tingkat transparansi berbeda supaya terbaca di atas hero bergradasi apa pun temanya.
 */
const NADA = [1, 0.78, 0.58, 0.44, 0.32, 0.24, 0.18];

/** Angka yang menghitung naik dari 0 (easing keluar), seperti sampel. Tanpa animasi bila mode ringan atau kurangi gerak. */
export function Hitung({ nilai, durasi = 1500, tunda = 0 }) {
    const akhir = Math.round(Number(nilai) || 0);
    const diam = () => ringanAktif() || (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const [v, setV] = useState(() => (diam() ? akhir : 0));
    const dari = useRef(diam() ? akhir : 0); // nilai yang sedang tampil: pembaruan berikutnya bergerak dari sini, bukan dari 0
    useEffect(() => {
        if (diam()) {
            dari.current = akhir;
            setV(akhir);
            return undefined;
        }
        const awal = dari.current;
        const jeda = awal === 0 ? tunda : 0; // jeda pembuka hanya saat pertama kali
        let raf = 0;
        const mulai = performance.now() + jeda;
        const langkah = (t) => {
            const p = Math.min(1, Math.max(0, (t - mulai) / durasi));
            dari.current = Math.round(awal + (akhir - awal) * (1 - Math.pow(1 - p, 3)));
            setV(dari.current);
            if (p < 1) raf = requestAnimationFrame(langkah);
        };
        raf = requestAnimationFrame(langkah);
        return () => cancelAnimationFrame(raf);
    }, [akhir, durasi, tunda]);
    return <>{v.toLocaleString('id-ID')}</>;
}

export function CincinKomposisi({ data, judul, satuan }) {
    const daftar = (data || []).map((d) => ({ nama: d.nama, nilai: Number(d.nilai) || 0, avatar: d.avatar })).filter((d) => d.nilai > 0);
    const total = daftar.reduce((a, d) => a + d.nilai, 0);
    const r = 46;
    const k = 2 * Math.PI * r;
    const celah = daftar.filter((d) => d.nilai > 0).length > 1 ? 2.5 : 0;
    const [maju, setMaju] = useState(false);
    useEffect(() => {
        const id = setTimeout(() => setMaju(true), 700);
        return () => clearTimeout(id);
    }, []);
    let mulai = 0;
    const ringkas = daftar.map((d) => `${d.nama} ${d.nilai} ${satuan}`).join(', ') || 'belum ada data';
    return (
        <div className="gl-ring-wadah">
            <p className="gl-ring-judul">{judul}</p>
            <div className="gl-ring" role="img" aria-label={`${judul}: ${ringkas}`}>
                <svg className="gl-ring-svg" viewBox="0 0 120 120" aria-hidden="true">
                    <circle className="gl-ring-trek" cx="60" cy="60" r={r} />
                    {total > 0
                        ? daftar.map((d, i) => {
                              const bagian = (d.nilai / total) * k;
                              const panjang = Math.max(0, bagian - celah);
                              const awal = mulai;
                              mulai += bagian;
                              if (d.nilai <= 0) return null;
                              return (
                                  <circle
                                      key={d.nama + i}
                                      className="gl-ring-bar"
                                      cx="60"
                                      cy="60"
                                      r={r}
                                      style={{ stroke: `rgba(255,255,255,${NADA[i % NADA.length]})` }}
                                      strokeDasharray={maju ? `${panjang} ${k - panjang}` : `0 ${k}`}
                                      strokeDashoffset={-awal}
                                      transform="rotate(-90 60 60)"
                                  >
                                      <title>{`${d.nama}: ${d.nilai.toLocaleString('id-ID')} ${satuan} (${Math.round((d.nilai / total) * 100)}%)`}</title>
                                  </circle>
                              );
                          })
                        : null}
                </svg>
                <div className="gl-ring-isi">
                    <strong><Hitung nilai={total} durasi={1600} tunda={700} /></strong>
                    <span>{total > 0 ? `total ${satuan}` : 'belum ada data'}</span>
                </div>
                {daftar.map((d, i) => (
                    <span className="gl-ring-avatar" key={d.nama + i}
                        style={{ '--sudut': `${(i * 360) / daftar.length - 15}deg`, '--k': i }}
                        tabIndex={0} data-tip={`${d.nama} · ${d.nilai.toLocaleString('id-ID')} ${satuan}`}
                        aria-label={`${d.nama}: ${d.nilai.toLocaleString('id-ID')} ${satuan}`}>
                        <span>{d.nama.trim().split(/\s+/).slice(0, 2).map((n) => n[0]).join('').toUpperCase() || '?'}</span>
                        {d.avatar ? <img src={d.avatar} alt={`Foto ${d.nama}`} width="40" height="40" decoding="async"
                            onError={(e) => { e.currentTarget.style.display = 'none'; }} /> : null}
                    </span>
                ))}
            </div>
        </div>
    );
}
