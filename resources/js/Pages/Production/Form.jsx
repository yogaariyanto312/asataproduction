import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, Field, ICON, Icon, Input, Select, Textarea } from '../../Components/Ui';

/**
 * Form Input / Edit Produksi — perilaku dan perhitungannya disalin dari
 * Production-QC-Logging-System (resources/views/production/create & edit):
 *
 *  - Channel: isi UP & BT, Total = (UP + BT) ÷ 2. Produk lain: Total manual.
 *  - Nomor urut dibangkitkan dari "Nomor Awal" + jumlah → NO.001-010
 *    (UP NO.… / BT NO.… untuk channel), padding minimal 3 digit.
 *  - Input: nomor terakhir produk tampil sebagai "Entri terakhir" dan Nomor
 *    Awal terisi otomatis dengan nomor sesudahnya. Simpan tanpa pindah
 *    halaman; tanggal & produk tetap, isian lain dikosongkan.
 *  - Edit: Nomor Awal diisi dari nomor tersimpan; dikosongkan = nomor lama
 *    dipertahankan; Total 0 = nomor urut dikosongkan.
 *  - Isian Seri & KVA manual hanya untuk produk placeholder.
 */

function pad(n) {
    const s = String(Math.round(n));
    return s.length < 3 ? s.padStart(3, '0') : s;
}

// "NO.098-100" → 100, "UP NO.435-437" → 437
function parseEndNum(str) {
    const m = str.match(/(\d+)\s*$/);
    return m ? parseInt(m[1], 10) : null;
}

// "UP NO.477-482" → 477
function startOf(line) {
    const m = line.match(/NO\.\s*0*(\d+)/i);
    return m ? parseInt(m[1], 10) : null;
}

const fmtTotal = (t) => (t % 1 === 0 ? t.toLocaleString('id-ID') : t.toFixed(1));

const ALERT = 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
const INFO = 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
const TAG = 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z';
const CHEV = 'M19 9l-7 7-7-7';

