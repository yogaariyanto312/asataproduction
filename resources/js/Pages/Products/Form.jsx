import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, Card, Check, Field, ICON, Icon, Input, Select, Textarea } from '../../Components/Ui';
import { PALET, gayaIkon } from './warna';

const CUBE = 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4';

const TIPE = [
    { value: 'regular', label: 'Regular' },
    { value: 'channel', label: 'Channel (UP/BT)' },
];

/**
 * Form produk versi ringkas: yang wajib & sering dipakai di atas, ukuran dan
 * keterangan dilipat di "Detail tambahan". Satuan dipilih dari tombol cepat.
 */
export default function ProductForm({ mode, action, indexUrl, categories, maxYear, product, prefill = {}, units = [], names = [] }) {
    const isEdit = mode === 'edit';

    const { data, setData, post, put, processing, errors } = useForm({
        category_id: product?.category_id ?? prefill.category_id ?? '',
        type: product?.type ?? prefill.type ?? 'regular',
        name: product?.name ?? prefill.name ?? '',
        urutan: product?.urutan ?? prefill.urutan ?? '',
        warna_ikon: product?.warna_ikon || '',
        warna_teks: product?.warna_teks || '',
        series: product?.series || '',
        kva: product?.kva || '',
        tahun: product?.tahun || (isEdit ? '' : new Date().getFullYear()),
        panjang: product?.panjang || '',
        lebar: product?.lebar || '',
        unit: product?.unit || 'unit',
        description: product?.description || '',
        is_active: product ? product.is_active : true,
    });

    const adaDetail = !!(data.panjang || data.lebar || data.description || errors.panjang || errors.lebar || errors.description);
    const [detail, setDetail] = useState(adaDetail);
    const satuanLain = !units.includes(String(data.unit).toLowerCase());
    const [isiLain, setIsiLain] = useState(satuanLain && !!data.unit);

    function submit(e) {
        e.preventDefault();
        if (isEdit) put(action);
        else post(action);
    }

    const title = isEdit ? 'Edit Produk' : prefill.name ? 'Tambah Varian' : 'Tambah Produk';

    return (
        <AppLayout title={title} subtitle={isEdit ? product.name : prefill.name || 'Produk baru'}>
            <Card
                title={title}
                action={
                    <Btn as="a" href={indexUrl} tone="ghost" sm icon={ICON.back}>
                        Kembali
                    </Btn>
                }
            >
                <form className="au-form au-pf" onSubmit={submit}>
                    <div className="au-pf-grid au-pf-grid--utama">
                        <Field label="Nama produk" required error={errors.name}>
                            <Input
                                value={data.name}
                                error={errors.name}
                                autoFocus={!prefill.name}
                                maxLength={150}
                                list="au-pf-nama"
                                placeholder="Contoh: Channel-PLN"
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            <datalist id="au-pf-nama">
                                {names.map((n) => (
                                    <option key={n} value={n} />
                                ))}
                            </datalist>
                        </Field>

                        <Field label="Kategori" required error={errors.category_id}>
                            <Select
                                value={data.category_id}
                                error={errors.category_id}
                                placeholder="— Pilih kategori —"
                                options={categories}
                                onChange={(e) => setData('category_id', e.target.value)}
                            />
                        </Field>

                        <Field label="Urutan" error={errors.urutan} hint="1 = paling kiri">
                            <Input
                                type="number"
                                min={1}
                                max={999}
                                value={data.urutan}
                                error={errors.urutan}
                                placeholder="—"
                                onChange={(e) => setData('urutan', e.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="au-pf-grid au-pf-grid--3">
                        <Field label="Seri" error={errors.series}>
                            <Input
                                value={data.series}
                                error={errors.series}
                                autoFocus={!!prefill.name}
                                maxLength={100}
                                placeholder="Opsional"
                                onChange={(e) => setData('series', e.target.value)}
                            />
                        </Field>
                        <Field label="kVA" error={errors.kva}>
                            <Input value={data.kva} error={errors.kva} maxLength={20} placeholder="Opsional" onChange={(e) => setData('kva', e.target.value)} />
                        </Field>
                        <Field label="Tahun" error={errors.tahun} hint={'2025 – ' + maxYear}>
                            <Input
                                type="number"
                                min={2025}
                                max={maxYear}
                                value={data.tahun}
                                error={errors.tahun}
                                onChange={(e) => setData('tahun', e.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="au-pf-grid au-pf-grid--2">
                        <Field label="Tipe" required error={errors.type}>
                            <div className="au-pf-seg" role="radiogroup">
                                {TIPE.map((t) => (
                                    <button
                                        key={t.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={data.type === t.value}
                                        className={data.type === t.value ? 'is-aktif' : ''}
                                        onClick={() => setData('type', t.value)}
                                    >
                                        {t.label}
                                    </button>
                                ))}
                            </div>
                        </Field>

                        <Field label="Satuan" required error={errors.unit}>
                            <div className="au-pf-chips">
                                {units.map((u) => (
                                    <button
                                        key={u}
                                        type="button"
                                        className={!isiLain && String(data.unit).toLowerCase() === u ? 'is-aktif' : ''}
                                        onClick={() => {
                                            setIsiLain(false);
                                            setData('unit', u);
                                        }}
                                    >
                                        {u}
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    className={isiLain ? 'is-aktif' : ''}
                                    onClick={() => {
                                        setIsiLain(true);
                                        setData('unit', '');
                                    }}
                                >
                                    + Lainnya
                                </button>
                            </div>
                            {isiLain ? (
                                <Input
                                    className="au-pf-lain"
                                    value={data.unit}
                                    error={errors.unit}
                                    maxLength={20}
                                    autoFocus
                                    placeholder="Tulis satuan, mis. batang"
                                    onChange={(e) => setData('unit', e.target.value)}
                                />
                            ) : null}
                        </Field>
                    </div>

                    <div className="au-pf-warna">
                        <div className="au-pf-warna-pilih">
                            <PilihWarna label="Warna ikon" value={data.warna_ikon} error={errors.warna_ikon} onChange={(v) => setData('warna_ikon', v)} />
                            <PilihWarna label="Warna teks" value={data.warna_teks} error={errors.warna_teks} onChange={(v) => setData('warna_teks', v)} />
                        </div>
                        <div className="au-pf-pratinjau" aria-label="Pratinjau kartu">
                            <span className={'au-groupcard-mark' + (data.type === 'channel' ? ' au-groupcard-mark--blue' : '')} style={gayaIkon(data.warna_ikon)}>
                                <Icon path={CUBE} />
                            </span>
                            <div style={{ minWidth: 0 }}>
                                <h3 className="au-groupcard-title" style={data.warna_teks ? { color: data.warna_teks } : undefined}>
                                    {data.name || 'Nama produk'}
                                </h3>
                                <p className="au-groupcard-sub">Pratinjau kartu · berlaku untuk semua varian</p>
                            </div>
                        </div>
                    </div>

                    <button type="button" className={'au-pf-lipat' + (detail ? ' is-buka' : '')} onClick={() => setDetail((d) => !d)} aria-expanded={detail}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M9 5l7 7-7 7" />
                        </svg>
                        Detail tambahan <small>ukuran &amp; keterangan · opsional</small>
                    </button>

                    {detail ? (
                        <div className="au-pf-detail">
                            <div className="au-pf-grid au-pf-grid--2">
                                <Field label="Panjang" error={errors.panjang}>
                                    <Input value={data.panjang} error={errors.panjang} maxLength={50} onChange={(e) => setData('panjang', e.target.value)} />
                                </Field>
                                <Field label="Lebar" error={errors.lebar}>
                                    <Input value={data.lebar} error={errors.lebar} maxLength={50} onChange={(e) => setData('lebar', e.target.value)} />
                                </Field>
                            </div>
                            <Field label="Keterangan" error={errors.description}>
                                <Textarea rows={3} value={data.description} error={errors.description} maxLength={500} onChange={(e) => setData('description', e.target.value)} />
                            </Field>
                        </div>
                    ) : null}

                    <div className="au-pf-bawah">
                        <Check label="Produk aktif" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        <div className="au-form-actions">
                            <Btn as="a" href={indexUrl} tone="ghost">
                                Batal
                            </Btn>
                            <Btn type="submit" icon={ICON.save} disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Btn>
                        </div>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}

/** Palet warna cepat + pemilih bebas; "Bawaan" mengosongkan warna. */
function PilihWarna({ label, value, error, onChange }) {
    return (
        <Field label={label} error={error}>
            <div className="au-pf-swatch">
                <button type="button" className={'is-bawaan' + (!value ? ' is-aktif' : '')} title="Warna bawaan" onClick={() => onChange('')}>
                    ∅
                </button>
                {PALET.map((w) => (
                    <button
                        key={w}
                        type="button"
                        title={w}
                        aria-label={w}
                        className={value.toLowerCase() === w ? 'is-aktif' : ''}
                        style={{ background: w }}
                        onClick={() => onChange(w)}
                    />
                ))}
                <label className={'au-pf-swatch-bebas' + (value && !PALET.includes(value.toLowerCase()) ? ' is-aktif' : '')} title="Pilih warna lain" style={value && !PALET.includes(value.toLowerCase()) ? { background: value } : undefined}>
                    <input type="color" value={value || '#3b82f6'} onChange={(e) => onChange(e.target.value)} />
                    +
                </label>
            </div>
        </Field>
    );
}
