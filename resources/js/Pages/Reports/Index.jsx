import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON, Icon, Select } from '../../Components/Ui';

// number_format() PHP: ribuan koma, desimal titik; pecahan 1 desimal.
const nf = new Intl.NumberFormat('en-US');
const nf1 = new Intl.NumberFormat('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const num = (v) => {
    const n = Number(v) || 0;
    return n % 1 === 0 ? nf.format(n) : nf1.format(n);
};

const CAL = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';
const DOC = 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
const CAT_ICON = {
    Channel: { path: 'M13 10V3L4 14h7v7l9-11h-7z', mark: 'au-prodcard-mark--blue', total: 'au-prodcard-total--blue' },
    Cover: { path: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', mark: 'au-prodcard-mark--green', total: 'au-prodcard-total--green' },
    Tangki: { path: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4', mark: 'au-prodcard-mark--amber', total: 'au-prodcard-total--amber' },
};
const LAIN = { path: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2', mark: '', total: '' };
const BADGE = { Swasta: 'au-prodtag--red', Typetest: 'au-prodtag--blue', PLN: 'au-prodtag--muted' };

/**
 * Laporan Produksi bulanan — mengikuti Production-QC-Logging-System: bilah
 * filter (Bulan/Tahun + Harian/Excel/PDF), ringkasan tersegmen, lalu kartu per
 * kategori dengan urutan yang sama seperti Riwayat Produksi, PDF, dan Excel.
 */
export default function ReportsIndex({
    month,
    year,
    monthLabel,
    months,
    years,
    departments,
    deptFilter,
    canExport,
    indexUrl,
    dailyUrl,
    excelUrl,
    pdfUrl,
    jumlahProduk,
    summary,
    categories,
}) {
    const [f, setF] = useState({ month: String(month), year: String(year), department: deptFilter || '' });

    function tampilkan(e) {
        e.preventDefault();
        router.get(indexUrl, Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveScroll: true });
    }

    return (
        <AppLayout title="Laporan Produksi" subtitle="Rekap dan export data produksi bulanan">
            <div className="au-panel">
                <form className="au-filter" onSubmit={tampilkan}>
                    <div className="au-filter-field">
                        <label className="au-filter-label">Bulan</label>
                        <Select value={f.month} options={months} onChange={(e) => setF({ ...f, month: e.target.value })} />
                    </div>
                    <div className="au-filter-field">
                        <label className="au-filter-label">Tahun</label>
                        <Select value={f.year} options={years} onChange={(e) => setF({ ...f, year: e.target.value })} />
                    </div>
                    {departments.length ? (
                        <div className="au-filter-field">
                            <label className="au-filter-label">Departemen</label>
                            <Select
                                value={f.department}
                                placeholder="Semua Departemen"
                                options={departments}
                                onChange={(e) => setF({ ...f, department: e.target.value })}
                            />
                        </div>
                    ) : null}
                    <div className="au-filter-actions">
                        <Btn type="submit">Tampilkan</Btn>
                        <Btn as="a" href={dailyUrl} tone="ghost" icon={CAL}>
                            Harian
                        </Btn>
                        {canExport ? (
                            <>
                                <Btn as="a" href={excelUrl} download className="au-btn--green" icon={ICON.download}>
                                    Excel
                                </Btn>
                                <Btn as="a" href={pdfUrl} download tone="danger" icon={ICON.download}>
                                    PDF
                                </Btn>
                            </>
                        ) : null}
                    </div>
                </form>
            </div>

            {jumlahProduk > 0 ? (
                <>
                    <div className="au-summary">
                        <div className="au-summary-head">
                            Ringkasan · {monthLabel} {year}
                        </div>
                        <div className="au-summary-grid">
                            <div className="au-summary-cell">
                                <p className="au-summary-label">Total Channel</p>
                                <div className="au-summary-split">
                                    <div>
                                        <span className="au-summary-sub">UP</span>
                                        <p className="au-summary-value">{num(summary.up)}</p>
                                    </div>
                                    <span className="au-summary-sep">|</span>
                                    <div>
                                        <span className="au-summary-sub">BT</span>
                                        <p className="au-summary-value">{num(summary.bt)}</p>
                                    </div>
                                </div>
                            </div>
                            <div className="au-summary-cell">
                                <p className="au-summary-label">Total Tangki</p>
                                <p className="au-summary-value">
                                    {num(summary.tanki)}
                                    <span className="au-summary-unit">U</span>
                                </p>
                            </div>
                            <div className="au-summary-cell">
                                <p className="au-summary-label">Total Cover</p>
                                <p className="au-summary-value">
                                    {num(summary.cover)}
                                    <span className="au-summary-unit">U</span>
                                </p>
                            </div>
                            <div className="au-summary-cell">
                                <p className="au-summary-label">Total Keseluruhan</p>
                                <p className="au-summary-value">
                                    {num(summary.total)}
                                    <span className="au-summary-unit">U</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="au-section">
                        <div className="au-section-head">
                            <span className="au-section-pill">
                                <Icon path={CAL} />
                                {monthLabel} {year}
                            </span>
                            <span className="au-section-rule" />
                            <span className="au-section-count">{jumlahProduk} produk</span>
                        </div>

                        <div className="au-groupgrid">
                            {categories.map((cat) => {
                                const ic = CAT_ICON[cat.name] || LAIN;
                                return (
                                    <div className="au-groupcard au-prodcard" key={cat.name}>
                                        <div className="au-groupcard-head">
                                            <span className="au-prodcard-left">
                                                <span className={'au-prodcard-mark ' + ic.mark}>
                                                    <Icon path={ic.path} />
                                                </span>
                                                <span className="au-prodcard-name">{cat.name}</span>
                                                <span className="au-prodcard-count">· {cat.rows.length} produk</span>
                                            </span>
                                            <span className={'au-prodcard-total ' + ic.total}>
                                                {num(cat.total)}
                                                <span>unit</span>
                                            </span>
                                        </div>
                                        <div className="au-groupcard-body">
                                            {cat.rows.map((r) => (
                                                <div className="au-itemrow au-prodrow au-prodrow--laporan" key={r.id}>
                                                    <div className="au-itemrow-main">
                                                        <div className="au-itemrow-title">
                                                            <span className="au-prodname">{r.name}</span>
                                                            {r.seriesKva ? (
                                                                <span className={'au-chip' + (r.badge === 'Swasta' ? ' au-chip--red' : r.badge === 'Typetest' ? ' au-chip--blue' : '')}>
                                                                    {r.seriesKva}
                                                                </span>
                                                            ) : null}
                                                            <span className={'au-prodtag ' + BADGE[r.badge]}>{r.badge}</span>
                                                        </div>
                                                        {r.nomorUrut ? (
                                                            <div className="au-itemrow-sub">
                                                                <span className="au-serial" title={r.nomorUrut}>
                                                                    {r.nomorUrut.replace(/\s*\n\s*/g, '  ')}
                                                                </span>
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                    <div className="au-itemrow-right">
                                                        {r.isChannel ? (
                                                            <span className="au-updown">
                                                                UP <b className="au-up">{num(r.up)}</b>
                                                                <span>|</span>
                                                                BT <b className="au-bt">{num(r.bt)}</b>
                                                            </span>
                                                        ) : null}
                                                        <span className="au-total-pill">
                                                            <i>Total:</i>
                                                            {num(r.total)}
                                                            <i>Unit</i>
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </>
            ) : (
                <div className="au-blank">
                    <Icon path={DOC} />
                    <p>Tidak ada data produksi</p>
                    <small>Pilih bulan dan tahun yang berbeda</small>
                </div>
            )}
        </AppLayout>
    );
}
