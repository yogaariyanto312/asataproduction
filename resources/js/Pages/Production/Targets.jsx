import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Icon, ICON } from '../../Components/Ui';
import PdfViewer from '../../Components/PdfViewer';
import PilihProduk from '../../Components/PilihProduk';
import { csrf } from '../../csrf';
import { konfirmasi } from '../../dialog';

const P = {
    penuh: 'M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4',
    ganti: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
    gambar: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    cari: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
};
const fmt = (n) => Math.round(Number(n) || 0).toLocaleString('id-ID');
const mb = (b) => (b / 1048576).toFixed(1) + ' MB';
// Warna progres sama dengan referensi: tercapai hijau, ≥70% amber, selain itu biru.
const nada = (pct, done) => (done || pct >= 100 ? 'is-hijau' : pct >= 70 ? 'is-amber' : 'is-biru');

/**
 * Target Produksi — mengikuti Production-QC-Logging-System: Foto Jadwal minggu
 * ini di atas, lalu kolom kiri "Set Target" (lengket) dan kolom kanan 3 kartu
 * ringkasan + daftar progres per produk, ditambah tombol Live (60 detik).
 */
export default function ProductionTargets(props) {
    const { targets, totals, canEdit, canDelete, liveUrl } = props;
    const [live, setLive] = useState(null);
    const [updatedAt, setUpdatedAt] = useState(null);

    const fetchLive = useCallback(async () => {
        try {
            const r = await fetch(liveUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            setLive(d);
            setUpdatedAt(d.updated_at);
        } catch (_) {
            /* diamkan */
        }
    }, [liveUrl]);

    // Data dari server berganti (simpan/hapus target) → buang data live lama.
    useEffect(() => setLive(null), [targets]);

    const totalActual = live ? live.total_actual : totals.actual;
    const pctAll = live ? live.overall_pct : totals.pct;

    return (
        <AppLayout title="Target Produksi" subtitle="Set & pantau target produksi per produk">
            <div className="au-tg">
                <FotoJadwal {...props} />

                <div className={'au-tg-grid' + (canEdit ? ' is-dua' : '')}>
                    {canEdit ? <FormTarget {...props} /> : null}

                    <div className="au-tg-kanan">
                        <div className="au-tg-ringkas">
                            <div className="au-tg-stat">
                                <b>{fmt(totals.target)}</b>
                                <small>Total Target</small>
                            </div>
                            <div className="au-tg-stat">
                                <b className="is-biru">{fmt(totalActual)}</b>
                                <small>Total Aktual</small>
                            </div>
                            <div className={'au-tg-stat ' + (totals.target > 0 && totalActual >= totals.target ? 'is-capai' : 'is-belum')}>
                                <b>{pctAll}%</b>
                                <small>Pencapaian</small>
                            </div>
                        </div>

                        {targets.length ? (
                            <div className="au-tg-daftar">
                                {targets.map((t) => {
                                    const info = live?.actuals?.[t.productId];
                                    const actual = info ? info.actual : t.actual;
                                    const pct = info ? info.pct : t.target > 0 ? Math.min(Math.round((actual / t.target) * 100), 100) : 0;
                                    const done = info ? info.done : actual >= t.target;
                                    const kurang = info ? info.remaining : Math.max(0, t.target - actual);
                                    return (
                                        <div className="au-tg-baris" key={t.id}>
                                            <div className="au-tg-baris-atas">
                                                <div style={{ minWidth: 0 }}>
                                                    <p className="au-tg-nama">{t.product}</p>
                                                    {t.seriesKva ? <p className="au-tg-seri">{t.seriesKva}</p> : null}
                                                    {t.notes ? <p className="au-tg-catatan">{t.notes}</p> : null}
                                                </div>
                                                <div className="au-tg-angka">
                                                    <div>
                                                        <b className={done ? 'is-hijau' : ''}>
                                                            {fmt(actual)} / {fmt(t.target)}
                                                        </b>
                                                        <small>aktual / target</small>
                                                    </div>
                                                    <span className={'au-tg-pct ' + nada(pct, done)}>{pct}%</span>
                                                    {canDelete ? <HapusTarget t={t} /> : null}
                                                </div>
                                            </div>
                                            <div className="au-tg-bar">
                                                <div className={nada(pct, done)} style={{ width: pct + '%' }} />
                                            </div>
                                            {done ? (
                                                <p className="au-tg-label is-capai">
                                                    ✓ Target tercapai!
                                                    {t.hapusOtomatis ? <span> · terhapus otomatis {t.hapusOtomatis}</span> : null}
                                                </p>
                                            ) : (
                                                <p className="au-tg-label">Kurang {fmt(kurang)} unit lagi</p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="au-tg-kosong">
                                <p>Belum ada target aktif.</p>
                                {canEdit ? <small>Set target menggunakan form di sebelah kiri.</small> : null}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <TombolLive onTick={fetchLive} updatedAt={updatedAt} />
        </AppLayout>
    );
}

function HapusTarget({ t }) {
    async function hapus() {
        if (await konfirmasi('Hapus target ' + t.product + '?', { title: 'Hapus Target', okText: 'Hapus' })) {
            router.delete(t.deleteUrl, { preserveScroll: true });
        }
    }
    return (
        <button type="button" className="au-tg-hapus" title="Hapus target" onClick={hapus}>
            <Icon path={ICON.trash} />
        </button>
    );
}

/* ───────────── Foto jadwal mingguan ───────────── */

function FotoJadwal({ schedulePhoto, weekLabel, scheduleDate, canEdit, canDelete, photoUrl, photoDestroyUrl, pdfjs }) {
    const viewer = useRef(null);
    const [up, setUp] = useState(null);

    function unggah(file) {
        if (!file) return;
        const data = new FormData();
        data.append('target_date', scheduleDate);
        data.append('photo', file);
        const mulai = Date.now();
        const xhr = new XMLHttpRequest();
        xhr.open('POST', photoUrl, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        setUp({ pct: 0, info: '0 MB / ' + mb(file.size), stage: 'Mengupload…', error: null });

        xhr.upload.addEventListener('progress', (e) => {
            const tot = e.lengthComputable && e.total ? e.total : file.size;
            const laju = e.loaded / Math.max((Date.now() - mulai) / 1000, 0.001);
            setUp((u) => u && { ...u, pct: Math.min(100, (e.loaded / tot) * 100), info: mb(e.loaded) + ' / ' + mb(tot) + ' · ' + mb(laju) + '/dtk' });
        });
        xhr.upload.addEventListener('load', () => setUp((u) => u && { ...u, pct: 100, stage: 'Diproses di server…', info: 'Menyimpan berkas' }));
        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 400) {
                setUp(null);
                router.reload({ only: ['schedulePhoto', 'flash'] });
                return;
            }
            let pesan = 'Server menolak unggahan (kode ' + xhr.status + ').';
            if (xhr.status === 422) {
                try {
                    pesan = JSON.parse(xhr.responseText).errors?.photo?.[0] || pesan;
                } catch (_) {
                    /* abaikan */
                }
            } else if (xhr.status === 413) pesan = 'Berkas terlalu besar untuk server.';
            else if (xhr.status === 419) pesan = 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';
            setUp((u) => ({ ...(u || {}), stage: 'Upload gagal', info: '', error: pesan }));
        });
        xhr.addEventListener('error', () => setUp((u) => ({ ...(u || {}), stage: 'Upload gagal', info: '', error: 'Koneksi terputus saat mengunggah.' })));
        xhr.send(data);
    }

    async function hapus() {
        if (await konfirmasi('Hapus foto jadwal minggu ini?', { title: 'Hapus Foto Jadwal', okText: 'Hapus' })) {
            router.delete(photoDestroyUrl, { data: { target_date: scheduleDate }, preserveScroll: true });
        }
    }

    const layarPenuh = () => {
        const el = schedulePhoto?.isPdf ? document.getElementById('jadwal-viewer') : viewer.current;
        el?.requestFullscreen?.();
    };

    return (
        <div className="au-tg-kartu">
            <div className="au-tg-kepala">
                <div>
                    <h3>Foto Jadwal</h3>
                    <p>{weekLabel}</p>
                </div>
                {schedulePhoto ? (
                    <div className="au-tg-aksi">
                        <button type="button" onClick={layarPenuh}>
                            <Icon path={P.penuh} />
                            Layar Penuh
                        </button>
                        {canEdit ? (
                            <label>
                                <Icon path={P.ganti} />
                                Ganti
                                <input type="file" accept="image/*,.pdf" hidden onChange={(e) => { unggah(e.target.files[0]); e.target.value = ''; }} />
                            </label>
                        ) : null}
                        {canDelete ? (
                            <button type="button" className="is-merah" onClick={hapus}>
                                <Icon path={ICON.trash} />
                                Hapus
                            </button>
                        ) : null}
                    </div>
                ) : null}
            </div>

            {schedulePhoto ? (
                <div className="au-tg-foto">
                    {schedulePhoto.isPdf ? (
                        <div className="au-tg-pdf">
                            <PdfViewer id="jadwal-viewer" src={schedulePhoto.url} title="Jadwal minggu ini" download={schedulePhoto.url} height="85vh" pdfjs={pdfjs} />
                        </div>
                    ) : (
                        <a ref={viewer} href={schedulePhoto.url} target="_blank" rel="noreferrer" className="au-tg-gambar">
                            <img src={schedulePhoto.url} alt="Jadwal minggu ini" />
                            <span>Buka penuh</span>
                        </a>
                    )}
                    <p className="au-tg-oleh">
                        Diupload oleh {schedulePhoto.uploader} · {schedulePhoto.ago}
                    </p>
                </div>
            ) : canEdit ? (
                <div className="au-tg-isi">
                    <label className="au-tg-drop">
                        <span>
                            <Icon path={P.gambar} />
                        </span>
                        <b>Upload jadwal</b>
                        <small>JPG, PNG, WebP, PDF — maks 8 MB</small>
                        <input type="file" accept="image/*,.pdf" hidden onChange={(e) => { unggah(e.target.files[0]); e.target.value = ''; }} />
                    </label>
                </div>
            ) : (
                <p className="au-tg-belum">Belum ada foto jadwal untuk minggu ini.</p>
            )}

            {/* Overlay unggah: progres nyata dari XHR. */}
            {up ? (
                <div className="au-gku-overlay" style={{ display: 'flex' }}>
                    <div className="au-gku-overlay-kotak">
                        <div className="au-gku-overlay-atas">
                            <div className={'au-gku-spinner' + (up.error ? '' : ' is-putar')} style={{ borderTopColor: up.error ? '#ef4444' : '#2563eb' }} />
                            <div style={{ minWidth: 0, flex: 1 }}>
                                <p className="au-gku-stage">{up.stage}</p>
                                <p className="au-gku-info-teks">{up.info}</p>
                            </div>
                            <p className="au-gku-pct">{Math.round(up.pct || 0)}%</p>
                        </div>
                        <div className="au-gku-bar">
                            <div className={up.error ? 'is-merah' : ''} style={{ width: (up.pct || 0) + '%' }} />
                        </div>
                        {up.error ? (
                            <div className="au-gku-error">
                                <p>{up.error}</p>
                                <div>
                                    <button type="button" id="gkClose" onClick={() => setUp(null)}>
                                        Tutup
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <p className="au-gku-hint">Mohon tunggu, jangan tutup halaman.</p>
                        )}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

/* ───────────── Form set target ───────────── */

function FormTarget({ productGroups, storeUrl, actualQtyUrl }) {
    const { errors } = usePage().props;
    const { data, setData, post, processing, reset, transform } = useForm({ product_id: '', target_qty: '', notes: '', manual_series: '', manual_kva: '' });
    const [mode, setMode] = useState('qty'); // qty | serial
    const [qty, setQty] = useState('');
    const [ch, setCh] = useState({ up: '', bt: '' });
    const [serial, setSerial] = useState({ dari: '', sampai: '', otomatis: false });

    const pilihan = useMemo(() => productGroups.flatMap((g) => g.options), [productGroups]);
    const terpilih = pilihan.find((o) => String(o.value) === String(data.product_id)) || null;
    const isChannel = terpilih?.type === 'channel';

    useEffect(() => {
        if (isChannel && mode === 'serial') setMode('qty');
    }, [isChannel, mode]);

    // Mode "Sampai No. Urut": Dari = total produksi kumulatif produk (no. urut terakhir).
    useEffect(() => {
        if (mode !== 'serial' || !data.product_id) return;
        let hidup = true;
        fetch(actualQtyUrl + '?product_id=' + data.product_id, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((d) => {
                if (!hidup || !d) return;
                const dari = d.actual ?? 0;
                setSerial((s) => ({ dari: String(dari), sampai: s.sampai || (d.target_qty ? String(dari + d.target_qty) : ''), otomatis: true }));
            })
            .catch(() => {});
        return () => {
            hidup = false;
        };
    }, [mode, data.product_id, actualQtyUrl]);

    const upN = parseFloat(ch.up) || 0;
    const btN = parseFloat(ch.bt) || 0;
    const totalChannel = (upN + btN) / 2;
    const dariN = parseInt(serial.dari, 10) || 0;
    const sampaiN = parseInt(serial.sampai, 10) || 0;
    const qtySerial = sampaiN > 0 && sampaiN > dariN ? sampaiN - dariN : 0;

    const targetQty = mode === 'serial' ? (qtySerial || '') : isChannel ? (upN > 0 || btN > 0 ? totalChannel : '') : qty;

    function simpan(e) {
        e.preventDefault();
        // Nilai target dihitung dari mode yang dipilih (jumlah / channel / no. urut).
        transform((d) => ({ ...d, target_qty: targetQty }));
        post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setQty('');
                setCh({ up: '', bt: '' });
                setSerial({ dari: '', sampai: '', otomatis: false });
            },
        });
    }

    return (
        <div className="au-tg-kiri">
            <div className="au-tg-form">
                <div className="au-tg-form-kepala">
                    <h3>Set Target</h3>
                    <p>Target produksi per produk · tanpa batas waktu</p>
                </div>
                <form onSubmit={simpan} className="au-tg-form-isi">
                    <div>
                        <label className="au-tg-lbl">Produk</label>
                        <PilihProduk groups={productGroups} value={data.product_id} error={errors.product_id} onChange={(v) => setData('product_id', v)} />
                        {errors.product_id ? <p className="au-tg-galat">{errors.product_id}</p> : null}

                        {terpilih?.manual ? (
                            <div className="au-tg-manual">
                                <p>
                                    Identitas Produk <span>(wajib diisi)</span>
                                </p>
                                <div className="au-form-grid au-form-grid-2">
                                    <div>
                                        <label>Nomor Seri</label>
                                        <input className="au-input au-mono" required maxLength={100} placeholder="Contoh: A-1234" value={data.manual_series} onChange={(e) => setData('manual_series', e.target.value)} />
                                    </div>
                                    <div>
                                        <label>KVA</label>
                                        <input className="au-input au-mono" required maxLength={50} placeholder="Contoh: 100" value={data.manual_kva} onChange={(e) => setData('manual_kva', e.target.value)} />
                                    </div>
                                </div>
                            </div>
                        ) : null}
                    </div>

                    <div>
                        <label className="au-tg-lbl">Target Unit</label>
                        <div className="au-tg-mode">
                            <button type="button" className={mode === 'qty' ? 'is-aktif' : ''} onClick={() => setMode('qty')}>
                                Jumlah Unit
                            </button>
                            {!isChannel ? (
                                <button type="button" className={mode === 'serial' ? 'is-aktif' : ''} onClick={() => setMode('serial')}>
                                    Sampai No. Urut
                                </button>
                            ) : null}
                        </div>

                        {mode === 'qty' && !isChannel ? (
                            <div>
                                <input type="number" min={1} max={999999} className="au-input au-tg-besar" placeholder="Contoh: 500" value={qty} onChange={(e) => setQty(e.target.value)} />
                                <p className="au-tg-bantu">Total unit yang harus diproduksi</p>
                            </div>
                        ) : null}

                        {mode === 'qty' && isChannel ? (
                            <div className="au-tg-channel">
                                <div className="au-form-grid au-form-grid-2">
                                    <div>
                                        <label className="is-up">Channel UP</label>
                                        <input type="number" min={0} max={9999} className="au-input au-tg-besar" placeholder="Contoh: 100" value={ch.up} onChange={(e) => setCh({ ...ch, up: e.target.value })} />
                                    </div>
                                    <div>
                                        <label className="is-bt">Channel BT</label>
                                        <input type="number" min={0} max={9999} className="au-input au-tg-besar" placeholder="Contoh: 100" value={ch.bt} onChange={(e) => setCh({ ...ch, bt: e.target.value })} />
                                    </div>
                                </div>
                                {upN > 0 || btN > 0 ? (
                                    <div className="au-tg-pratinjau is-biru">
                                        <p>
                                            Total target <small>(UP + BT) ÷ 2</small>
                                        </p>
                                        <b>{(totalChannel % 1 === 0 ? totalChannel.toLocaleString('id-ID') : totalChannel.toFixed(1)) + ' unit'}</b>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        {mode === 'serial' ? (
                            <div className="au-tg-serial">
                                <div className="au-form-grid au-form-grid-2">
                                    <div>
                                        <label>
                                            Dari No. Urut {serial.otomatis ? <span>(otomatis)</span> : null}
                                        </label>
                                        <input type="number" min={1} max={999999} className="au-input au-tg-sedang" placeholder="Auto dari riwayat" value={serial.dari} onChange={(e) => setSerial({ ...serial, dari: e.target.value })} />
                                    </div>
                                    <div>
                                        <label>Sampai No. Urut</label>
                                        <input type="number" min={1} max={999999} className="au-input au-tg-sedang" placeholder="Contoh: 500" value={serial.sampai} onChange={(e) => setSerial({ ...serial, sampai: e.target.value })} />
                                    </div>
                                </div>
                                {qtySerial > 0 ? (
                                    <div className="au-tg-pratinjau is-hijau">
                                        <p>Total target</p>
                                        <b>{qtySerial.toLocaleString('id-ID')} unit</b>
                                        <small>
                                            No. Urut {dariN} → {sampaiN}
                                        </small>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                        {errors.target_qty ? <p className="au-tg-galat">{errors.target_qty}</p> : null}
                    </div>

                    <div>
                        <label className="au-tg-lbl">
                            Catatan <span>(opsional)</span>
                        </label>
                        <input className="au-input" maxLength={200} placeholder="Misal: Target khusus akhir bulan" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>

                    <button type="submit" className="au-tg-simpan" disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Simpan Target'}
                    </button>
                </form>
            </div>
        </div>
    );
}

/* Tombol Live/Paused — progres disegarkan tiap 60 detik (localStorage tgtAutoRefresh). */
const INTERVAL = 60;
function TombolLive({ onTick, updatedAt }) {
    const [on, setOn] = useState(() => {
        try {
            return localStorage.getItem('tgtAutoRefresh') !== 'false';
        } catch (_) {
            return true;
        }
    });
    const [cd, setCd] = useState(INTERVAL);

    useEffect(() => {
        if (!on) return undefined;
        onTick();
        let c = INTERVAL;
        setCd(c);
        const t = setInterval(() => {
            c -= 1;
            if (c <= 0) {
                c = INTERVAL;
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
            localStorage.setItem('tgtAutoRefresh', String(v));
        } catch (_) {
            /* abaikan */
        }
    }

    return (
        <button type="button" className="au-tg-live au-db-live-tombol" onClick={ubah} title="Toggle auto-refresh">
            <i className={on ? 'is-on' : ''} />
            <b>{on ? 'Live' : 'Paused'}</b>
            {on ? <span>{cd}s</span> : null}
            {updatedAt ? <small> · {updatedAt}</small> : null}
        </button>
    );
}
