import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    DoughnutController,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import { Chart } from 'react-chartjs-2';
import AppLayout from '../Layouts/AppLayout';
import Calendar from '../Components/Calendar';
import { Icon, ICON as UI } from '../Components/Ui';

ChartJS.register(CategoryScale, LinearScale, BarElement, BarController, LineElement, LineController, PointElement, ArcElement, DoughnutController, Tooltip);

const TEXT = '#94a3b8';
const GRID = 'rgba(255,255,255,0.05)';
// Palet ungu monokrom (gelap → terang), sama dengan referensi.
const DONUT = ['#5b52e8', '#7c73f0', '#a5a0f7', '#4338ca', '#c7c3fb', '#8b83f3', '#6d63ec'];
const NOTE_HEX = { blue: '#3b82f6', green: '#22c55e', yellow: '#facc15', amber: '#f59e0b', red: '#ef4444', purple: '#a855f7', teal: '#14b8a6', slate: '#64748b' };

const P = {
    clipboard: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2',
    chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    cube: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    bolt: 'M13 10V3L4 14h7v7l9-11h-7z',
    plus: 'M12 4v16m8-8H4',
    print: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
    check: 'M5 13l4 4L19 7',
    star: 'M11.05 2.93c.3-.92 1.6-.92 1.9 0l1.52 4.67a1 1 0 00.95.69h4.91c.97 0 1.37 1.24.59 1.81l-3.97 2.89a1 1 0 00-.36 1.12l1.52 4.67c.3.92-.76 1.69-1.54 1.12l-3.97-2.89a1 1 0 00-1.18 0l-3.97 2.89c-.78.57-1.84-.2-1.54-1.12l1.52-4.67a1 1 0 00-.36-1.12L2.98 10.1c-.78-.57-.38-1.81.59-1.81h4.91a1 1 0 00.95-.69l1.52-4.67z',
    pena: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    chev: 'M19 9l-7 7-7-7',
    keluar: 'M15 3h6v6M14 10l7-7M21 14v5a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h5',
    kalender: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    papan: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
};

const fmt = (n) => Math.round(Number(n) || 0).toLocaleString('id-ID');

// Label angka di atas tiap bar, supaya nilai terbaca tanpa hover.
const labelBar = {
    id: 'labelBar',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        chart.data.datasets.forEach((ds, i) => {
            if (ds.type !== 'bar') return;
            chart.getDatasetMeta(i).data.forEach((bar, j) => {
                const v = ds.data[j];
                if (!v) return;
                ctx.save();
                ctx.fillStyle = TEXT;
                ctx.font = '600 10px Sora, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillText(Number(v).toLocaleString('id-ID'), bar.x, bar.y - 2);
                ctx.restore();
            });
        });
    },
};

