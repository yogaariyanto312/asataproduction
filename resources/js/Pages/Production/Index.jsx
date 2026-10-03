import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, DeleteButton, Field, ICON, Icon, Input, Pagination, Select, Textarea } from '../../Components/Ui';

const MONTHS = [
    { value: '1', label: 'Januari' }, { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' }, { value: '4', label: 'April' },
    { value: '5', label: 'Mei' }, { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' }, { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' }, { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' }, { value: '12', label: 'Desember' },
];

const CAL = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';

// Warna ikon & angka total per kategori, sama seperti versi Blade.
const CAT_ICON = {
    Channel: {
        path: 'M13 10V3L4 14h7v7l9-11h-7z',
        mark: 'au-prodcard-mark--blue',
        total: 'au-prodcard-total--blue',
    },
    Cover: {
        path: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        mark: 'au-prodcard-mark--green',
        total: 'au-prodcard-total--green',
    },
    Tangki: {
        path: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
        mark: 'au-prodcard-mark--amber',
        total: 'au-prodcard-total--amber',
    },
};

/**
 * Versi lama membedakan jenis seri lewat WARNA TEKS nomor seri — merah untuk
 * Swasta, biru untuk Typetest, netral untuk PLN — dengan pil kecil sewarna
 * sebagai pelengkap, bukan badge besar.
 */
const SERIES_TONE = {
    Swasta: { chip: 'au-chip--red', tag: 'au-prodtag--red' },
    Typetest: { chip: 'au-chip--blue', tag: 'au-prodtag--blue' },
    // PLN cukup ditandai warna seri yang netral — pilnya akan muncul di hampir
    // semua baris dan justru meramaikan kartu.
    PLN: { chip: '', tag: null },
};
const NO_TONE = { chip: '', tag: null };

// Sama dengan $fmtQty versi Blade: bilangan bulat apa adanya, pecahan 1 desimal.
// number_format() PHP: ribuan pakai koma, desimal pakai titik (1,234 / 12.5).
const nf = new Intl.NumberFormat('en-US');
const nf1 = new Intl.NumberFormat('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const num = (v) => {
    const n = Number(v) || 0;
    return n % 1 === 0 ? nf.format(n) : nf1.format(n);
};

/**
 * Tata letak versi lama: panel filter, ringkasan tersegmen (Channel UP|BT,
 * Cover, Tangki, Total), lalu daftar dikelompokkan per TANGGAL dan di dalamnya
 * per KATEGORI sebagai kartu.
 */
export default function ProductionIndex({
    days,
    pagination,
    summary,
    summaryLabel,
    filters,
    indexUrl,
    createUrl,
    canInput,
    can,
    products,
    years,
    departments,
    rejectCategories,
    today,
}) {
    // Dialog "Reject Unit": null = tutup, selain itu = baris produksi terpilih.
    const [rejectRow, setRejectRow] = useState(null);
    const [rj, setRj] = useState({ jumlah: 1, tanggal: today, reject_category: '', reject_notes: '' });
    const [rjErr, setRjErr] = useState({});
    const [rjBusy, setRjBusy] = useState(false);

    function openReject(log) {
        setRj({ jumlah: 1, tanggal: today, reject_category: '', reject_notes: '' });
        setRjErr({});
        setRejectRow(log);
    }

    function submitReject(e) {
        e.preventDefault();
        if (rjBusy) return;
        setRjBusy(true);
        router.post(rejectRow.rejectUrl, rj, {
            preserveScroll: true,
            onError: (errs) => setRjErr(errs),
            onSuccess: () => setRejectRow(null),
            onFinish: () => setRjBusy(false),
        });
    }

    const [f, setF] = useState({
        search: filters.search || '',
        product_name: filters.product_name || '',
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
        month: filters.month || '',
        year: filters.year || '',
        department: filters.department || '',
    });

    const [memuat, setMemuat] = useState(false);
    const pertama = useRef(true);
    const tunda = useRef(null);

    function kirim(nilai) {
        const params = {};
        Object.entries(nilai).forEach(([k, v]) => {
            if (v) params[k] = v;
        });

        setMemuat(true);
        router.get(indexUrl, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            // Hanya daftarnya yang berubah. Pilihan produk/tahun/departemen dan
            // ringkasan harian tidak terpengaruh filter, jadi tidak perlu ikut
            // dikirim ulang setiap ketikan — penting di koneksi lambat.
            only: ['days', 'pagination', 'filters'],
            onFinish: () => setMemuat(false),
        });
    }

    // Filter berjalan langsung sambil mengetik. Jeda singkat supaya satu kata
    // tidak menjadi beberapa permintaan ke server.
    useEffect(() => {
        if (pertama.current) {
            pertama.current = false;
            return;
        }

        clearTimeout(tunda.current);
        tunda.current = setTimeout(() => kirim(f), 300);

        return () => clearTimeout(tunda.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [f.search, f.product_name, f.date_from, f.date_to, f.month, f.year, f.department]);

    function applyFilter(e) {
        e.preventDefault();
        clearTimeout(tunda.current);
        kirim(f);
    }

    function resetFilter() {
        setF({
            search: '', product_name: '', date_from: '', date_to: '',
            month: '', year: '', department: '',
        });
    }

    return (
        <AppLayout title="Riwayat Produksi" subtitle="Catatan produksi harian">
            <div className="au-panel">
                <form className="au-filter" onSubmit={applyFilter}>
                    <div className="au-filter-field is-wide">
                        <label className="au-filter-label">
                            Cari
                            {memuat ? <span className="au-spin" aria-label="Mencari" /> : null}
                        </label>
                        <Input
                            value={f.search}
                            placeholder="Produk, operator, seri..."
                            onChange={(e) => setF({ ...f, search: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Produk</label>
                        <Select
                            value={f.product_name}
                            placeholder="Semua Produk"
                            options={products}
                            onChange={(e) => setF({ ...f, product_name: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Dari Tanggal</label>
                        <Input
                            type="date"
                            value={f.date_from}
                            onChange={(e) => setF({ ...f, date_from: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Sampai Tanggal</label>
                        <Input
                            type="date"
                            value={f.date_to}
                            onChange={(e) => setF({ ...f, date_to: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Bulan</label>
                        <Select
                            value={f.month}
                            placeholder="Semua Bulan"
                            options={MONTHS}
                            onChange={(e) => setF({ ...f, month: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Tahun</label>
                        <Select
                            value={f.year}
                            placeholder="Semua Tahun"
                            options={years}
                            onChange={(e) => setF({ ...f, year: e.target.value })}
                        />
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
                        <Btn type="submit">Filter</Btn>
                        <Btn tone="ghost" onClick={resetFilter}>
                            Reset
                        </Btn>
                        {canInput ? (
                            <Btn as="a" href={createUrl} icon={ICON.plus} className="au-btn--green">
                                Input Produksi
                            </Btn>
                        ) : null}
                    </div>
                </form>
            </div>

            <div className="au-summary">
                <div className="au-summary-head">Ringkasan · {summaryLabel}</div>
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
                        <p className="au-summary-label">Total Cover</p>
                        <p className="au-summary-value">
                            {num(summary.cover)}
                            <span className="au-summary-unit">U</span>
                        </p>
                    </div>

                    <div className="au-summary-cell">
                        <p className="au-summary-label">Total Tangki</p>
                        <p className="au-summary-value">
                            {num(summary.tanki)}
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

            <div className={'au-live' + (memuat ? ' is-memuat' : '')}>
            {days.length ? (
                days.map((day) => (
                    <div className="au-section" key={day.date}>
                        <div className="au-section-head">
                            <span className="au-section-pill">
                                <Icon path={CAL} />
                                {day.dayName}
                                <span style={{ opacity: 0.8, fontWeight: 600 }}>{day.dateLabel}</span>
                            </span>
                            <span className="au-section-rule" />
                            <span className="au-section-count">
                                {num(day.total)} unit · {day.count} entri
                            </span>
                        </div>

                        <div className="au-groupgrid">
                            {day.categories.map((cat) => {
                                const ic = CAT_ICON[cat.name] || {
                                    path: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2',
                                    mark: '',
                                    total: '',
                                };

                                return (
                                    <div
                                        className="au-groupcard au-prodcard"
                                        key={day.date + '-' + cat.name}
                                    >
                                        <div className="au-groupcard-head">
                                            <span className="au-prodcard-left">
                                                <span className={'au-prodcard-mark ' + ic.mark}>
                                                    <Icon path={ic.path} />
                                                </span>
                                                <span className="au-prodcard-name">{cat.name}</span>
                                                <span className="au-prodcard-count">
                                                    · {cat.items.length} entri
                                                </span>
                                            </span>
                                            <span className={'au-prodcard-total ' + ic.total}>
                                                {num(cat.total)}
                                                <span>unit</span>
                                            </span>
                                        </div>

                                        <div className="au-groupcard-body">
                                            {cat.items.map((log) => {
                                                const tone = SERIES_TONE[log.badge] || NO_TONE;

                                                return (
                                                <div className="au-itemrow au-prodrow" key={log.id}>
                                                    <div className="au-itemrow-main">
                                                        <div className="au-itemrow-title">
                                                            <span className="au-prodname">
                                                                {log.name}
                                                            </span>
                                                            {log.seriesKva ? (
                                                                <span
                                                                    className={
                                                                        'au-chip ' + tone.chip
                                                                    }
                                                                >
                                                                    {log.seriesKva}
                                                                </span>
                                                            ) : null}
                                                            {tone.tag ? (
                                                                <span
                                                                    className={
                                                                        'au-prodtag ' + tone.tag
                                                                    }
                                                                >
                                                                    {log.badge}
                                                                </span>
                                                            ) : null}
                                                        </div>

                                                        <div className="au-itemrow-sub">
                                                            {log.reject > 0 ? (
                                                                <span className="au-prodtag au-prodtag--red">
                                                                    ✕ {log.reject}
                                                                </span>
                                                            ) : null}
                                                            {log.serialLine ? (
                                                                <>
                                                                    <span className="au-serial">
                                                                        {log.serialLine}
                                                                    </span>
                                                                    <span>·</span>
                                                                </>
                                                            ) : log.notes ? (
                                                                <>
                                                                    <span className="au-subtext">
                                                                        {log.notes.slice(0, 40)}
                                                                    </span>
                                                                    <span>·</span>
                                                                </>
                                                            ) : null}
                                                            <span>{log.operator}</span>
                                                        </div>

                                                        {log.reject > 0 && (log.rejectLabel || log.rejectNotes) ? (
                                                            <p
                                                                className="au-itemrow-note"
                                                                style={{ color: '#f87171' }}
                                                                title={log.rejectNotes || ''}
                                                            >
                                                                {log.rejectLabel ? <b>{log.rejectLabel}</b> : null}
                                                                {log.rejectLabel && log.rejectNotes ? ' — ' : null}
                                                                {log.rejectNotes}
                                                            </p>
                                                        ) : null}
                                                        {log.keterangan ? (
                                                            <p className="au-itemrow-note" title={log.keterangan}>
                                                                {log.keterangan}
                                                            </p>
                                                        ) : null}
                                                    </div>

                                                    <div className="au-itemrow-right">
                                                        {log.isChannel ? (
                                                            <span className="au-updown">
                                                                UP{' '}
                                                                <b className="au-up">
                                                                    {num(log.up)}
                                                                </b>
                                                                <span>|</span>
                                                                BT{' '}
                                                                <b className="au-bt">
                                                                    {num(log.bt)}
                                                                </b>
                                                            </span>
                                                        ) : null}
                                                        <span className="au-total-pill">
                                                            <i>Total:</i>
                                                            {num(log.total)}
                                                            <i>Unit</i>
                                                        </span>
                                                    </div>

                                                    <div className="au-prodrow-actions">
                                                        <a
                                                            className="au-iconlink"
                                                            href={log.showUrl}
                                                            title="Detail"
                                                        >
                                                            <Icon path={ICON.eye} />
                                                        </a>
                                                        {can.edit ? (
                                                            <a
                                                                className="au-iconlink"
                                                                href={log.editUrl}
                                                                title="Edit"
                                                            >
                                                                <Icon path={ICON.edit} />
                                                            </a>
                                                        ) : null}
                                                        {can.reject && log.canReject ? (
                                                            <button
                                                                type="button"
                                                                className="au-iconlink"
                                                                title="Reject unit"
                                                                onClick={() => openReject(log)}
                                                            >
                                                                <Icon path={ICON.alert} />
                                                            </button>
                                                        ) : null}
                                                        {can.delete ? (
                                                            <DeleteButton
                                                                url={log.deleteUrl}
                                                                title="Hapus entri produksi?"
                                                                text={
                                                                    log.name +
                                                                    ' — ' +
                                                                    num(log.total) +
                                                                    ' unit'
                                                                }
                                                            />
                                                        ) : null}
                                                    </div>
                                                </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))
            ) : (
                <div className="au-blank">
                    <Icon path={CAL} />
                    <p>Tidak ada data produksi yang cocok dengan filter.</p>
                </div>
            )}

            </div>

            <Pagination paginator={pagination} />

            {rejectRow ? (
                <div className="au-modal-backdrop" onClick={() => !rjBusy && setRejectRow(null)}>
                    <form className="au-modal" onClick={(e) => e.stopPropagation()} onSubmit={submitReject}>
                        <div className="au-modal-banner au-modal-banner--danger">
                            <div>
                                <h3>Reject Unit</h3>
                                <p>
                                    {rejectRow.name}
                                    {rejectRow.seriesKva ? ' · ' + rejectRow.seriesKva : ''} — {num(rejectRow.total)} unit
                                </p>
                            </div>
                            <button type="button" onClick={() => setRejectRow(null)} aria-label="Tutup">
                                <Icon path={ICON.close} />
                            </button>
                        </div>

                        <div className="au-modal-body">
                            <div className="au-form">
                                <div className="au-form-grid au-form-grid-2">
                                    <Field label="Jumlah reject" required error={rjErr.jumlah}>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={Math.floor(rejectRow.total)}
                                            value={rj.jumlah}
                                            error={rjErr.jumlah}
                                            onChange={(e) => setRj({ ...rj, jumlah: e.target.value })}
                                        />
                                    </Field>
                                    <Field label="Tanggal ditemukan" required error={rjErr.tanggal}>
                                        <Input
                                            type="date"
                                            min={rejectRow.dateInput}
                                            max={today}
                                            value={rj.tanggal}
                                            error={rjErr.tanggal}
                                            onChange={(e) => setRj({ ...rj, tanggal: e.target.value })}
                                        />
                                    </Field>
                                </div>

                                <Field label="Kategori penyebab" error={rjErr.reject_category}>
                                    <Select
                                        value={rj.reject_category}
                                        error={rjErr.reject_category}
                                        placeholder="— Tanpa kategori —"
                                        options={rejectCategories}
                                        onChange={(e) => setRj({ ...rj, reject_category: e.target.value })}
                                    />
                                </Field>

                                <Field label="Keterangan reject" error={rjErr.reject_notes}>
                                    <Textarea
                                        value={rj.reject_notes}
                                        error={rjErr.reject_notes}
                                        maxLength={300}
                                        style={{ minHeight: 60 }}
                                        placeholder="Mis. karoseri menggembung, perlu divakum ulang"
                                        onChange={(e) => setRj({ ...rj, reject_notes: e.target.value })}
                                    />
                                </Field>

                                <p className="au-hint">
                                    Entri tanggal produksi akan berkurang {rj.jumlah || 0} unit; nomor urut paling
                                    akhir dilepas dan reject dicatat di tanggal ditemukan.
                                </p>
                            </div>
                        </div>

                        <div className="au-modal-foot">
                            <Btn tone="ghost" onClick={() => setRejectRow(null)} disabled={rjBusy}>
                                Batal
                            </Btn>
                            <Btn type="submit" icon={ICON.save} disabled={rjBusy}>
                                {rjBusy ? 'Menyimpan...' : 'Tandai Reject'}
                            </Btn>
                        </div>
                    </form>
                </div>
            ) : null}
        </AppLayout>
    );
}
