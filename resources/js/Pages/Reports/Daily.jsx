import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON, Input, Select } from '../../Components/Ui';

const nf = new Intl.NumberFormat('en-US');
const nf1 = new Intl.NumberFormat('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const num = (v) => {
    const n = Number(v) || 0;
    return n % 1 === 0 ? nf.format(n) : nf1.format(n);
};
const PRINT = 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z';

/**
 * Laporan Harian — mengikuti Production-QC-Logging-System: bilah tanggal
 * (tidak ikut tercetak), kepala kertas khusus cetak, kartu UP/BT/Grand Total,
 * lalu tabel yang urut seperti Riwayat Produksi.
 */
export default function ReportsDaily({
    date,
    today,
    dateLabel,
    printedAt,
    printedBy,
    indexUrl,
    pdfUrl,
    canExport,
    departments,
    deptFilter,
    totals,
    logs,
}) {
    const [f, setF] = useState({ date, department: deptFilter || '' });

    function tampilkan(e) {
        e.preventDefault();
        router.get(indexUrl, Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveScroll: true });
    }

    return (
        <AppLayout title="Laporan Harian" subtitle="Rekap produksi per hari">
            <div className="au-panel tanpa-cetak">
                <form className="au-filter" onSubmit={tampilkan}>
                    <div className="au-filter-field">
                        <label className="au-filter-label">Tanggal</label>
                        <Input type="date" value={f.date} max={today} onChange={(e) => setF({ ...f, date: e.target.value })} />
                    </div>
                    {departments.length ? (
                        <div className="au-filter-field">
                            <label className="au-filter-label">Departemen</label>
                            <Select value={f.department} placeholder="Semua Departemen" options={departments}
                                onChange={(e) => setF({ ...f, department: e.target.value })} />
                        </div>
                    ) : null}
                    <div className="au-filter-actions">
                        <Btn type="submit">Tampilkan</Btn>
                        {canExport ? (
                            <Btn as="a" href={pdfUrl} download tone="danger" icon={ICON.download}>
                                Export PDF
                            </Btn>
                        ) : null}
                        <Btn tone="ghost" icon={PRINT} onClick={() => window.print()}>
                            Print
                        </Btn>
                    </div>
                </form>
            </div>

            {/* Kepala kertas: bilah atas aplikasi tidak ikut tercetak. */}
            <div className="hanya-cetak au-cetak-kepala">
                <h1>Laporan Produksi Harian</h1>
                <p>{dateLabel}</p>
                <small>
                    Dicetak {printedAt} · {printedBy}
                </small>
            </div>

            <div className="au-harian-kartu">
                <div className="is-up">
                    <p>UP</p>
                    <b>{num(totals.up)}</b>
                </div>
                <div className="is-bt">
                    <p>BT</p>
                    <b>{num(totals.bt)}</b>
                </div>
                <div className="is-total">
                    <p>Grand Total</p>
                    <b>{num(totals.total)}</b>
                </div>
            </div>

            <div className="au-tablecard au-harian-tabel">
                <div className="au-harian-judul">Laporan Harian — {dateLabel}</div>
                <div className="au-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Produk</th>
                                <th>Seri</th>
                                <th>Kategori</th>
                                <th style={{ textAlign: 'center' }}>UP</th>
                                <th style={{ textAlign: 'center' }}>BT</th>
                                <th style={{ textAlign: 'center' }}>Total</th>
                                <th>No. Urut</th>
                                <th>Operator</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.length ? (
                                logs.map((l, i) => (
                                    <tr key={l.id}>
                                        <td style={{ color: '#94a3b8' }}>{i + 1}</td>
                                        <td style={{ fontWeight: 800 }}>{l.product}</td>
                                        <td className="au-mono">{l.seriesKva}</td>
                                        <td>{l.category}</td>
                                        <td style={{ textAlign: 'center' }}>{num(l.up)}</td>
                                        <td style={{ textAlign: 'center' }}>{num(l.bt)}</td>
                                        <td style={{ textAlign: 'center', fontWeight: 800 }} className="au-harian-total">{num(l.total)}</td>
                                        <td className="au-mono" style={{ whiteSpace: 'pre-line' }}>{l.notes}</td>
                                        <td>{l.operator}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={9} style={{ padding: '48px 16px', textAlign: 'center', color: '#94a3b8' }}>
                                        Tidak ada data produksi untuk tanggal ini
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        {logs.length ? (
                            <tfoot>
                                <tr>
                                    <td colSpan={4} style={{ fontWeight: 800 }}>TOTAL</td>
                                    <td style={{ textAlign: 'center', fontWeight: 800 }}>{num(totals.up)}</td>
                                    <td style={{ textAlign: 'center', fontWeight: 800 }}>{num(totals.bt)}</td>
                                    <td style={{ textAlign: 'center', fontWeight: 900 }} className="au-harian-total">{num(totals.total)}</td>
                                    <td />
                                    <td />
                                </tr>
                            </tfoot>
                        ) : null}
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