export default function Dashboard(props) {
    const { monthLabel, chartData, productChart, recentLogs, notes, calendar, can, urls } = props;

    const [stats, setStats] = useState(props.stats);
    const [target, setTarget] = useState(props.target);
    const [reject, setReject] = useState(props.reject);
    const [topToday, setTopToday] = useState(props.topOperators);
    const [updatedAt, setUpdatedAt] = useState(null);

    const fetchLive = useCallback(async () => {
        try {
            const r = await fetch(urls.live, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            setStats((s) => ({ ...s, today_total: d.today_total, today_entries: d.today_entries, monthly_total: d.monthly_total }));
            setReject({ today: d.today_reject, pct: d.reject_pct });
            if (d.target_pct !== null) setTarget((t) => ({ ...t, total: d.total_target, actual: d.total_actual, pct: d.target_pct }));
            if (Array.isArray(d.top_operators)) setTopToday(d.top_operators);
            setUpdatedAt(d.updated_at);
        } catch (_) {
            /* polling gagal tidak boleh merusak halaman */
        }
    }, [urls.live]);

    return (
        <AppLayout title="Dashboard" subtitle="Ringkasan produksi hari ini">
            <div className="au-db">
                {/* ── Kartu statistik ── */}
                <div className={'au-db-stats' + (can.quick ? '' : ' is-3')}>
                    <div className="au-card au-db-stat">
                        <div className="au-db-stat-atas">
                            <span className="au-db-stat-ikon is-biru"><Icon path={P.clipboard} /></span>
                            <span className="au-db-live"><i />Live</span>
                        </div>
                        <p className="au-db-stat-nilai">{fmt(stats.today_total)}</p>
                        <p className="au-db-stat-label">Total Unit Hari Ini</p>
                        <p className="au-db-stat-kaki">{stats.today_entries} entri</p>
                    </div>
                    <div className="au-card au-db-stat">
                        <div className="au-db-stat-atas">
                            <span className="au-db-stat-ikon is-ungu"><Icon path={P.chart} /></span>
                        </div>
                        <p className="au-db-stat-nilai">{fmt(stats.monthly_total)}</p>
                        <p className="au-db-stat-label">Total Bulan Ini</p>
                        <p className="au-db-stat-kaki">{monthLabel}</p>
                    </div>
                    <div className="au-card au-db-stat">
                        <div className="au-db-stat-atas">
                            <span className="au-db-stat-ikon is-amber"><Icon path={P.cube} /></span>
                        </div>
                        <p className="au-db-stat-nilai">{stats.total_products}</p>
                        <p className="au-db-stat-label">Produk Aktif</p>
                        <p className="au-db-stat-kaki">Terdaftar di sistem</p>
                    </div>
                    {can.quick ? (
                        <div className="au-db-quick">
                            <div className="au-db-quick-atas">
                                <span><Icon path={P.bolt} /></span>
                                <b>Aksi Cepat</b>
                            </div>
                            <div className="au-db-quick-list">
                                {can.input ? (
                                    <a href={urls.create}>
                                        <Icon path={P.plus} />
                                        Input Produksi
                                    </a>
                                ) : null}
                                {can.privileged ? (
                                    <a href={urls.daily}>
                                        <Icon path={P.print} />
                                        Laporan Harian
                                    </a>
                                ) : null}
                            </div>
                        </div>
                    ) : null}
                </div>

                {/* ── Target Aktif | Reject Hari Ini ── */}
                <div className="au-db-g2">
                    <KartuTarget target={target} can={can} urls={urls} />
                    <KartuReject reject={reject} />
                </div>

                {/* ── Grafik ── */}
                <div className="au-db-g3">
                    <KartuTrend data={chartData} />
                    <KartuDonut data={productChart} monthLabel={monthLabel} />
                </div>

                {/* ── Top Operator ── */}
                <div className="au-db-g2 is-rapat">
                    <TopHariIni rows={topToday} />
                    <TopBulanan rows={props.topOperatorsMonthly} monthLabel={monthLabel} />
                </div>

                {/* ── Catatan | Kalender ── */}
                <div className="au-db-g2">
                    <KartuCatatan notes={notes} can={can} urls={urls} />
                    <div id="dashboard-calendar">
                        <Calendar initial={calendar} calendarUrl={urls.calendar} storeUrl={urls.eventStore} eventBase={urls.eventBase} />
                    </div>
                </div>

                <InputTerbaru data={recentLogs} can={can} urls={urls} />

                {props.activities ? <LogAktivitas awal={props.activities} url={urls.aktivitas} /> : null}

                <TombolLive onTick={fetchLive} updatedAt={updatedAt} />
            </div>
        </AppLayout>
    );
}

/* ─────────────────────────── kartu-kartu ─────────────────────────── */

function KepalaKartu({ judul, sub, aksi }) {
    return (
        <div className="au-db-kepala">
            <div style={{ minWidth: 0 }}>
                <h3>{judul}</h3>
                {sub ? <p>{sub}</p> : null}
            </div>
            {aksi}
        </div>
    );
}

function KartuTarget({ target, can, urls }) {
    const pct = target.pct;
    const warna = (p, done) => (done || p >= 100 ? 'is-hijau' : p >= 70 ? 'is-amber' : 'is-biru');
    return (
        <div className="au-card au-db-kartu is-rapat">
            <KepalaKartu
                judul="Target Aktif"
                sub="Progres sejak target dibuat"
                aksi={can.privileged ? <a className="au-db-link" href={urls.targets}>Kelola →</a> : null}
            />
            {target.total > 0 ? (
                <>
                    <div className="au-db-target-ringkas">
                        <p>
                            <b>{fmt(target.actual)}</b>
                            <span>/ {fmt(target.total)}</span>
                        </p>
                        <b className={'au-db-pct ' + warna(pct)}>{pct}%</b>
                    </div>
                    <div className="au-db-bar">
                        <div className={warna(pct)} style={{ width: pct + '%' }} />
                    </div>
                    <div className="au-db-target-list">
                        {target.products.map((t, i) => {
                            const p = t.target > 0 ? Math.min(Math.round((t.actual / t.target) * 100), 100) : 0;
                            return (
                                <div className="au-db-target-item" key={t.name + i}>
                                    <div className="au-db-target-baris">
                                        <div style={{ minWidth: 0, flex: 1 }}>
                                            <span className="au-db-target-nama">{t.name}</span>
                                            {t.series_kva ? <span className="au-db-target-seri">{t.series_kva}</span> : null}
                                        </div>
                                        <span className={'au-db-target-angka' + (t.done ? ' is-done' : '')}>
                                            {fmt(t.actual)}
                                            <small>/{fmt(t.target)}</small>
                                        </span>
                                    </div>
                                    <div className="au-db-bar is-tipis">
                                        <div className={warna(p, t.done)} style={{ width: p + '%' }} />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </>
            ) : (
                <div className="au-db-kosong-kecil">
                    <p>Belum ada target aktif</p>
                    {can.privileged ? <a href={urls.targets}>Set target sekarang →</a> : null}
                </div>
            )}
        </div>
    );
}

function KartuReject({ reject }) {
    const ada = reject.today > 0;
    return (
        <div className="au-card au-db-kartu is-rapat">
            <KepalaKartu
                judul="Reject Hari Ini"
                sub="Defect / produk reject"
                aksi={ada ? <span className="au-db-chip is-merah">⚠ High Alert</span> : <span className="au-db-chip is-hijau">✓ All Good</span>}
            />
            {ada ? (
                <>
                    <div className="au-db-reject-kotak">
                        <div>
                            <b>{fmt(reject.today)}</b>
                            <p>unit reject</p>
                        </div>
                        <div>
                            <b>{reject.pct}%</b>
                            <p>reject rate</p>
                        </div>
                    </div>
                    <div style={{ marginTop: 'auto' }}>
                        <div className="au-db-reject-label">
                            <span>Defect rate</span>
                            <span>{reject.pct}%</span>
                        </div>
                        <div className="au-db-bar">
                            <div className="is-merah" style={{ width: Math.min(reject.pct, 100) + '%' }} />
                        </div>
                    </div>
                </>
            ) : (
                <div className="au-db-reject-ok">
                    <span><Icon path={P.check} /></span>
                    <b>Tidak ada reject</b>
                    <p>Produksi berjalan lancar hari ini</p>
                </div>
            )}
        </div>
    );
}

function KartuTrend({ data }) {
    const hariIni = data[data.length - 1];
    const chart = {
        labels: data.map((d) => d.date),
        datasets: [
            {
                type: 'bar',
                label: 'Total Unit',
                data: data.map((d) => d.total),
                backgroundColor: 'rgba(59,130,246,0.85)',
                hoverBackgroundColor: 'rgba(59,130,246,1)',
                borderRadius: 6,
                borderSkipped: false,
                order: 2,
            },
            {
                type: 'line',
                label: 'Rata-rata / input',
                data: data.map((d) => d.avg),
                borderColor: '#facc15',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [4, 3],
                pointBackgroundColor: '#facc15',
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.4,
                order: 1,
            },
        ],
    };
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 18 } },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: (items) => items[0].label,
                    label: (ctx) => {
                        if (ctx.dataset.label === 'Total Unit') {
                            const r = data[ctx.dataIndex];
                            const rows = [`  Total: ${ctx.parsed.y.toLocaleString('id-ID')} unit`, `  Jumlah input: ${r.entries}x`];
                            if (r.top) rows.push(`  Terbanyak: ${r.top} (${(r.top_qty || 0).toLocaleString('id-ID')} unit)`);
                            return rows;
                        }
                        return `  Rata-rata: ${ctx.parsed.y} unit / input`;
                    },
                },
            },
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: TEXT, font: { size: 10 } } },
            y: { beginAtZero: true, grid: { color: GRID }, ticks: { color: TEXT, font: { size: 10 }, precision: 0, callback: (v) => v.toLocaleString('id-ID') } },
        },
    };

    return (
        <div className="au-card au-db-kartu au-db-span2">
            <KepalaKartu
                judul="Trend Produksi"
                sub="Total unit yang diproduksi · 7 hari terakhir"
                aksi={
                    <div className="au-db-legend-mini">
                        <span><i className="is-bar" />Total Unit</span>
                        <span><i className="is-garis" />Rata-rata / input</span>
                    </div>
                }
            />
            <div className="au-db-trend">
                <Chart type="bar" data={chart} options={options} plugins={[labelBar]} />
            </div>
            {hariIni && hariIni.top ? (
                <div className="au-db-teratas">
                    <span className="au-db-teratas-ikon"><Icon path={P.star} /></span>
                    <div style={{ minWidth: 0, flex: 1 }}>
                        <small>Seri terbanyak hari ini</small>
                        <p>{hariIni.top}</p>
                    </div>
                    <b>{fmt(hariIni.top_qty)} unit</b>
                </div>
            ) : null}
            <p className="au-db-catatan-kaki">
                <b>Total Unit</b> = jumlah unit selesai per hari. Arahkan kursor ke tiap bar untuk lihat jumlah input &amp; seri yang paling banyak masuk hari itu.
            </p>
        </div>
    );
}

