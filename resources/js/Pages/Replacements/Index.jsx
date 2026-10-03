import { Fragment, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import {
    Badge,
    Btn,
    DeleteButton,
    Field,
    ICON,
    Icon,
    Input,
    Pagination,
    Select,
    Textarea,
} from '../../Components/Ui';

const MONTHS = [
    { value: '1', label: 'Januari' }, { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' }, { value: '4', label: 'April' },
    { value: '5', label: 'Mei' }, { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' }, { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' }, { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' }, { value: '12', label: 'Desember' },
];

function today() {
    return new Date().toISOString().slice(0, 10);
}

/**
 * Tata letak versi lama: panel filter terpisah, tabel tanpa judul kartu dengan
 * nomor unit asli sebagai chip, status yang bisa diklik, keterangan sebagai
 * baris lanjutan di bawah barisnya, dan modal tambah berkepala gradien.
 */
export default function ReplacementsIndex({
    rows,
    filters,
    indexUrl,
    storeUrl,
    products,
    years,
    departments,
    can,
}) {
    const [f, setF] = useState({
        search: filters.search || '',
        month: filters.month || '',
        year: filters.year || '',
        department: filters.department || '',
    });
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        product_id: '',
        replacement_date: today(),
        qty: 1,
        recipient: '',
        reason: '',
        original_serial: '',
        keterangan: '',
    });

    function applyFilter(e) {
        e.preventDefault();
        const params = {};
        Object.entries(f).forEach(([k, v]) => {
            if (v) params[k] = v;
        });
        router.get(indexUrl, params, { preserveState: true, replace: true });
    }

    function resetFilter() {
        setF({ search: '', month: '', year: '', department: '' });
        router.get(indexUrl, {}, { preserveState: true, replace: true });
    }

    function submit(e) {
        e.preventDefault();
        post(storeUrl, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    }

    const cols = can.delete ? 9 : 8;

    return (
        <AppLayout title="Barang Pengganti" subtitle="Catatan penggantian unit">
            <div className="au-panel">
                <form className="au-filter" onSubmit={applyFilter}>
                    <div className="au-filter-field is-wide">
                        <label className="au-filter-label">Cari</label>
                        <Input
                            value={f.search}
                            placeholder="Produk, penerima, no. unit..."
                            onChange={(e) => setF({ ...f, search: e.target.value })}
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
                        {can.create ? (
                            <Btn icon={ICON.plus} className="au-btn--green" onClick={() => setOpen(true)}>
                                Tambah
                            </Btn>
                        ) : null}
                    </div>
                </form>
            </div>

            <div className="au-tablecard">
                <div className="au-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Produk</th>
                                <th style={{ textAlign: 'center' }}>Qty</th>
                                <th>Penerima</th>
                                <th>Alasan</th>
                                <th>No. Unit Asli</th>
                                <th>Diinput</th>
                                <th style={{ textAlign: 'center' }}>Status</th>
                                {can.delete ? <th style={{ textAlign: 'center' }}>Aksi</th> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length ? (
                                rows.data.map((r) => (
                                    <Fragment key={r.id}>
                                        <tr>
                                            <td style={{ whiteSpace: 'nowrap' }}>{r.date}</td>
                                            <td style={{ fontWeight: 800 }}>{r.product}</td>
                                            <td style={{ textAlign: 'center' }}>
                                                <Badge>{r.qty}</Badge>
                                            </td>
                                            <td>{r.recipient || '—'}</td>
                                            <td style={{ maxWidth: 240 }}>{r.reason || '—'}</td>
                                            <td>
                                                {r.serials.length ? (
                                                    <span className="au-chips">
                                                        {r.serials.map((sn, i) => (
                                                            <span className="au-chip" key={sn + i}>
                                                                {sn}
                                                            </span>
                                                        ))}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td style={{ fontSize: 11, color: '#94a3b8' }}>{r.operator}</td>
                                            <td style={{ textAlign: 'center' }}>
                                                {r.canToggle ? (
                                                    <button
                                                        type="button"
                                                        className={
                                                            'au-toggle' + (r.completed ? '' : ' is-off')
                                                        }
                                                        title={
                                                            r.completed
                                                                ? 'Tandai belum selesai'
                                                                : 'Tandai selesai'
                                                        }
                                                        onClick={() =>
                                                            router.patch(
                                                                r.toggleUrl,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {r.completed ? 'Selesai' : 'Belum'}
                                                    </button>
                                                ) : (
                                                    <Badge tone={r.completed ? 'teal' : 'muted'}>
                                                        {r.completed ? 'Selesai' : 'Belum'}
                                                    </Badge>
                                                )}
                                            </td>
                                            {can.delete ? (
                                                <td style={{ textAlign: 'center' }}>
                                                    <DeleteButton
                                                        url={r.deleteUrl}
                                                        title="Hapus data barang pengganti?"
                                                        text={r.product + ' — ' + r.qty + ' unit'}
                                                    />
                                                </td>
                                            ) : null}
                                        </tr>

                                        {r.keterangan ? (
                                            <tr className="au-subrow">
                                                <td colSpan={cols}>Keterangan: {r.keterangan}</td>
                                            </tr>
                                        ) : null}
                                    </Fragment>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={cols}
                                        style={{ padding: '60px 20px', textAlign: 'center', color: '#94a3b8' }}
                                    >
                                        Belum ada data barang pengganti.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {rows.last_page > 1 ? (
                    <div className="au-tablefoot">
                        <Pagination paginator={rows} />
                    </div>
                ) : null}
            </div>

            {open ? (
                <div className="au-modal-backdrop" onClick={() => !processing && setOpen(false)}>
                    <form
                        className="au-modal au-modal--wide"
                        onClick={(e) => e.stopPropagation()}
                        onSubmit={submit}
                    >
                        <div className="au-modal-banner">
                            <div>
                                <h3>Tambah Barang Pengganti</h3>
                                <p>Catat unit pengganti yang dikeluarkan</p>
                            </div>
                            <button type="button" onClick={() => setOpen(false)} aria-label="Tutup">
                                <Icon path={ICON.close} />
                            </button>
                        </div>

                        <div className="au-modal-body">
                            <div className="au-form">
                                <Field label="Produk" required error={errors.product_id}>
                                    <Select
                                        value={data.product_id}
                                        error={errors.product_id}
                                        placeholder="— Pilih produk —"
                                        options={products}
                                        onChange={(e) => setData('product_id', e.target.value)}
                                    />
                                </Field>

                                <div className="au-form-grid au-form-grid-2">
                                    <Field label="Tanggal" required error={errors.replacement_date}>
                                        <Input
                                            type="date"
                                            value={data.replacement_date}
                                            error={errors.replacement_date}
                                            onChange={(e) =>
                                                setData('replacement_date', e.target.value)
                                            }
                                        />
                                    </Field>

                                    <Field label="Jumlah unit" required error={errors.qty}>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={data.qty}
                                            error={errors.qty}
                                            onChange={(e) => setData('qty', e.target.value)}
                                        />
                                    </Field>
                                </div>

                                <div className="au-form-grid au-form-grid-2">
                                    <Field label="Penerima" error={errors.recipient}>
                                        <Input
                                            value={data.recipient}
                                            error={errors.recipient}
                                            maxLength={150}
                                            onChange={(e) => setData('recipient', e.target.value)}
                                        />
                                    </Field>

                                    <Field
                                        label="Nomor unit asli"
                                        error={errors.original_serial}
                                        hint="Pisahkan dengan koma bila lebih dari satu"
                                    >
                                        <Input
                                            value={data.original_serial}
                                            error={errors.original_serial}
                                            maxLength={150}
                                            onChange={(e) =>
                                                setData('original_serial', e.target.value)
                                            }
                                        />
                                    </Field>
                                </div>

                                <Field label="Alasan penggantian" error={errors.reason}>
                                    <Input
                                        value={data.reason}
                                        error={errors.reason}
                                        maxLength={255}
                                        onChange={(e) => setData('reason', e.target.value)}
                                    />
                                </Field>

                                <Field label="Keterangan" error={errors.keterangan}>
                                    <Textarea
                                        value={data.keterangan}
                                        error={errors.keterangan}
                                        maxLength={1000}
                                        style={{ minHeight: 70 }}
                                        onChange={(e) => setData('keterangan', e.target.value)}
                                    />
                                </Field>
                            </div>
                        </div>

                        <div className="au-modal-foot">
                            <Btn tone="ghost" onClick={() => setOpen(false)} disabled={processing}>
                                Batal
                            </Btn>
                            <Btn type="submit" icon={ICON.save} disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Btn>
                        </div>
                    </form>
                </div>
            ) : null}
        </AppLayout>
    );
}
