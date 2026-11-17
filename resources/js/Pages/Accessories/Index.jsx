import { useEffect, useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, DeleteButton, Field, ICON, Icon, IconBtn, Input, Pagination, Select, Textarea } from '../../Components/Ui';
import { konfirmasi } from '../../dialog';

const MONTHS = [
    { value: '1', label: 'Januari' }, { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' }, { value: '4', label: 'April' },
    { value: '5', label: 'Mei' }, { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' }, { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' }, { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' }, { value: '12', label: 'Desember' },
];
const INFO = 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
const TAG = 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z';

const today = () => new Date().toISOString().slice(0, 10);
const pad = (n) => String(Math.round(n)).padStart(3, '0');

/**
 * "Pilih atau ketik" (TomSelect create:true di acuan): fokus/ketik membuka daftar nama yang pernah dipakai,
 * tersaring sesuai ketikan; nama baru dipakai lewat baris "Tambah …". Panah atas/bawah + Enter, Esc menutup.
 */
function PilihKetik({ value, onChange, options, placeholder, error, autoFocus }) {
    const [buka, setBuka] = useState(false);
    const [aktif, setAktif] = useState(0);
    const q = (value || '').trim();
    const qk = q.toLowerCase();
    const cocok = options.filter((o) => !qk || o.toLowerCase().includes(qk));
    const tambah = q && !options.some((o) => o.toLowerCase() === qk);
    const item = [...(tambah ? [{ nilai: q, baru: true }] : []), ...cocok.map((o) => ({ nilai: o }))];

    function pilih(v) {
        onChange(v);
        setBuka(false);
    }

    function tombol(e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setBuka(true); setAktif((i) => Math.min(i + 1, item.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setAktif((i) => Math.max(i - 1, 0)); }
        else if (e.key === 'Enter' && buka && item[aktif]) { e.preventDefault(); pilih(item[aktif].nilai); }
        else if (e.key === 'Escape' && buka) { e.stopPropagation(); setBuka(false); }
    }

    return (
        <div className="au-pilihketik">
            <Input
                value={value}
                error={error}
                autoFocus={autoFocus}
                maxLength={150}
                placeholder={placeholder}
                autoComplete="off"
                onFocus={() => { setBuka(true); setAktif(0); }}
                onClick={() => setBuka(true)}
                onBlur={() => setBuka(false)}
                onKeyDown={tombol}
                onChange={(e) => { onChange(e.target.value); setBuka(true); setAktif(0); }}
            />
            <Icon path={ICON.chevronDown || 'M19 9l-7 7-7-7'} />
            {buka && item.length ? (
                <ul className="au-pilihketik-daftar" role="listbox">
                    {item.map((it, i) => (
                        <li
                            key={(it.baru ? '+' : '') + it.nilai}
                            role="option"
                            aria-selected={i === aktif}
                            className={(i === aktif ? 'is-aktif' : '') + (it.baru ? ' is-baru' : '')}
                            onMouseEnter={() => setAktif(i)}
                            // mousedown + preventDefault: pilihan masuk sebelum input kehilangan fokus.
                            onMouseDown={(e) => { e.preventDefault(); pilih(it.nilai); }}
                        >
                            {it.baru ? <><Icon path={ICON.plus} /> Tambah &quot;{it.nilai}&quot;</> : it.nilai}
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

/** Dropdown produk berkelompok (grup "Nomor Seri" + per nama produk). */
function SelectProduk({ value, onChange, groups, error }) {
    return (
        <select className={'au-select' + (error ? ' has-error' : '')} value={value} onChange={(e) => onChange(e.target.value)}>
            <option value="">-- Tanpa seri (aksesoris lepas) --</option>
            {groups.map((g) => (
                <optgroup key={g.label} label={g.label}>
                    {g.options.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </optgroup>
            ))}
        </select>
    );
}

/**
 * Aksesoris Keluar — mengikuti Production-QC-Logging-System: filter, info
 * halaman, tabel (layar lebar) + daftar kartu (HP), modal Tambah dengan
 * "Nomor Awal" → pratinjau nomor urut dan saran lanjutan per jenis+seri, serta
 * modal Edit. Tambahan asata: filter departemen (developer).
 */
// Keterangan dibatasi 80 karakter (server juga memeriksa).
const BATAS_KET = 80;

export default function AccessoriesIndex({
    rows,
    filters,
    indexUrl,
    storeUrl,
    exportUrl,
    units,
    can,
    departments,
    years,
    productGroups,
    productSeries,
    accessoryNames,
    lastSerials,
    categories,
}) {
    const [f, setF] = useState({
        search: filters.search || '',
        month: filters.month || '',
        year: filters.year || '',
        category: filters.category || '',
        department: filters.department || '',
    });
    const [kelola, setKelola] = useState(false);
    const [tambah, setTambah] = useState(false);
    const [edit, setEdit] = useState(null);

    const adaFilter = !!(filters.search || filters.month || filters.year || filters.category || filters.department);
    const actionCol = can.create || can.edit || can.delete;

    function terapkan(e) {
        e.preventDefault();
        router.get(indexUrl, Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Aksesoris Keluar" subtitle="Catatan aksesoris yang keluar dari produksi">
            <div className="au-panel">
                <form className="au-filter" onSubmit={terapkan}>
                    <div className="au-filter-field is-wide">
                        <label className="au-filter-label">Cari</label>
                        <Input value={f.search} placeholder="Nama aksesoris, seri, keterangan..." onChange={(e) => setF({ ...f, search: e.target.value })} />
                    </div>
                    <div className="au-filter-field">
                        <label className="au-filter-label">Bulan</label>
                        <Select value={f.month} placeholder="Semua Bulan" options={MONTHS} onChange={(e) => setF({ ...f, month: e.target.value })} />
                    </div>
                    <div className="au-filter-field">
                        <label className="au-filter-label">Tahun</label>
                        <Select value={f.year} placeholder="Semua Tahun" options={years} onChange={(e) => setF({ ...f, year: e.target.value })} />
                    </div>
                    <div className="au-filter-field">
                        <label className="au-filter-label">Kategori</label>
                        <Select
                            value={f.category}
                            placeholder="Semua Kategori"
                            options={categories.map((k) => ({ value: k.name, label: k.name }))}
                            onChange={(e) => setF({ ...f, category: e.target.value })}
                        />
                    </div>
                    {departments.length ? (
                        <div className="au-filter-field">
                            <label className="au-filter-label">Departemen</label>
                            <Select value={f.department} placeholder="Semua Departemen" options={departments} onChange={(e) => setF({ ...f, department: e.target.value })} />
                        </div>
                    ) : null}
                    <div className="au-filter-actions">
                        <Btn type="submit" tone="ghost">Filter</Btn>
                        {adaFilter ? (
                            <Btn as="a" href={indexUrl} tone="ghost">
                                Reset
                            </Btn>
                        ) : null}
                        {can.edit || can.delete ? (
                            <Btn tone="ghost" icon={TAG} onClick={() => setKelola(true)}>
                                Kategori
                            </Btn>
                        ) : null}
                        {can.export ? (
                            <Btn as="a" href={exportUrl} download tone="ghost" icon={ICON.download}>
                                Export
                            </Btn>
                        ) : null}
                        {can.create ? (
                            <Btn icon={ICON.plus} onClick={() => setTambah(true)}>
                                Tambah Aksesoris
                            </Btn>
                        ) : null}
                    </div>
                </form>
            </div>

            {rows.total > 0 ? (
                <div className="au-aks-info">
                    <p>
                        Hal. <b>{rows.current_page}</b> · <b>{rows.data.length}</b> entri dari <b>{rows.total}</b> entri total
                    </p>
                    {rows.last_page > 1 ? <Pagination paginator={rows} /> : null}
                </div>
            ) : null}

            {/* Tabel — layar lebar */}
            <div className="au-tablecard au-aks-tabel">
                <div className="au-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th className="au-nowrap">Tanggal</th>
                                <th className="au-nowrap">Aksesoris</th>
                                <th className="au-nowrap">Produk / No. Urut</th>
                                <th className="au-nowrap" style={{ textAlign: 'center' }}>Jumlah</th>
                                <th className="au-nowrap">Diinput</th>
                                <th style={{ width: '100%' }}>Keterangan</th>
                                {actionCol ? <th style={{ textAlign: 'center' }}>Aksi</th> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length ? (
                                rows.data.map((a) => (
                                    <tr key={a.id}>
                                        <td className="au-nowrap">{a.date}</td>
                                        <td className="au-nowrap" style={{ fontWeight: 800 }}>{a.name}</td>
                                        <td className="au-nowrap">
                                            {a.series ? (
                                                <>
                                                    <div style={{ fontWeight: 700 }}>{a.series}</div>
                                                    <small className="au-mono au-redup">
                                                        {a.kva ? a.kva + ' KVA' : ''}
                                                        {a.serial ? ' · ' + a.serial : ''}
                                                    </small>
                                                </>
                                            ) : a.serial ? (
                                                <span className="au-mono">{a.serial}</span>
                                            ) : (
                                                <span className="au-redup">—</span>
                                            )}
                                        </td>
                                        <td className="au-nowrap" style={{ textAlign: 'center' }}>
                                            <span className="au-aks-qty">{a.qty}</span> <small className="au-redup">{a.unit}</small>
                                        </td>
                                        <td className="au-nowrap au-redup">{a.operator}</td>
                                        <td className="au-redup">{a.keterangan || ''}</td>
                                        {actionCol ? (
                                            <td style={{ textAlign: 'center' }}>
                                                <span className="au-actions">
                                                    {can.edit ? <IconBtn icon={ICON.edit} title="Edit" onClick={() => setEdit(a)} /> : null}
                                                    {can.delete ? (
                                                        <DeleteButton url={a.deleteUrl} title="Hapus Aksesoris" text={"Hapus data aksesoris '" + a.name + "' (" + a.qty + ' ' + (a.unit || '') + ')?'} />
                                                    ) : null}
                                                </span>
                                            </td>
                                        ) : null}
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={actionCol ? 7 : 6} className="au-aks-kosong">
                                        Belum ada data aksesoris keluar.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Daftar kartu — HP & tablet */}
            <div className="au-aks-kartu-list">
                {rows.data.length ? (
                    rows.data.map((a) => (
                        <div className="au-aks-kartu" key={a.id}>
                            <div className="au-aks-kartu-atas">
                                <div>
                                    <p className="au-aks-nama">{a.name}</p>
                                    <small className="au-redup">{a.date}</small>
                                </div>
                                <span className="au-aks-qty-besar">
                                    <b>{a.qty}</b> {a.unit}
                                </span>
                            </div>
                            {a.series || a.serial ? (
                                <div className="au-aks-chips">
                                    {a.series ? <span className="au-chip">{a.series}{a.kva ? ' · ' + a.kva + ' KVA' : ''}</span> : null}
                                    {a.serial ? <span className="au-chip au-mono">{a.serial}</span> : null}
                                </div>
                            ) : null}
                            {a.keterangan ? <p className="au-aks-ket">{a.keterangan}</p> : null}
                            <div className="au-aks-kartu-bawah">
                                <small className="au-redup">Diinput {a.operator}</small>
                                {actionCol ? (
                                    <span className="au-actions">
                                        {can.edit ? <IconBtn icon={ICON.edit} title="Edit" onClick={() => setEdit(a)} /> : null}
                                        {can.delete ? (
                                            <DeleteButton url={a.deleteUrl} title="Hapus Aksesoris" text={"Hapus data aksesoris '" + a.name + "' (" + a.qty + ' ' + (a.unit || '') + ')?'} />
                                        ) : null}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="au-aks-kosong">Belum ada data aksesoris keluar.</div>
                )}
            </div>

            {tambah ? (
                <ModalTambah
                    onClose={() => setTambah(false)}
                    storeUrl={storeUrl}
                    units={units}
                    productGroups={productGroups}
                    productSeries={productSeries}
                    lastSerials={lastSerials}
                    accessoryNames={accessoryNames}
                />
            ) : null}
            {edit ? <ModalEdit row={edit} onClose={() => setEdit(null)} units={units} productGroups={productGroups} accessoryNames={accessoryNames} /> : null}
            {kelola ? <ModalKategori categories={categories} can={can} onClose={() => setKelola(false)} /> : null}
        </AppLayout>
    );
}

function ModalTambah({ onClose, storeUrl, units, productGroups, productSeries, lastSerials, accessoryNames }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        accessory_date: today(),
        qty: 1,
        unit: 'pcs',
        product_id: '',
        serial_number: '',
        keterangan: '',
    });
    const [awal, setAwal] = useState('');

    // Saran: nomor urut terakhir untuk jenis + seri yang dipilih.
    const info = useMemo(() => {
        const name = (data.name || '').trim();
        if (!name) return null;
        const series = data.product_id ? productSeries[data.product_id] || '' : '';
        return lastSerials[name + '|' + series] || null;
    }, [data.name, data.product_id, productSeries, lastSerials]);

    // Isi otomatis Nomor Awal (lanjut dari nomor SETELAH yang terakhir) bila kosong.
    useEffect(() => {
        if (info && !awal) setAwal(String(info.last_number + 1));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [info]);

    // Rangkai Nomor Awal + jumlah: 1 pcs → NO.015, 3 pcs mulai 15 → NO.015-017.
    const qty = parseInt(data.qty, 10) || 0;
    const s = parseInt(awal, 10);
    const preview = !s ? '—' : qty <= 0 ? 'Isi jumlah →' : qty === 1 ? 'NO.' + pad(s) : 'NO.' + pad(s) + '-' + pad(s + qty - 1);

    useEffect(() => {
        setData('serial_number', preview.startsWith('NO.') ? preview : '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [preview]);

    function simpan(e) {
        e.preventDefault();
        post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <div className="au-modal-backdrop" onClick={() => !processing && onClose()}>
            <form className="au-modal au-modal--wide" onClick={(e) => e.stopPropagation()} onSubmit={simpan}>
                <div className="au-modal-banner">
                    <div>
                        <h3>Tambah Aksesoris Keluar</h3>
                        <p>Catat aksesoris yang keluar dari produksi</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup">
                        <Icon path={ICON.close} />
                    </button>
                </div>
                <div className="au-modal-body">
                    <div className="au-form">
                        <Field label="Nama / Jenis Aksesoris" required error={errors.name}>
                            <PilihKetik value={data.name} options={accessoryNames} error={errors.name} autoFocus
                                placeholder="Pilih atau ketik nama aksesoris baru..." onChange={(v) => setData('name', v)} />
                        </Field>
                        <div className="au-form-grid au-form-grid-2">
                            <Field label="Tanggal" required error={errors.accessory_date}>
                                <Input type="date" value={data.accessory_date} error={errors.accessory_date} onChange={(e) => setData('accessory_date', e.target.value)} />
                            </Field>
                            <div className="au-form-grid au-form-grid-2 au-aks-jumlah">
                                <Field label="Jumlah" required error={errors.qty}>
                                    <Input type="number" min={1} max={99999} value={data.qty} error={errors.qty} onChange={(e) => setData('qty', e.target.value)} />
                                </Field>
                                <Field label="Satuan" error={errors.unit}>
                                    <Select value={data.unit} options={units.map((u) => ({ value: u, label: u }))} onChange={(e) => setData('unit', e.target.value)} />
                                </Field>
                            </div>
                        </div>
                        <div className="au-form-grid au-form-grid-2">
                            <Field label="Seri Produk Terkait (opsional)" error={errors.product_id}>
                                <SelectProduk value={data.product_id} groups={productGroups} error={errors.product_id} onChange={(v) => setData('product_id', v)} />
                            </Field>
                            <div className="au-field">
                                <label className="au-label">Nomor Urut Aksesoris (opsional)</label>
                                {info ? (
                                    <div className="au-pf-hint">
                                        <Icon path={INFO} />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <p>
                                                Entri terakhir {info.series ? 'seri ' + info.series : '(tanpa seri)'}{' '}
                                                <span className="au-pf-hint-date">{info.date}</span>:
                                            </p>
                                            <p className="au-pf-hint-text">{info.serial_number}</p>
                                            <div className="au-pf-hint-btns">
                                                <button type="button" onClick={() => setAwal(String(info.last_number + 1))}>
                                                    ↳ Lanjutkan dari {info.last_number + 1}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                ) : null}
                                <div className="au-pf-noawal">
                                    <div>
                                        <span className="au-pf-cap">Nomor Awal</span>
                                        <input type="number" min={1} className="au-input" placeholder="Contoh: 15" value={awal} onChange={(e) => setAwal(e.target.value)} />
                                    </div>
                                    <div className="au-pf-arrow">→</div>
                                    <div>
                                        <span className="au-pf-cap">Preview</span>
                                        <div className="au-pf-preview">{preview}</div>
                                    </div>
                                </div>
                                {errors.serial_number ? <span className="au-error">{errors.serial_number}</span> : null}
                            </div>
                        </div>
                        <Field label={'Keterangan (opsional) · ' + data.keterangan.length + '/' + BATAS_KET + ' karakter'} error={errors.keterangan}>
                            <Textarea rows={2} maxLength={BATAS_KET} value={data.keterangan} error={errors.keterangan} onChange={(e) => setData('keterangan', e.target.value)} />
                        </Field>
                    </div>
                </div>
                <div className="au-modal-foot au-modal-foot--rata">
                    <Btn tone="ghost" onClick={onClose} disabled={processing}>
                        Batal
                    </Btn>
                    <Btn type="submit" icon={ICON.save} disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Simpan'}
                    </Btn>
                </div>
            </form>
        </div>
    );
}

function ModalEdit({ row, onClose, units, productGroups, accessoryNames }) {
    const { data, setData, put, processing, errors } = useForm({
        name: row.name || '',
        accessory_date: row.dateInput || today(),
        qty: row.qty || 1,
        unit: row.unit || 'pcs',
        product_id: row.productId ? String(row.productId) : '',
        serial_number: row.serial || '',
        keterangan: row.keterangan || '',
    });

    function simpan(e) {
        e.preventDefault();
        put(row.updateUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <div className="au-modal-backdrop" onClick={() => !processing && onClose()}>
            <form className="au-modal au-modal--wide" onClick={(e) => e.stopPropagation()} onSubmit={simpan}>
                <div className="au-modal-banner au-modal-banner--amber">
                    <div>
                        <h3>Edit Aksesoris Keluar</h3>
                        <p>Perbarui catatan aksesoris yang keluar dari produksi</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup">
                        <Icon path={ICON.close} />
                    </button>
                </div>
                <div className="au-modal-body">
                    <div className="au-form">
                        <Field label="Nama / Jenis Aksesoris" required error={errors.name}>
                            <PilihKetik value={data.name} options={accessoryNames} error={errors.name}
                                placeholder="Pilih atau ketik nama aksesoris baru..." onChange={(v) => setData('name', v)} />
                        </Field>
                        <div className="au-form-grid au-form-grid-2">
                            <Field label="Tanggal" required error={errors.accessory_date}>
                                <Input type="date" value={data.accessory_date} error={errors.accessory_date} onChange={(e) => setData('accessory_date', e.target.value)} />
                            </Field>
                            <div className="au-form-grid au-form-grid-2 au-aks-jumlah">
                                <Field label="Jumlah" required error={errors.qty}>
                                    <Input type="number" min={1} max={99999} value={data.qty} error={errors.qty} onChange={(e) => setData('qty', e.target.value)} />
                                </Field>
                                <Field label="Satuan" error={errors.unit}>
                                    <Select value={data.unit} options={units.map((u) => ({ value: u, label: u }))} onChange={(e) => setData('unit', e.target.value)} />
                                </Field>
                            </div>
                        </div>
                        <div className="au-form-grid au-form-grid-2">
                            <Field label="Seri Produk Terkait (opsional)" error={errors.product_id}>
                                <SelectProduk value={data.product_id} groups={productGroups} error={errors.product_id} onChange={(v) => setData('product_id', v)} />
                            </Field>
                            <Field label="Nomor Urut Aksesoris (opsional)" error={errors.serial_number}>
                                <Input value={data.serial_number} maxLength={150} placeholder="Contoh: NO.015-017" error={errors.serial_number} onChange={(e) => setData('serial_number', e.target.value)} />
                            </Field>
                        </div>
                        <Field label={'Keterangan (opsional) · ' + data.keterangan.length + '/' + BATAS_KET + ' karakter'} error={errors.keterangan}>
                            <Textarea rows={2} maxLength={BATAS_KET} value={data.keterangan} error={errors.keterangan} onChange={(e) => setData('keterangan', e.target.value)} />
                        </Field>
                    </div>
                </div>
                <div className="au-modal-foot au-modal-foot--rata">
                    <Btn tone="ghost" onClick={onClose} disabled={processing}>
                        Batal
                    </Btn>
                    <Btn type="submit" icon={ICON.save} disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </Btn>
                </div>
            </form>
        </div>
    );
}

/**
 * Kelola kategori (nama/jenis) aksesoris: ubah nama — semua data ikut berganti, nama yang sudah ada
 * otomatis digabung — dan hapus kategori yang tidak dipakai data apa pun.
 */
function ModalKategori({ categories, can, onClose }) {
    const [ubah, setUbah] = useState(null);
    const [galat, setGalat] = useState('');
    const [busy, setBusy] = useState(false);

    function simpan(e) {
        e.preventDefault();
        setBusy(true);
        router.patch(ubah.url, { name: ubah.name }, {
            preserveScroll: true,
            onSuccess: () => { setUbah(null); setGalat(''); },
            onError: (er) => setGalat(er.name || 'Gagal menyimpan.'),
            onFinish: () => setBusy(false),
        });
    }

    async function hapus(k) {
        if (!(await konfirmasi("Hapus kategori '" + k.name + "'?", { title: 'Hapus Kategori', okText: 'Hapus' }))) return;
        router.delete(k.deleteUrl, { preserveScroll: true, onSuccess: () => setGalat(''), onError: (er) => setGalat(er.name || 'Gagal menghapus.') });
    }

    return (
        <div className="au-modal-backdrop" onClick={() => !busy && onClose()}>
            <div className="au-modal au-modal--wide au-aks-kat" onClick={(e) => e.stopPropagation()}>
                <div className="au-modal-banner">
                    <div>
                        <h3>Kategori Aksesoris</h3>
                        <p>Ubah nama atau hapus kategori yang tidak dipakai</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup">
                        <Icon path={ICON.close} />
                    </button>
                </div>
                <div className="au-modal-body">
                    {galat ? <p className="au-aks-kat-galat">{galat}</p> : null}
                    {categories.length ? (
                        categories.map((k) =>
                            ubah && ubah.id === k.id ? (
                                <form className="au-aks-kat-baris" key={k.id} onSubmit={simpan}>
                                    <Input autoFocus value={ubah.name} maxLength={150} onChange={(e) => setUbah({ ...ubah, name: e.target.value })} />
                                    <Btn type="submit" disabled={busy || !ubah.name.trim()}>Simpan</Btn>
                                    <Btn tone="ghost" onClick={() => setUbah(null)} disabled={busy}>Batal</Btn>
                                </form>
                            ) : (
                                <div className="au-aks-kat-baris" key={k.id}>
                                    <b>{k.name}</b>
                                    <span className="au-aks-kat-jumlah">{k.count} data</span>
                                    <span className="au-actions">
                                        {can.edit ? <IconBtn icon={ICON.edit} title="Ubah nama" onClick={() => { setGalat(''); setUbah({ id: k.id, url: k.updateUrl, name: k.name }); }} /> : null}
                                        {can.delete ? <IconBtn icon={ICON.trash} title="Hapus" onClick={() => hapus(k)} /> : null}
                                    </span>
                                </div>
                            ),
                        )
                    ) : (
                        <p className="au-aks-kosong">Belum ada kategori. Kategori dibuat otomatis saat menambah aksesoris.</p>
                    )}
                    <p className="au-aks-kat-catatan">Mengubah nama mengganti nama di semua data aksesoris. Bila nama baru sudah ada, kedua kategori digabung.</p>
                </div>
                <div className="au-modal-foot">
                    <Btn tone="ghost" onClick={onClose}>Tutup</Btn>
                </div>
            </div>
        </div>
    );
}