function KartuDonut({ data, monthLabel }) {
    const label = data.map((d) => d.name);
    const nilai = data.map((d) => d.total);
    const total = nilai.reduce((a, b) => a + b, 0);

    const plugins = useMemo(() => {
        const lighten = (hex, amt) => {
            const n = parseInt(hex.slice(1), 16);
            const mix = (c) => Math.round(c + (255 - c) * amt);
            return `rgb(${mix((n >> 16) & 255)},${mix((n >> 8) & 255)},${mix(n & 255)})`;
        };
        return {
            gradasi: (chart, i) => {
                // Chart.js juga memanggil warna skriptabel di tingkat dataset
                // (tanpa dataIndex) — tanpa pengaman ini halaman ikut mogok.
                const base = DONUT[(Number.isInteger(i) ? i : 0) % DONUT.length];
                const area = chart.chartArea;
                if (!area) return base;
                const g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                g.addColorStop(0, lighten(base, 0.28));
                g.addColorStop(1, base);
                return g;
            },
            list: [
                {
                    id: 'bayangan',
                    beforeDatasetsDraw(chart) {
                        const c = chart.ctx;
                        c.save();
                        c.shadowColor = 'rgba(0,0,0,0.45)';
                        c.shadowBlur = 14;
                        c.shadowOffsetY = 5;
                    },
                    afterDatasetsDraw(chart) {
                        chart.ctx.restore();
                    },
                },
                {
                    id: 'tengah',
                    afterDraw(chart) {
                        const { ctx, chartArea: a } = chart;
                        const cx = (a.left + a.right) / 2;
                        const cy = (a.top + a.bottom) / 2;
                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.font = '700 24px Sora, sans-serif';
                        ctx.fillStyle = '#f1f5f9';
                        ctx.fillText(total.toLocaleString('id-ID'), cx, cy - 2);
                        ctx.font = '500 10px Sora, sans-serif';
                        ctx.fillStyle = 'rgba(241,245,249,0.55)';
                        ctx.fillText('total unit', cx, cy + 18);
                        ctx.restore();
                    },
                },
                {
                    id: 'labelLuar',
                    afterDraw(chart) {
                        if (!total) return;
                        const { ctx } = chart;
                        ctx.save();
                        ctx.textAlign = 'center';
                        chart.getDatasetMeta(0).data.forEach((arc, i) => {
                            const v = nilai[i];
                            if (!v) return;
                            const pct = (v / total) * 100;
                            const ang = (arc.startAngle + arc.endAngle) / 2;
                            const r = arc.outerRadius + 28;
                            const x = arc.x + Math.cos(ang) * r;
                            const y = arc.y + Math.sin(ang) * r;
                            ctx.textBaseline = 'bottom';
                            ctx.font = '700 12px Sora, sans-serif';
                            ctx.fillStyle = '#e2e8f0';
                            ctx.fillText((pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1)) + '%', x, y);
                            ctx.textBaseline = 'top';
                            ctx.font = '600 9px Sora, sans-serif';
                            ctx.fillStyle = TEXT;
                            ctx.fillText(String(label[i] ?? '').toUpperCase(), x, y + 1);
                        });
                        ctx.restore();
                    },
                },
            ],
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [total]);

    const chart = {
        labels: label,
        datasets: [{ data: nilai, backgroundColor: (ctx) => plugins.gradasi(ctx.chart, ctx.dataIndex), borderWidth: 0, spacing: 3, borderRadius: 30, hoverOffset: 10 }],
    };
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        layout: { padding: 62 },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => `  ${ctx.parsed.toLocaleString('id-ID')} unit (${total > 0 ? Math.round((ctx.parsed / total) * 100) : 0}%)`,
                },
            },
        },
    };

    return (
        <div className="au-card au-db-kartu">
            <KepalaKartu judul="Produk per Tipe" sub={monthLabel} />
            <div className="au-db-donut">
                <Chart type="doughnut" data={chart} options={options} plugins={plugins.list} />
            </div>
            <div className="au-db-donut-legend">
                {data.map((d, i) => {
                    const p = total > 0 ? Math.round((d.total / total) * 100) : 0;
                    const w = DONUT[i % DONUT.length];
                    return (
                        <div key={d.name}>
                            <i style={{ background: w }} />
                            <span className="au-db-donut-nama">{d.name}</span>
                            <b>
                                {d.total.toLocaleString('id-ID')} <small>Unit</small>
                            </b>
                            <span className="au-db-donut-bar">
                                <span style={{ width: p + '%', background: w }} />
                            </span>
                            <span className="au-db-donut-pct">{p}%</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function TopHariIni({ rows }) {
    const max = Math.max(...rows.map((r) => r.total), 1);
    return (
        <div className="au-card au-db-kartu is-rapat">
            <KepalaKartu judul="Top Operator" sub="Hari ini · unit terproduksi" />
            <div className="au-db-op-list">
                {rows.length ? (
                    rows.map((o, i) => (
                        <div className="au-db-op" key={o.name + i}>
                            <span className={'au-db-op-rank is-' + Math.min(i + 1, 3)}>{i + 1}</span>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div className="au-db-op-baris">
                                    <span>{o.name}</span>
                                    <b>
                                        {o.total.toLocaleString('id-ID')} <small>unit</small>
                                    </b>
                                </div>
                                <div className="au-db-bar is-tipis">
                                    <div className="is-biru" style={{ width: Math.round((o.total / max) * 100) + '%' }} />
                                </div>
                            </div>
                        </div>
                    ))
                ) : (
                    <p className="au-db-kosong-teks">Belum ada data hari ini</p>
                )}
            </div>
        </div>
    );
}

function TopBulanan({ rows, monthLabel }) {
    const max = Math.max(...rows.map((r) => r.total), 1);
    return (
        <div className="au-card au-db-kartu is-rapat">
            <KepalaKartu judul="Top Operator" sub={monthLabel + ' · unit terproduksi'} />
            <div className="au-db-op-list">
                {rows.length ? (
                    rows.map((o, i) => (
                        <div className="au-db-op" key={o.name + i}>
                            <span className={'au-db-op-angka is-' + Math.min(i + 1, 4)}>{i + 1}</span>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div className="au-db-op-baris">
                                    <span>
                                        {o.name}
                                        {i === 0 ? ' 👑' : ''}
                                    </span>
                                    <b className="is-biru">{o.total.toLocaleString('id-ID')} unit</b>
                                </div>
                                <div className="au-db-bar is-tipis">
                                    <div className={i === 0 ? 'is-amber' : 'is-biru'} style={{ width: Math.round((o.total / max) * 100) + '%' }} />
                                </div>
                            </div>
                        </div>
                    ))
                ) : (
                    <p className="au-db-kosong-teks">Belum ada data produksi bulan ini</p>
                )}
            </div>
        </div>
    );
}

/* Catatan: baris ringkas yang bisa dibentang (animasi max-height) + modal. */
function KartuCatatan({ notes, can, urls }) {
    const [detail, setDetail] = useState(null);
    return (
        <div className="au-card au-db-catatan">
            <div className="au-db-catatan-kepala">
                <div className="au-db-catatan-judul">
                    <span><Icon path={P.pena} /></span>
                    <div>
                        <h3>Catatan</h3>
                        <p>{notes.length} catatan terbaru</p>
                    </div>
                </div>
                {can.privileged ? (
                    <a className="au-db-catatan-tambah" href={urls.notes}>
                        <Icon path={P.plus} />
                        Tambah
                    </a>
                ) : null}
            </div>

            {/* Tetap bisa digulir kalau kepanjangan — yang disembunyikan cuma batang gulirnya. */}
            <div className="au-db-catatan-list no-scrollbar">
                {notes.length ? (
                    notes.map((n) => <BarisCatatan key={n.id} n={n} onDetail={() => setDetail(n)} />)
                ) : (
                    <div className="au-db-catatan-kosong">
                        <Icon path={P.pena} />
                        <p>Belum ada catatan</p>
                    </div>
                )}
            </div>

            <div className="au-db-catatan-kaki">
                <a href={urls.notes}>Lihat semua catatan →</a>
            </div>

            {detail ? <ModalCatatan n={detail} url={urls.notes} onClose={() => setDetail(null)} /> : null}
        </div>
    );
}

function BarisCatatan({ n, onDetail }) {
    const [buka, setBuka] = useState(false);
    const body = useRef(null);
    const pertama = useRef(true);

    // Tinggi dianimasikan dari 0 ke setinggi isinya; setelah terbuka batasnya
    // dilepas supaya isi tetap utuh bila teks berpindah baris.
    useLayoutEffect(() => {
        const el = body.current;
        if (!el) return;
        if (pertama.current) {
            pertama.current = false;
            return;
        }
        if (buka) {
            el.style.opacity = '1';
            el.style.maxHeight = el.scrollHeight + 'px';
        } else {
            el.style.maxHeight = el.scrollHeight + 'px';
            void el.offsetHeight;
            el.style.opacity = '0';
            el.style.maxHeight = '0px';
        }
    }, [buka]);

    return (
        <div className="au-db-note">
            <button type="button" className="au-db-note-head" aria-expanded={buka ? 'true' : 'false'} onClick={() => setBuka((b) => !b)}>
                <span className="au-db-note-strip" style={{ background: NOTE_HEX[n.color] || NOTE_HEX.slate }} />
                <div style={{ flex: 1, minWidth: 0 }}>
                    <p className={'au-db-note-judul' + (n.done ? ' is-done' : '')}>{n.title}</p>
                    <div className="au-db-note-meta">
                        {n.snippet ? <span className="au-db-note-cuplik">{n.snippet}</span> : null}
                        {n.dueShort ? <span className={n.late ? 'is-telat' : ''}>· {n.dueShort}</span> : null}
                        {n.to ? <span>· → {n.to}</span> : null}
                    </div>
                </div>
                {n.done ? <span className="au-db-note-selesai">✓ Selesai</span> : <span className="au-db-note-titik" />}
                <svg className="au-db-note-chev" style={{ transform: buka ? 'rotate(180deg)' : '' }} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d={P.chev} />
                </svg>
            </button>
            <div
                ref={body}
                className="au-db-note-body"
                style={{ maxHeight: 0, overflow: 'hidden', transition: 'max-height .28s ease, opacity .2s ease', opacity: 0 }}
                onTransitionEnd={(e) => {
                    if (e.propertyName === 'max-height' && buka) e.currentTarget.style.maxHeight = 'none';
                }}
            >
                <div className="au-db-note-dalam">
                    <div className="au-db-note-kotak">
                        {n.html ? (
                            // Sudah disaring di server (App\Support\HtmlCatatan).
                            <div className="qc-isi-catatan au-db-note-isi" dangerouslySetInnerHTML={{ __html: n.html }} />
                        ) : (
                            <p className="au-db-note-kosong">Catatan ini tidak punya isi.</p>
                        )}
                        <div className="au-db-note-info">
                            <span>Dibuat {n.made}</span>
                            {n.by ? <span>· oleh {n.by}</span> : null}
                            {n.photo ? <span>· 📎 ada foto</span> : null}
                        </div>
                    </div>
                    <button type="button" className="au-db-note-detail" onClick={onDetail}>
                        <Icon path={P.keluar} />
                        Lihat detail
                    </button>
                </div>
            </div>
        </div>
    );
}

function ModalCatatan({ n, url, onClose }) {
    useEffect(() => {
        const esc = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [onClose]);

    const meta = [];
    if (n.done) meta.push('✓ Selesai');
    if (n.due) meta.push((n.late ? '⚠ Tenggat ' : 'Tenggat ') + n.due);
    if (n.to) meta.push('→ ' + n.to);

    return (
        <div className="au-modal-backdrop" onClick={onClose}>
            <div className="au-db-modal" onClick={(e) => e.stopPropagation()}>
                <div className="au-db-modal-kepala is-amber">
                    <div style={{ minWidth: 0 }}>
                        <h3 style={{ textDecoration: n.done ? 'line-through' : '' }}>{n.title || 'Catatan'}</h3>
                        <p>{meta.join(' · ')}</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup">
                        <Icon path={UI.close} />
                    </button>
                </div>
                <div className="au-db-modal-isi">
                    {n.html ? (
                        <div className="qc-isi-catatan au-db-modal-teks" dangerouslySetInnerHTML={{ __html: n.html }} />
                    ) : (
                        <p className="au-db-modal-teks" style={{ fontStyle: 'italic' }}>
                            Catatan ini tidak punya isi.
                        </p>
                    )}
                    {n.photo ? <img className="au-db-modal-foto" src={n.photo} alt="Foto catatan" /> : null}
                    <p className="au-db-modal-kaki">Dibuat {n.madeFull + (n.by ? ' oleh ' + n.by : '')}</p>
                </div>
                <div className="au-db-modal-aksi">
                    <a href={url}>Buka menu catatan</a>
                </div>
            </div>
        </div>
    );
}

function InputTerbaru({ data, can, urls }) {
    return (
        <div className="au-card au-db-input">
            <div className="au-db-input-kepala">
                <h3>Input Produksi Terbaru</h3>
                <a href={urls.production}>Lihat semua →</a>
            </div>
            <div className="au-db-input-isi">
                {data ? (
                    <>
                        <div className="au-db-hari">
                            <span className="au-db-hari-pil">
                                <Icon path={P.kalender} />
                                <small>{data.dayName}</small>
                                <b>{data.date}</b>
                            </span>
                            <span className="au-db-hari-garis" />
                            <span className="au-db-hari-total">
                                {fmt(data.total)} unit · {data.count} entri
                            </span>
                        </div>
                        <div className="au-db-kat-grid">
                            {data.groups.map((g) => (
                                <div className="au-db-kat" key={g.name}>
                                    <div className="au-db-kat-kepala">
                                        <div>
                                            <i className={'is-' + g.tone} />
                                            <b>{g.name}</b>
                                            <small>· {g.count} entri</small>
                                        </div>
                                        <span className={'au-db-kat-total is-' + g.tone}>
                                            {fmt(g.total)} <small>unit</small>
                                        </span>
                                    </div>
                                    <div className="au-db-kat-list">
                                        {g.logs.map((l) => (
                                            <div className="au-db-entri" key={l.id}>
                                                <div style={{ minWidth: 0, flex: 1 }}>
                                                    <div className="au-db-entri-atas">
                                                        <b>{l.name}</b>
                                                        {l.seriesKva ? <span className="au-mono">{l.seriesKva}</span> : null}
                                                        {l.badge ? <span className={'au-db-entri-badge is-' + l.badge}>{l.badge === 'swasta' ? 'Swasta' : 'Typetest'}</span> : null}
                                                    </div>
                                                    <div className="au-db-entri-bawah">
                                                        {l.notes ? (
                                                            <>
                                                                <i>{l.notes}</i>
                                                                <span>·</span>
                                                            </>
                                                        ) : null}
                                                        <span>{l.user}</span>
                                                    </div>
                                                </div>
                                                <div className="au-db-entri-kanan">
                                                    {l.channel ? (
                                                        <div className="au-db-entri-upbt">
                                                            <span>UP</span>
                                                            <b className="is-up">{l.up.toLocaleString('en-US')}</b>
                                                            <span>|</span>
                                                            <span>BT</span>
                                                            <b className="is-bt">{l.bt.toLocaleString('en-US')}</b>
                                                        </div>
                                                    ) : null}
                                                    <span className="au-db-entri-total">
                                                        <small>Total:</small>
                                                        {fmt(l.total)}
                                                        <small>Unit</small>
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                ) : (
                    <div className="au-db-input-kosong">
                        <span><Icon path={P.papan} /></span>
                        <p>Belum ada data produksi</p>
                        {can.input ? <a href={urls.create}>Input sekarang →</a> : null}
                    </div>
                )}
            </div>
        </div>
    );
}

const GAYA_LOG = {
    create: ['TAMBAH', 'is-tambah'],
    update: ['UBAH', 'is-ubah'],
    delete: ['HAPUS', 'is-hapus'],
    login: ['MASUK', 'is-masuk'],
    logout: ['KELUAR', 'is-keluar'],
};
const SARING = [
    ['', 'Semua'],
    ['create', 'Tambah'],
    ['update', 'Ubah'],
    ['delete', 'Hapus'],
    ['login', 'Masuk'],
];
const TERLIHAT = 12;

/* Log Aktivitas bergaya konsol: 12 baris terlihat, sisanya digulir. */
function LogAktivitas({ awal, url }) {
    const [data, setData] = useState(awal);
    const [saring, setSaring] = useState('');
    const [baru, setBaru] = useState(0); // id di atas ini disorot sebentar
    const [denyut, setDenyut] = useState(false);
    const gulir = useRef(null);
    const terbaru = useRef(awal.terbaru);

    const terlihat = useMemo(
        () => data.items.filter((l) => !saring || l.action === saring || (saring === 'login' && l.action === 'logout')),
        [data, saring],
    );

    // Tinggi dibatasi di awal baris ke-13 yang TERLIHAT, bukan angka tetap:
    // tinggi baris berbeda-beda dan pemisah tanggal ikut makan tempat.
    const batasi = useCallback(() => {
        const el = gulir.current;
        if (!el) return;
        el.style.maxHeight = '';
        const baris = el.querySelectorAll('[data-log]');
        if (baris.length > TERLIHAT) el.style.maxHeight = baris[TERLIHAT].offsetTop + 'px';
    }, []);

    useLayoutEffect(batasi, [terlihat, batasi]);
    useEffect(() => {
        window.addEventListener('resize', batasi);
        return () => window.removeEventListener('resize', batasi);
    }, [batasi]);

    useEffect(() => {
        const t = setInterval(async () => {
            if (document.hidden) return;
            try {
                const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();
                if (d.terbaru === terbaru.current) return;
                setBaru(terbaru.current);
                terbaru.current = d.terbaru;
                setData(d);
                setDenyut(true);
                setTimeout(() => setDenyut(false), 2000);
                setTimeout(() => setBaru(0), 2500);
            } catch (_) {
                /* diamkan */
            }
        }, 20000);
        return () => clearInterval(t);
    }, [url]);

    let hariSebelumnya = null;

    return (
        <div id="log-aktivitas" className="au-db-log">
            <div className="au-db-log-kepala">
                <div className="au-db-log-judul">
                    <span className="au-db-log-lampu">
                        <i className={denyut ? 'is-denyut' : ''} />
                        <i />
                    </span>
                    <h3>Log Aktivitas</h3>
                    <small>{terlihat.length ? terlihat.length + ' entri' : ''}</small>
                </div>
                <div className="au-db-log-saring no-scrollbar">
                    {SARING.map(([aksi, nama]) => (
                        <button
                            type="button"
                            key={aksi || 'semua'}
                            className={saring === aksi ? 'is-aktif' : ''}
                            onClick={() => {
                                setSaring(aksi);
                                if (gulir.current) gulir.current.scrollTop = 0;
                            }}
                        >
                            {nama}
                        </button>
                    ))}
                </div>
            </div>
            <div ref={gulir} className="au-db-log-gulir no-scrollbar">
                <ol className="au-db-log-daftar">
                    {terlihat.length ? (
                        terlihat.map((l) => {
                            const [label, kelas] = GAYA_LOG[l.action] || [String(l.action).toUpperCase(), 'is-lain'];
                            const pemisah = l.date !== hariSebelumnya;
                            hariSebelumnya = l.date;
                            return [
                                // Sengaja TIDAK sticky: pemisah yang menempel saling menumpuk dan menimpa baris log.
                                pemisah ? (
                                    <li className="au-db-log-hari" key={'h' + l.date}>
                                        {l.day}
                                        <span />
                                    </li>
                                ) : null,
                                <li data-log className={'au-db-log-baris' + (baru && l.id > baru ? ' is-baru' : '')} key={l.id}>
                                    <time dateTime={l.iso}>{l.time}</time>
                                    <span className={'au-db-log-aksi ' + kelas}>{label}</span>
                                    <p>
                                        <b>{l.user}</b>
                                        {l.role ? <span className="au-db-log-peran">({l.role})</span> : null}
                                        <span className="au-db-log-panah">›</span>
                                        <span className="au-db-log-isi">{l.desc}</span>
                                    </p>
                                </li>,
                            ];
                        })
                    ) : (
                        <li className="au-db-log-kosong">Belum ada aktivitas tercatat.</li>
                    )}
                </ol>
            </div>
        </div>
    );
}

const AR_INTERVAL = 60;
function bacaAutoRefresh() {
    try {
        return localStorage.getItem('dashAutoRefresh') !== 'false';
    } catch (_) {
        return true;
    }
}

/* Tombol Live/Paused + hitung mundur — statistik disegarkan tiap 60 detik. */
function TombolLive({ onTick, updatedAt }) {
    const [on, setOn] = useState(bacaAutoRefresh);
    const [cd, setCd] = useState(AR_INTERVAL);

    useEffect(() => {
        if (!on) return undefined;
        onTick();
        let c = AR_INTERVAL;
        setCd(c);
        const t = setInterval(() => {
            c -= 1;
            if (c <= 0) {
                c = AR_INTERVAL;
                if (!document.hidden) onTick();
            }
            setCd(c);
        }, 1000);
        return () => clearInterval(t);
    }, [on, onTick]);

    function ubah() {
        const v = !on;
        setOn(v);
        try {
            localStorage.setItem('dashAutoRefresh', String(v));
        } catch (_) {
            /* abaikan */
        }
    }

    return (
        <div className="au-db-live-wadah">
            <button type="button" className="au-db-live-tombol" onClick={ubah}>
                <i className={on ? 'is-on' : ''} />
                <b>{on ? 'Live' : 'Paused'}</b>
                {on ? <span>{cd}s</span> : null}
                {updatedAt ? <small> · {updatedAt}</small> : null}
            </button>
        </div>
    );
}