export default function ProductionForm({
    mode,
    action,
    indexUrl,
    lastSerialUrl,
    today,
    products,
    departments,
    rejectCategories,
    log,
}) {
    const isEdit = mode === 'edit';
    const notesAwal = isEdit ? log?.notes || '' : '';

    const [data, setAll] = useState(() => ({
        product_id: log?.product_id ? String(log.product_id) : '',
        production_date: log?.production_date || today,
        operator_name: log?.operator_name || '',
        department: '',
        up: isEdit ? String(log?.up_qty ?? 0) : '0',
        bt: isEdit ? String(log?.bt_qty ?? 0) : '0',
        total: isEdit ? String(log?.total_qty ?? 0) : '0',
        manual_series: log?.manual_series || '',
        manual_kva: log?.manual_kva || '',
        notes: notesAwal,
        keterangan: log?.keterangan || '',
        reject_qty: String(log?.reject_qty ?? 0),
        reject_category: log?.reject_category || '',
        reject_notes: log?.reject_notes || '',
    }));
    const set = (key, value) => setAll((d) => ({ ...d, [key]: value }));

    // Nomor Awal. Mode edit: diisi dari nomor tersimpan.
    const [start, setStart] = useState(() => {
        const s = { regular: '', up: '', bt: '' };
        if (!isEdit || !notesAwal.trim()) return s;
        const lines = notesAwal.split('\n').map((l) => l.trim()).filter(Boolean);
        const up = lines.find((l) => /UP/i.test(l));
        const bt = lines.find((l) => /BT/i.test(l));
        const reg = lines.find((l) => !/UP|BT/i.test(l));
        if (up && startOf(up)) s.up = String(startOf(up));
        if (bt && startOf(bt)) s.bt = String(startOf(bt));
        if (reg && startOf(reg)) s.regular = String(startOf(reg));
        return s;
    });
    const [filled, setFilled] = useState({});
    const [hint, setHint] = useState(null);
    const [savedVisible, setSavedVisible] = useState(true);
    const [rejectOpen, setRejectOpen] = useState(Number(log?.reject_qty) > 0);
    const [search, setSearch] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [toasts, setToasts] = useState([]);

    const selected = useMemo(
        () => products.find((p) => String(p.value) === String(data.product_id)) || null,
        [products, data.product_id],
    );
    const isChannel = !!selected && selected.type === 'channel';
    const isManual = !!selected && selected.manual;

    const up = parseInt(data.up, 10) || 0;
    const bt = parseInt(data.bt, 10) || 0;
    const channelTotal = (up + bt) / 2;

    // ── Nomor urut: generateRegular / generateChannel versi Blade ────────────
    useEffect(() => {
        if (isChannel) {
            const su = parseInt(start.up, 10);
            const sb = parseInt(start.bt, 10);
            const lines = [];
            if (su && up > 0) lines.push(`UP NO.${pad(su)}-${pad(su + up - 1)}`);
            if (sb && bt > 0) lines.push(`BT NO.${pad(sb)}-${pad(sb + bt - 1)}`);

            if (!isEdit) {
                set('notes', lines.join('\n'));
                return;
            }
            if (up + bt <= 0) {
                set('notes', '');
                setSavedVisible(false);
                return;
            }
            setSavedVisible(true);
            setAll((d) => {
                let notes = d.notes || notesAwal;
                if (lines.length) notes = lines.join('\n');
                return { ...d, notes };
            });
            return;
        }

        const s = parseInt(start.regular, 10);
        const qty = Math.ceil(parseFloat(data.total) || 0);
        if (!isEdit) {
            set('notes', s && qty > 0 ? `NO.${pad(s)}-${pad(s + qty - 1)}` : '');
            return;
        }
        if (qty <= 0) {
            set('notes', '');
            setSavedVisible(false);
            return;
        }
        setSavedVisible(true);
        setAll((d) => {
            let notes = d.notes || notesAwal;
            if (s) notes = `NO.${pad(s)}-${pad(s + qty - 1)}`;
            return { ...d, notes };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isChannel, start, up, bt, data.total]);

    const previewRegular = (() => {
        const s = parseInt(start.regular, 10);
        const qty = Math.ceil(parseFloat(data.total) || 0);
        if (isEdit && qty <= 0) return '— (Total 0: nomor urut dikosongkan)';
        if (!s) return '—';
        if (qty <= 0) return 'Isi total unit →';
        return `NO.${pad(s)}-${pad(s + qty - 1)}`;
    })();
    const su = parseInt(start.up, 10);
    const sb = parseInt(start.bt, 10);
    const previewUp = su && up > 0 ? `UP NO.${pad(su)}-${pad(su + up - 1)}` : '—';
    const previewBt = sb && bt > 0 ? `BT NO.${pad(sb)}-${pad(sb + bt - 1)}` : '—';

    // ── Entri terakhir (khusus Input) ────────────────────────────────────────
    const [hintTick, setHintTick] = useState(0);

    function flash(key) {
        setFilled((f) => ({ ...f, [key]: Date.now() }));
    }

    useEffect(() => {
        if (isEdit) return undefined;
        setHint(null);
        if (!selected || isManual || !lastSerialUrl) return undefined;

        let cancelled = false;
        fetch(`${lastSerialUrl}?product_id=${selected.value}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((res) => {
                if (cancelled || !res || !res.notes) return;
                const lines = res.notes.split('\n').map((l) => l.trim()).filter(Boolean);
                const next = (line) => {
                    if (!line) return null;
                    const end = parseEndNum(line);
                    return end !== null ? end + 1 : null;
                };
                const h = isChannel
                    ? {
                          date: res.date,
                          text: lines.join('  |  '),
                          up: next(lines.find((l) => /UP/i.test(l))),
                          bt: next(lines.find((l) => /BT/i.test(l))),
                      }
                    : { date: res.date, text: res.notes, regular: next(res.notes) };
                setHint(h);

                // Isi otomatis Nomor Awal yang masih kosong.
                setStart((s) => {
                    const n = { ...s };
                    ['regular', 'up', 'bt'].forEach((k) => {
                        if (h[k] && !s[k]) {
                            n[k] = String(h[k]);
                            flash(k);
                        }
                    });
                    return n;
                });
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected?.value, hintTick]);

    function pickProduct(value) {
        set('product_id', value);
        // Input: kosongkan Nomor Awal agar diisi ulang dari produk baru.
        if (!isEdit) setStart({ regular: '', up: '', bt: '' });
    }

    function lanjutDari(key) {
        setStart((s) => ({ ...s, [key]: String(hint[key]) }));
        const el = document.getElementById('no_awal_' + key);
        if (el) el.focus();
    }

    // ── Gulir mouse untuk naik/turunkan angka (seperti versi Blade) ──────────
    const formRef = useRef(null);
    useEffect(() => {
        const root = formRef.current;
        if (!root) return undefined;
        function onWheel(e) {
            const el = e.target.closest('input[data-wheel]');
            if (!el) return;
            e.preventDefault();
            const step = parseFloat(el.step) || 1;
            const min = el.min !== '' ? parseFloat(el.min) : -Infinity;
            const max = el.max !== '' ? parseFloat(el.max) : Infinity;
            const cur = parseFloat(el.value) || 0;
            const val = String(Math.min(max, Math.max(min, cur + (e.deltaY < 0 ? step : -step))));
            const key = el.dataset.wheel;
            if (key.startsWith('start.')) {
                const k = key.slice(6);
                setStart((s) => ({ ...s, [k]: val }));
            } else {
                set(key, val);
            }
        }
        root.addEventListener('wheel', onWheel, { passive: false });
        return () => root.removeEventListener('wheel', onWheel);
    }, []);

    // ── Simpan ───────────────────────────────────────────────────────────────
    function payload() {
        const p = {
            product_id: data.product_id,
            production_date: data.production_date,
            notes: data.notes,
            manual_series: data.manual_series,
            manual_kva: data.manual_kva,
            keterangan: data.keterangan,
            reject_qty: data.reject_qty,
            reject_category: data.reject_category,
            reject_notes: data.reject_notes,
        };
        if (isEdit) p.operator_name = data.operator_name;
        if (!isEdit && departments && departments.length) p.department = data.department;
        if (isChannel) {
            p.up_qty = data.up;
            p.bt_qty = data.bt;
            p.total_qty = channelTotal;
        } else {
            p.total_qty = data.total;
        }
        return p;
    }

    function toast(text, type = 'ok') {
        const id = Date.now() + Math.random();
        setToasts((t) => [...t, { id, text, type }]);
        setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 3500);
    }

    function resetForNextEntry() {
        setAll((d) => ({
            ...d,
            up: '',
            bt: '',
            total: '',
            notes: '',
            keterangan: '',
            reject_qty: '0',
            reject_notes: '',
            manual_series: '',
            manual_kva: '',
        }));
        setStart({ regular: '', up: '', bt: '' });
        setHintTick((t) => t + 1);
    }

    function submit(e) {
        e.preventDefault();
        if (processing) return;
        if (isManual && (!data.manual_series.trim() || !data.manual_kva.trim())) {
            setErrors({
                manual_series: data.manual_series.trim() ? undefined : 'Nomor seri wajib diisi.',
                manual_kva: data.manual_kva.trim() ? undefined : 'KVA wajib diisi.',
            });
            return;
        }
        setProcessing(true);
        setErrors({});

        if (isEdit) {
            router.put(action, payload(), {
                onError: (errs) => setErrors(errs),
                onFinish: () => setProcessing(false),
            });
            return;
        }

        axios
            .post(action, payload(), { headers: { Accept: 'application/json' } })
            .then((res) => {
                toast(res.data?.message || 'Data produksi berhasil disimpan.');
                resetForNextEntry();
                try {
                    localStorage.setItem('prod:changed', Date.now().toString());
                } catch (_) {
                    /* abaikan */
                }
            })
            .catch((err) => {
                const res = err.response;
                if (res && res.status === 422) {
                    const errs = {};
                    Object.entries(res.data?.errors || {}).forEach(([k, v]) => {
                        errs[k] = Array.isArray(v) ? v[0] : v;
                    });
                    setErrors(errs);
                    toast(Object.values(errs)[0] || 'Periksa kembali isian form.', 'warn');
                } else if (res) {
                    toast('Gagal menyimpan. Coba lagi.', 'error');
                } else {
                    toast('Koneksi bermasalah. Data belum tersimpan.', 'error');
                }
            })
            .finally(() => setProcessing(false));
    }

    // ── Dropdown produk: dikelompokkan per nama, bisa dicari ─────────────────
    const groups = useMemo(() => {
        const q = search.trim().toLowerCase();
        const map = new Map();
        products.forEach((p) => {
            if (q && !(p.label + ' ' + p.group).toLowerCase().includes(q) && String(p.value) !== String(data.product_id)) {
                return;
            }
            if (!map.has(p.group)) map.set(p.group, []);
            map.get(p.group).push(p);
        });
        return [...map.entries()];
    }, [products, search, data.product_id]);

    const qtyErr = errors.total_qty || errors.up_qty || errors.bt_qty;
    const fillCls = (k) => (filled[k] ? ' au-pf-filled' : '');

    const subtitle = isEdit
        ? `${selected?.group || ''} — ${(log?.production_date || '').split('-').reverse().join('/')}`
        : 'Isi data produksi dengan lengkap';

    return (
        <AppLayout
            title={isEdit ? 'Edit Data Produksi' : 'Input Produksi'}
            subtitle={isEdit ? 'Ubah data produksi yang sudah diinput' : 'Tambah data produksi harian'}
        >
            <div className="au-formcard">
                <div className="au-formcard-head">
                    <h2>{isEdit ? 'Edit Data Produksi' : 'Form Input Produksi Harian'}</h2>
                    <p>{subtitle}</p>
                </div>

                <div className="au-formcard-body">
                    <form onSubmit={submit} ref={formRef}>
                        {!isEdit && departments && departments.length ? (
                            <div className="au-notice">
                                <label className="au-notice-label">
                                    Departemen tujuan data (khusus developer)
                                </label>
                                <Select
                                    value={data.department}
                                    error={errors.department}
                                    placeholder="— Pilih departemen —"
                                    options={departments}
                                    onChange={(e) => set('department', e.target.value)}
                                />
                                {errors.department ? <span className="au-error">{errors.department}</span> : null}
                            </div>
                        ) : null}

                        <div className="au-formcols">
                            {/* Kiri: tanggal, (operator), jumlah */}
                            <div className="au-form">
                                <Field label="Tanggal Produksi" required error={errors.production_date}>
                                    <Input
                                        type="date"
                                        max={today}
                                        value={data.production_date}
                                        error={errors.production_date}
                                        onChange={(e) => set('production_date', e.target.value)}
                                    />
                                </Field>

                                {isEdit ? (
                                    <Field label="Nama Operator" error={errors.operator_name}>
                                        <Input
                                            value={data.operator_name}
                                            error={errors.operator_name}
                                            maxLength={150}
                                            placeholder="Nama operator yang bertugas..."
                                            onChange={(e) => set('operator_name', e.target.value)}
                                        />
                                    </Field>
                                ) : null}

                                {isChannel ? (
                                    <div>
                                        <span className="au-pf-section-label">
                                            Jumlah Channel <span className="au-req">*</span>
                                        </span>
                                        <div className="au-qty-grid">
                                            <div>
                                                <span className="au-qty-cap">Channel UP</span>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    max={9999}
                                                    data-wheel="up"
                                                    className="au-input au-qty-input"
                                                    value={data.up}
                                                    onChange={(e) => set('up', e.target.value)}
                                                />
                                            </div>
                                            <div>
                                                <span className="au-qty-cap">Channel BT</span>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    max={9999}
                                                    data-wheel="bt"
                                                    className="au-input au-qty-input"
                                                    value={data.bt}
                                                    onChange={(e) => set('bt', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="au-pf-total">
                                            <small>Total otomatis: (UP + BT) ÷ 2</small>
                                            <b>{fmtTotal(channelTotal)}</b>
                                        </div>
                                        {qtyErr ? <span className="au-error">{qtyErr}</span> : null}
                                    </div>
                                ) : (
                                    <div className="au-qtybox au-qtybox--accent">
                                        <span className="au-qtybox-label au-qtybox-label--accent">
                                            Total Unit <span className="au-req">*</span>{' '}
                                            <small style={{ fontWeight: 500 }}>(isi manual)</small>
                                        </span>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={99999}
                                            step="any"
                                            data-wheel="total"
                                            className={'au-input au-pf-bigtotal' + (errors.total_qty ? ' has-error' : '')}
                                            value={data.total}
                                            onChange={(e) => set('total', e.target.value)}
                                        />
                                        {errors.total_qty ? <span className="au-error">{errors.total_qty}</span> : null}
                                    </div>
                                )}
                            </div>

                            {/* Kanan: produk, seri manual, nomor urut */}
                            <div className="au-form">
                                <div className="au-field">
                                    <label className="au-label">
                                        Produk<span className="au-req"> *</span>
                                    </label>
                                    <input
                                        type="search"
                                        className="au-input au-pf-search"
                                        placeholder="Cari produk / seri..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                    <select
                                        className={'au-select' + (errors.product_id ? ' has-error' : '')}
                                        value={data.product_id}
                                        onChange={(e) => pickProduct(e.target.value)}
                                    >
                                        <option value="">-- Pilih Produk --</option>
                                        {groups.map(([name, items]) => (
                                            <optgroup label={name} key={name}>
                                                {items.map((p) => (
                                                    <option value={p.value} key={p.value}>
                                                        {p.label}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        ))}
                                    </select>
                                    {errors.product_id ? <span className="au-error">{errors.product_id}</span> : null}
                                </div>

                                {isManual ? (
                                    <div className="au-pf-manual">
                                        <p>
                                            <Icon path={TAG} /> Identitas Produk <small>(wajib diisi)</small>
                                        </p>
                                        <div className="au-qty-grid">
                                            <Field label="Nomor Seri" required error={errors.manual_series}>
                                                <Input
                                                    value={data.manual_series}
                                                    error={errors.manual_series}
                                                    maxLength={100}
                                                    placeholder="Contoh: A-1234 / 2026.001"
                                                    onChange={(e) => set('manual_series', e.target.value)}
                                                />
                                            </Field>
                                            <Field label="KVA" required error={errors.manual_kva}>
                                                <Input
                                                    value={data.manual_kva}
                                                    error={errors.manual_kva}
                                                    maxLength={50}
                                                    placeholder="Contoh: 100 / 2x50 / 250"
                                                    onChange={(e) => set('manual_kva', e.target.value)}
                                                />
                                            </Field>
                                        </div>
                                    </div>
                                ) : null}

                                {!isChannel ? (
                                    <div>
                                        <span className="au-pf-section-label">Nomor Urut</span>
                                        {hint ? (
                                            <div className="au-pf-hint">
                                                <Icon path={INFO} />
                                                <div style={{ flex: 1, minWidth: 0 }}>
                                                    <p>
                                                        Entri terakhir <span className="au-pf-hint-date">{hint.date}</span>:
                                                    </p>
                                                    <p className="au-pf-hint-text">{hint.text}</p>
                                                    {hint.regular ? (
                                                        <div className="au-pf-hint-btns">
                                                            <button type="button" onClick={() => lanjutDari('regular')}>
                                                                ↳ Lanjutkan dari {hint.regular}
                                                            </button>
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ) : null}
                                        <div className="au-pf-noawal">
                                            <div>
                                                <span className="au-pf-cap">Nomor Awal</span>
                                                <input
                                                    id="no_awal_regular"
                                                    type="number"
                                                    min={1}
                                                    data-wheel="start.regular"
                                                    placeholder="Contoh: 98"
                                                    className={'au-input' + fillCls('regular')}
                                                    key={'r' + (filled.regular || 0)}
                                                    value={start.regular}
                                                    onChange={(e) => setStart({ ...start, regular: e.target.value })}
                                                />
                                            </div>
                                            <div className="au-pf-arrow">→</div>
                                            <div>
                                                <span className="au-pf-cap">Preview</span>
                                                <div className="au-pf-preview">{previewRegular}</div>
                                            </div>
                                        </div>
                                    </div>
                                ) : !(isEdit && isManual) ? (
                                    <div>
                                        <span className="au-pf-section-label">Nomor Urut</span>
                                        {hint ? (
                                            <div className="au-pf-hint">
                                                <Icon path={INFO} />
                                                <div style={{ flex: 1, minWidth: 0 }}>
                                                    <p>
                                                        Entri terakhir <span className="au-pf-hint-date">{hint.date}</span>:
                                                    </p>
                                                    <p className="au-pf-hint-text">{hint.text}</p>
                                                    <div className="au-pf-hint-btns">
                                                        {hint.up ? (
                                                            <button type="button" onClick={() => lanjutDari('up')}>
                                                                ↳ Lanjut UP dari {hint.up}
                                                            </button>
                                                        ) : null}
                                                        {hint.bt ? (
                                                            <button type="button" className="is-bt" onClick={() => lanjutDari('bt')}>
                                                                ↳ Lanjut BT dari {hint.bt}
                                                            </button>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            </div>
                                        ) : null}
                                        <div className="au-qty-grid">
                                            {[
                                                ['up', 'Nomor Awal UP', 'Contoh: 435', previewUp, ''],
                                                ['bt', 'Nomor Awal BT', 'Contoh: 438', previewBt, ' is-bt'],
                                            ].map(([k, label, ph, prev, cls]) => (
                                                <div key={k}>
                                                    <span className="au-qty-cap">{label}</span>
                                                    <input
                                                        id={'no_awal_' + k}
                                                        type="number"
                                                        min={1}
                                                        data-wheel={'start.' + k}
                                                        placeholder={ph}
                                                        className={'au-input au-qty-input' + fillCls(k)}
                                                        key={k + (filled[k] || 0)}
                                                        value={start[k]}
                                                        onChange={(e) => setStart({ ...start, [k]: e.target.value })}
                                                    />
                                                    <p className={'au-pf-preview-sm' + cls}>{prev}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ) : null}

                                {isEdit && notesAwal && savedVisible ? (
                                    <div className="au-pf-saved">
                                        <small>Tersimpan saat ini:</small>
                                        <p>{notesAwal}</p>
                                    </div>
                                ) : null}
                                {errors.notes ? <span className="au-error">{errors.notes}</span> : null}
                            </div>
                        </div>

                        {/* Reject / Defect */}
                        <button type="button" className="au-pf-reject-toggle" onClick={() => setRejectOpen(!rejectOpen)}>
                            <Icon path={ALERT} />
                            <span>{rejectOpen ? 'Sembunyikan Reject' : 'Ada Reject / Defect?'}</span>
                            <span className={'au-pf-chev' + (rejectOpen ? ' is-open' : '')}>
                                <Icon path={CHEV} />
                            </span>
                        </button>
                        {rejectOpen ? (
                            <div className="au-pf-reject">
                                <div className="au-qty-grid">
                                    <Field label="Jumlah Reject" error={errors.reject_qty}>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={9999}
                                            className="au-input au-qty-input"
                                            value={data.reject_qty}
                                            error={errors.reject_qty}
                                            onChange={(e) => set('reject_qty', e.target.value)}
                                        />
                                    </Field>
                                    <Field label="Kategori Penyebab" error={errors.reject_category}>
                                        <Select
                                            value={data.reject_category}
                                            error={errors.reject_category}
                                            placeholder="-- Pilih Kategori --"
                                            options={rejectCategories}
                                            onChange={(e) => set('reject_category', e.target.value)}
                                        />
                                    </Field>
                                </div>
                                <Field label="Keterangan Reject" error={errors.reject_notes}>
                                    <Input
                                        value={data.reject_notes}
                                        error={errors.reject_notes}
                                        maxLength={300}
                                        placeholder="Deskripsi singkat penyebab reject..."
                                        onChange={(e) => set('reject_notes', e.target.value)}
                                    />
                                </Field>
                            </div>
                        ) : null}

                        <div style={{ marginTop: 20 }}>
                            <Field label="Keterangan (opsional)" error={errors.keterangan}>
                                <Textarea
                                    rows={2}
                                    value={data.keterangan}
                                    error={errors.keterangan}
                                    maxLength={500}
                                    style={{ minHeight: 66 }}
                                    placeholder="Catatan tambahan, misal: Barang dari supplier, Lupa input, dll..."
                                    onChange={(e) => set('keterangan', e.target.value)}
                                />
                            </Field>
                        </div>

                        <div className="au-pf-actions">
                            <Btn as="a" href={indexUrl} tone="ghost">
                                Batal
                            </Btn>
                            <Btn type="submit" icon={ICON.save} disabled={processing}>
                                {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Simpan Data Produksi'}
                            </Btn>
                        </div>
                    </form>
                </div>
            </div>

            {toasts.length ? (
                <div className="au-toasts">
                    {toasts.map((t) => (
                        <div
                            key={t.id}
                            className={'au-toast' + (t.type === 'ok' ? '' : ' au-toast--error')}
                            role="status"
                        >
                            <Icon path={t.type === 'ok' ? ICON.check : ICON.alert} />
                            <span>{t.text}</span>
                        </div>
                    ))}
                </div>
            ) : null}
        </AppLayout>
    );
}
