import { useEffect, useLayoutEffect, useRef, useState } from 'react';

/*
 * Grafik bergaya kaca (PRD 11), meniru kartu "Revenue" dan "Revenue by channel" di sampel glassy-dashboard:
 * garis mulus (kurva monoton) dengan area berpendar, tooltip gelap, kursor vertikal; donat tipis dengan
 * irisan yang menyorot saat disentuh. SVG murni, tanpa Chart.js: halaman Dashboard tidak lagi mengunduh pustaka grafik.
 * Warna lewat variabel CSS tema (--g-*), jadi ikut berganti saat tema berganti.
 */

const fmt = (n) => Math.round(Number(n) || 0).toLocaleString('id-ID');

/** Kurva mulus lewat titik-titik (interpolasi kubik monoton, tidak melampaui data). */
function monoton(pts) {
    const n = pts.length;
    if (n < 2) return '';
    const x = pts.map((p) => p[0]);
    const y = pts.map((p) => p[1]);
    const dx = [];
    const m = [];
    const t = new Array(n);
    for (let i = 0; i < n - 1; i++) {
        dx[i] = x[i + 1] - x[i];
        m[i] = (y[i + 1] - y[i]) / dx[i];
    }
    t[0] = m[0];
    t[n - 1] = m[n - 2];
    for (let i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
    for (let i = 0; i < n - 1; i++) {
        if (m[i] === 0) {
            t[i] = t[i + 1] = 0;
            continue;
        }
        const a = t[i] / m[i];
        const b = t[i + 1] / m[i];
        const s = a * a + b * b;
        if (s > 9) {
            const k = 3 / Math.sqrt(s);
            t[i] = k * a * m[i];
            t[i + 1] = k * b * m[i];
        }
    }
    let d = `M${x[0].toFixed(2)} ${y[0].toFixed(2)}`;
    for (let i = 0; i < n - 1; i++) {
        const h = dx[i] / 3;
        d += `C${(x[i] + h).toFixed(2)} ${(y[i] + t[i] * h).toFixed(2)} ${(x[i + 1] - h).toFixed(2)} ${(y[i + 1] - t[i + 1] * h).toFixed(2)} ${x[i + 1].toFixed(2)} ${y[i + 1].toFixed(2)}`;
    }
    return d;
}

/** Batas atas dan garis bantu yang rapi (kelipatan 1, 2, 5 x 10^n (bilangan bulat)), paling banyak 5 garis. */
function skala(maks) {
    const mentah = Math.max(maks, 1) / 4;
    const mag = Math.pow(10, Math.floor(Math.log10(mentah)));
    const langkah = Math.max(1, ([1, 2, 5, 10].find((s) => s * mag >= mentah) || 10) * mag);
    const atas = Math.max(langkah, Math.ceil(maks / langkah) * langkah);
    const garis = [];
    for (let v = 0; v <= atas + 1e-9; v += langkah) garis.push(v);
    return { atas, garis };
}

function useLebar(ref) {
    const [w, setW] = useState(0);
    useLayoutEffect(() => {
        const el = ref.current;
        if (!el) return undefined;
        setW(el.clientWidth);
        const ro = new ResizeObserver(() => setW(el.clientWidth));
        ro.observe(el);
        return () => ro.disconnect();
    }, [ref]);
    return w;
}

/**
 * Grafik garis dua seri dengan area di bawah seri pertama.
 * data: [{ date, total, avg, entries, top, top_qty }] urut waktu.
 */
export function GrafikGaris({ data, namaUtama = 'Total Unit', namaKedua = 'Rata-rata / input' }) {
    const ref = useRef(null);
    const lebar = useLebar(ref);
    const [aktif, setAktif] = useState(-1);
    // Indeks terakhir yang disorot: garis bidik, titik, dan tooltip tetap ada (memudar) sesudah kursor pergi,
    // supaya berpindah antar hari meluncur, bukan muncul-hilang.
    const [terakhir, setTerakhir] = useState(0);
    useEffect(() => {
        if (aktif >= 0) setTerakhir(aktif);
    }, [aktif]);
    const H = 250;
    const n = data.length;
    const { atas, garis } = skala(Math.max(...data.map((d) => Math.max(Number(d.total) || 0, Number(d.avg) || 0)), 1));
    const L = 38;
    const R = 12;
    const T = 14;
    const B = 28;
    const padX = 16;
    const sempit = lebar < 520;
    const x = (i) => (n === 1 ? (L + lebar - R) / 2 : L + padX + (i / (n - 1)) * (lebar - L - R - padX * 2));
    const y = (v) => T + (1 - v / atas) * (H - T - B);
    const utama = data.map((d, i) => [x(i), y(Number(d.total) || 0)]);
    const kedua = data.map((d, i) => [x(i), y(Number(d.avg) || 0)]);
    const garisUtama = n > 1 ? monoton(utama) : '';
    const garisKedua = n > 1 ? monoton(kedua) : '';

    const label = (d) => {
        const bagian = String(d.date || '').split(', ');
        return sempit ? bagian[bagian.length - 1] : bagian.length > 1 ? bagian[0].slice(0, 3) + ' ' + bagian[1] : d.date;
    };

    function dekat(e) {
        const r = ref.current.getBoundingClientRect();
        let best = 0;
        let bd = Infinity;
        for (let i = 0; i < n; i++) {
            const d = Math.abs(x(i) - (e.clientX - r.left));
            if (d < bd) {
                bd = d;
                best = i;
            }
        }
        setAktif(best);
    }

    const ringkas = data.map((d) => `${d.date}: ${fmt(d.total)} unit dari ${fmt(d.entries)} input`).join('. ');
    const tampil = aktif >= 0;
    const idx = Math.min(Math.max(tampil ? aktif : terakhir, 0), Math.max(n - 1, 0));
    const d = data[idx] || null;

    return (
        <div
            className="gl-chart"
            ref={ref}
            tabIndex={0}
            role="img"
            aria-label={`Grafik ${namaUtama} per hari. ${ringkas}`}
            onPointerMove={dekat}
            onPointerLeave={() => setAktif(-1)}
            onBlur={() => setAktif(-1)}
            onKeyDown={(e) => {
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    e.preventDefault();
                    setAktif((a) => Math.min(n - 1, Math.max(0, (a < 0 ? (e.key === 'ArrowRight' ? -1 : n) : a) + (e.key === 'ArrowRight' ? 1 : -1))));
                } else if (e.key === 'Escape') setAktif(-1);
            }}
        >
            {lebar > 20 ? (
                <svg viewBox={`0 0 ${lebar} ${H}`} width={lebar} height={H}>
                    <defs>
                        <linearGradient id="gl-area" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stopColor="var(--g-accent-soft)" stopOpacity="0.38" />
                            <stop offset="1" stopColor="var(--g-accent-soft)" stopOpacity="0" />
                        </linearGradient>
                    </defs>
                    {garis.map((v) => (
                        <g key={v}>
                            <line className="gl-chart-grid" x1={L} x2={lebar - R} y1={y(v)} y2={y(v)} />
                            <text className="gl-chart-teks" x={L - 10} y={y(v) + 3.5} textAnchor="end">{fmt(v)}</text>
                        </g>
                    ))}
                    {data.map((dd, i) => (
                        <text className="gl-chart-teks" key={i} x={x(i)} y={H - 7} textAnchor="middle">{label(dd)}</text>
                    ))}
                    {n > 1 ? <path className="gl-chart-area" d={`${garisUtama}L${x(n - 1)} ${y(0)}L${x(0)} ${y(0)}Z`} fill="url(#gl-area)" /> : null}
                    {n > 1 ? <path className="gl-chart-ln gl-chart-ln--kedua" d={garisKedua} pathLength="1" /> : null}
                    {n > 1 ? <path className="gl-chart-ln gl-chart-ln--utama" d={garisUtama} pathLength="1" /> : null}
                    {n === 1 ? <circle cx={x(0)} cy={y(Number(data[0].total) || 0)} r="4" fill="var(--g-accent-soft)" /> : null}
                    {d && n > 1 ? (
                        <g className={'gl-chart-sorot' + (tampil ? ' is-tampil' : '')}>
                            <line className="gl-chart-kursor" x1="0" x2="0" y1={T} y2={y(0)} style={{ transform: `translateX(${x(idx)}px)` }} />
                            <circle className="gl-chart-titik" cx="0" cy="0" r="5" fill="var(--g-accent-soft)" style={{ transform: `translate(${x(idx)}px, ${y(Number(d.total) || 0)}px)` }} />
                            <circle className="gl-chart-titik" cx="0" cy="0" r="4.5" fill="var(--g-ink)" style={{ transform: `translate(${x(idx)}px, ${y(Number(d.avg) || 0)}px)` }} />
                        </g>
                    ) : null}
                </svg>
            ) : null}
            {d && lebar > 20 ? (
                <div
                    className={'gl-chart-tip' + (tampil ? ' is-tampil' : '')}
                    style={{ transform: `translate3d(calc(${Math.min(Math.max(x(idx), 80), lebar - 80)}px - 50%), ${Math.max(0, y(Math.max(Number(d.total) || 0, Number(d.avg) || 0)) - 112)}px, 0)` }}
                    aria-hidden={!tampil}
                >
                    <b>{d.date}</b>
                    <div className="gl-chart-baris"><span><i style={{ background: 'var(--g-accent-soft)' }} />{namaUtama}</span><span>{fmt(d.total)}</span></div>
                    <div className="gl-chart-baris"><span><i style={{ background: '#fff' }} />{namaKedua}</span><span>{Number(d.avg) || 0}</span></div>
                    <div className="gl-chart-baris"><span>Jumlah input</span><span>{fmt(d.entries)}x</span></div>
                    {d.top ? <div className="gl-chart-baris"><span>Terbanyak</span><span>{d.top} ({fmt(d.top_qty)})</span></div> : null}
                </div>
            ) : null}
        </div>
    );
}

const WARNA_DONAT = ['var(--g-accent-soft)', '#5b7bff', '#f5b042', '#34b89c', '#c06be0', '#ef6b8a', '#7c8794'];

/** Donat tipis dengan irisan yang menyorot (dan mengganti isi tengah) saat disentuh atau difokus. */
export function DonatKaca({ data, satuan = 'unit', judulTengah = 'total unit' }) {
    const daftar = data.map((d) => ({ nama: d.name, nilai: Number(d.total) || 0 })).filter((d) => d.nilai > 0);
    const total = daftar.reduce((a, d) => a + d.nilai, 0);
    const [fokus, setFokus] = useState(-1);
    const [maju, setMaju] = useState(false);
    useEffect(() => {
        const id = setTimeout(() => setMaju(true), 100);
        return () => clearTimeout(id);
    }, []);
    const R = 46;
    const C = 2 * Math.PI * R;
    const celah = daftar.length > 1 ? 2.2 : 0;
    let acc = 0;
    const f = fokus >= 0 ? daftar[fokus] : null;

    return (
        <div className="gl-donat-isi">
            <div className="gl-donat" onPointerLeave={() => setFokus(-1)}>
                <svg viewBox="0 0 120 120" role="img" aria-label={`Komposisi ${judulTengah}: ` + (daftar.map((d) => `${d.nama} ${fmt(d.nilai)}`).join(', ') || 'belum ada data')}>
                    <circle className="gl-donat-trek" cx="60" cy="60" r={R} />
                    {daftar.map((d, i) => {
                        const len = (d.nilai / total) * C;
                        const dash = Math.max(len - celah, 0);
                        const off = -acc;
                        acc += len;
                        return (
                            <circle
                                key={d.nama + i}
                                className={'gl-donat-irisan' + (fokus === i ? ' is-on' : fokus >= 0 ? ' is-redup' : '')}
                                cx="60"
                                cy="60"
                                r={R}
                                stroke={WARNA_DONAT[i % WARNA_DONAT.length]}
                                strokeDasharray={maju ? `${dash} ${C - dash}` : `0 ${C}`}
                                strokeDashoffset={off}
                                transform="rotate(-90 60 60)"
                                tabIndex={0}
                                aria-label={`${d.nama} ${fmt(d.nilai)} ${satuan}`}
                                onPointerEnter={() => setFokus(i)}
                                onFocus={() => setFokus(i)}
                                onBlur={() => setFokus(-1)}
                            />
                        );
                    })}
                </svg>
                <div className="gl-donat-tengah">
                    <strong>{fmt(f ? f.nilai : total)}</strong>
                    <span>{f ? `${f.nama} · ${Math.round((f.nilai / total) * 100)}%` : total > 0 ? judulTengah : 'belum ada data'}</span>
                </div>
            </div>
            {daftar.length ? (
                <ul className="gl-donat-legenda">
                    {daftar.map((d, i) => (
                        <li
                            key={d.nama + i}
                            tabIndex={0}
                            className={fokus === i ? 'is-on' : ''}
                            onPointerEnter={() => setFokus(i)}
                            onPointerLeave={() => setFokus(-1)}
                            onFocus={() => setFokus(i)}
                            onBlur={() => setFokus(-1)}
                        >
                            <i style={{ background: WARNA_DONAT[i % WARNA_DONAT.length] }} />
                            <span>{d.nama}</span>
                            <b>{fmt(d.nilai)}</b>
                            <em>{Math.round((d.nilai / total) * 100)}%</em>
                            <u><s style={{ width: Math.round((d.nilai / total) * 100) + '%', background: WARNA_DONAT[i % WARNA_DONAT.length] }} /></u>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
