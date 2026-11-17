import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Icon } from '../../Components/Ui';
import { csrf } from '../../csrf';

const KATEGORI = [
    { value: 'pln', label: 'Standar / PLN', tone: 'slate' },
    { value: 'swasta', label: 'Seri Swasta', tone: 'amber' },
    { value: 'typetest', label: 'Seri Typetest', tone: 'purple' },
];
const INFO = 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
const AWAN = 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12';
const AWAS = 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.446 0L3.34 16c-.77 1.333.192 3 1.732 3z';

const mb = (b) => (b / 1048576).toFixed(1) + ' MB';
const ukuranBerkas = (n) => (n > 1046576 ? (n / 1046576).toFixed(1) + ' MB' : Math.round(n / 1024) + ' KB');
// Tahun diambil dari 2 digit awal seri (26xxxx → 2026), seperti referensi.
const tahunDariSeri = (seri) => {
    const m = String(seri || '').match(/^(\d{2})/);
    return m ? '20' + m[1] : '';
};

/**
 * Upload Gambar Kerja — mengikuti Production-QC-Logging-System: kartu
 * bergradasi, penghitung karakter judul (46) & kata keterangan (18) dengan
 * toast, pratinjau "seri(kva)", pilihan kategori berupa kartu, zona seret
 * berkas + daftar berkas, dan overlay unggah dengan kemajuan NYATA dari
 * XMLHttpRequest (persen, MB terkirim, laju, sisa waktu, coba lagi).
 */
export default function GambarKerjaCreate({ action, indexUrl, prefill }) {
    const page = usePage();
    const { errors } = page.props;
    const isAdd = !!prefill;
    const formRef = useRef(null);

    const [judul, setJudul] = useState(prefill?.judul || '');
    const [seri, setSeri] = useState(prefill?.seri || '');
    const [kva, setKva] = useState(prefill?.kva || '');
    const [kategori, setKategori] = useState('pln');
    const [keterangan, setKeterangan] = useState('');
    const [files, setFiles] = useState([]);
    const [toast, setToast] = useState('');
    const toastT = useRef(null);
    const [up, setUp] = useState(null); // status overlay unggah

    const kata = keterangan.trim() ? keterangan.trim().split(/\s+/).length : 0;

    function tampilToast(teks) {
        setToast(teks);
        clearTimeout(toastT.current);
        toastT.current = setTimeout(() => setToast(''), 3000);
    }

    function ubahJudul(v) {
        if (v.length > 40 && judul.length <= 40) tampilToast('Judul mendekati batas maksimum (46 karakter)');
        setJudul(v);
    }

    function ubahKeterangan(v) {
        const w = v.trim() ? v.trim().split(/\s+/).length : 0;
        if (w > 18 && kata <= 18) tampilToast('Kalimat Anda melebihi batas maksimum (18 kata)');
        setKeterangan(v);
    }

    const xhrRef = useRef(null);

    function kirim() {
        const form = formRef.current;
        const total = files.reduce((s, f) => s + (f.size || 0), 0);
        const mulai = Date.now();
        let adaKabar = false;

        setUp({ stage: 'Mengupload…', info: total ? '0 MB / ' + mb(total) : 'Menyiapkan berkas…', pct: 0, tidakTahu: false, error: null });

        /* Sebagian browser baru mengirim event progress setelah seluruh berkas
           terkirim — layar jadi terlihat menggantung di 0%. Kalau dalam 1 detik
           belum ada kabar, tampilkan penanda berjalan. */
        const diam = setTimeout(() => {
            if (!adaKabar) setUp((u) => u && { ...u, tidakTahu: true, info: total ? 'Mengirim ' + mb(total) + '…' : 'Mengirim berkas…' });
        }, 1000);

        const data = new FormData(form);
        const xhr = new XMLHttpRequest();
        xhrRef.current = xhr;
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        // Header Inertia: redirect diikuti XHR dan halaman tujuan (grup, atau
        // form ini lagi berisi error validasi) datang sebagai JSON. Tanpa ini
        // pesan error ikut termakan oleh XHR dan hilang saat halaman dimuat ulang.
        xhr.setRequestHeader('X-Inertia', 'true');
        if (page.version) xhr.setRequestHeader('X-Inertia-Version', page.version);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'text/html, application/xhtml+xml');

        xhr.upload.addEventListener('progress', (e) => {
            adaKabar = true;
            clearTimeout(diam);
            const tot = e.lengthComputable && e.total ? e.total : total;
            const loaded = e.loaded || 0;
            const detik = (Date.now() - mulai) / 1000;
            const laju = detik > 0 ? loaded / detik : 0;

            if (!tot) {
                setUp((u) => u && { ...u, tidakTahu: true, info: 'Terkirim ' + mb(loaded) + (laju > 0 ? ' · ' + mb(laju) + '/dtk' : '') });
                return;
            }
            const sisa = laju > 0 ? Math.round((tot - loaded) / laju) : null;
            let teks = mb(loaded) + ' / ' + mb(tot);
            if (laju > 0) teks += ' · ' + mb(laju) + '/dtk';
            if (sisa !== null && sisa > 0 && loaded < tot) teks += ' · sisa ' + (sisa >= 60 ? Math.ceil(sisa / 60) + ' mnt' : sisa + ' dtk');
            setUp((u) => u && { ...u, tidakTahu: false, pct: Math.min(100, (loaded / tot) * 100), info: teks });
        });

        // Semua byte terkirim; server masih menyimpan berkas.
        xhr.upload.addEventListener('load', () => {
            clearTimeout(diam);
            setUp((u) => u && { ...u, tidakTahu: false, pct: 100, stage: 'Diproses di server…', info: 'Menyimpan berkas dan membuat pratinjau' });
        });

        const gagal = (pesan) => {
            clearTimeout(diam);
            setUp((u) => ({ ...(u || {}), stage: 'Upload gagal', info: '', tidakTahu: false, error: pesan }));
        };

        xhr.addEventListener('load', () => {
            if (xhr.status === 409 && xhr.getResponseHeader('X-Inertia-Location')) {
                // Versi aset berubah (aplikasi baru di-build): muat penuh.
                window.location.href = xhr.getResponseHeader('X-Inertia-Location');
                return;
            }
            if (xhr.status >= 200 && xhr.status < 400) {
                let tujuan = null;
                try {
                    tujuan = JSON.parse(xhr.responseText);
                } catch (_) {
                    /* bukan JSON Inertia */
                }
                if (tujuan && tujuan.component) {
                    if (tujuan.component !== page.component) {
                        setUp((u) => u && { ...u, stage: 'Selesai', info: 'Mengalihkan…' });
                    } else {
                        setUp(null); // kembali ke form ini: tampilkan error validasinya
                    }
                    router.push({ component: tujuan.component, url: tujuan.url, props: tujuan.props, flash: tujuan.flash });
                } else {
                    window.location.href = xhr.responseURL || indexUrl;
                }
                return;
            }
            if (xhr.status === 413) gagal('Berkas terlalu besar untuk server (batas unggah terlampaui). Coba unggah lebih sedikit berkas sekaligus.');
            else if (xhr.status === 419) gagal('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            else gagal('Server menolak unggahan (kode ' + xhr.status + ').');
        });
        xhr.addEventListener('error', () => gagal('Koneksi terputus saat mengunggah. Periksa jaringan lalu coba lagi.'));
        xhr.addEventListener('abort', () => gagal('Unggahan dibatalkan.'));

        xhr.send(data);
    }

    // Cegah halaman ditutup tanpa sengaja saat unggahan sedang jalan.
    useEffect(() => {
        const cegah = (e) => {
            const x = xhrRef.current;
            if (x && x.readyState > 0 && x.readyState < 4) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', cegah);
        return () => window.removeEventListener('beforeunload', cegah);
    }, []);

    function submit(e) {
        // Tanpa berkas: biarkan validasi server yang menjawab (submit biasa via Inertia).
        e.preventDefault();
        if (!files.length) {
            router.post(action, new FormData(formRef.current));
            return;
        }
        kirim();
    }

    const tahun = isAdd ? prefill.tahun || '' : tahunDariSeri(seri);

    return (
        <AppLayout title={isAdd ? 'Tambah File' : 'Upload Gambar Kerja'} subtitle={isAdd ? 'Menambah file ke grup yang sudah ada' : 'Tambah dokumen gambar teknik'}>
            <div className="au-gku">
                <div className={'au-gku-kepala' + (isAdd ? ' is-hijau' : '')}>
                    <h2>{isAdd ? 'Tambah File ke Grup' : 'Upload Gambar Kerja'}</h2>
                    <p>{isAdd ? 'File baru akan ditambahkan ke grup: ' + prefill.judul : 'Bisa pilih banyak file sekaligus — nomor urut otomatis'}</p>
                </div>

                <form id="gkUploadForm" ref={formRef} method="POST" action={action} encType="multipart/form-data" className="au-gku-form" onSubmit={submit}>
                    <input type="hidden" name="_token" value={csrf()} />

                    {isAdd ? (
                        <>
                            <div className="au-gku-grup">
                                <Icon path={INFO} />
                                <div>
                                    <b>{prefill.judul}</b>
                                    <p className="au-mono">
                                        {(prefill.seri || '') + (prefill.kva ? '-' + prefill.kva + 'KVA' : '')} {prefill.tahun ? '· ' + prefill.tahun : ''}
                                    </p>
                                </div>
                            </div>
                            <input type="hidden" name="judul" value={prefill.judul || ''} />
                            <input type="hidden" name="seri" value={prefill.seri || ''} />
                            <input type="hidden" name="kva" value={prefill.kva || ''} />
                            <input type="hidden" name="tahun" value={tahun} />
                        </>
                    ) : (
                        <>
                            <div>
                                <label className="au-gku-label">
                                    Judul <span className="au-req">*</span>
                                    <span className={'au-gku-hitung' + (judul.length > 46 ? ' is-lebih' : '')}>({judul.length}/46)</span>
                                </label>
                                <input
                                    className={'au-input' + (errors.judul || judul.length > 46 ? ' has-error' : '')}
                                    name="judul"
                                    value={judul}
                                    placeholder="Contoh: Gambar Teknik Trafo 500KVA"
                                    onChange={(e) => ubahJudul(e.target.value)}
                                />
                                {errors.judul ? <span className="au-error">{errors.judul}</span> : null}
                            </div>

                            <div>
                                <div className="au-form-grid au-form-grid-2">
                                    <div>
                                        <label className="au-gku-label">
                                            Seri <span className="au-req">*</span>
                                        </label>
                                        <input className={'au-input' + (errors.seri ? ' has-error' : '')} name="seri" value={seri} placeholder="Contoh: 26#######" onChange={(e) => setSeri(e.target.value)} />
                                        {errors.seri ? <span className="au-error">{errors.seri}</span> : null}
                                    </div>
                                    <div>
                                        <label className="au-gku-label">
                                            KVA <span className="au-req">*</span>
                                        </label>
                                        <input className={'au-input' + (errors.kva ? ' has-error' : '')} name="kva" value={kva} placeholder="Contoh: 50, 100, 160" onChange={(e) => setKva(e.target.value)} />
                                        {errors.kva ? <span className="au-error">{errors.kva}</span> : null}
                                    </div>
                                    <input type="hidden" name="tahun" value={tahun} />
                                </div>
                                {seri || kva ? (
                                    <div className="au-gku-pratinjau">
                                        <span>Akan tampil sebagai:</span>
                                        <b className="au-mono">{seri + (kva ? '(' + kva + ')' : '')}</b>
                                    </div>
                                ) : null}
                                {errors.tahun ? <span className="au-error">{errors.tahun}</span> : null}
                            </div>

                            <div>
                                <label className="au-gku-label">
                                    Kategori Seri <span className="au-req">*</span>
                                </label>
                                <div className="au-gku-kat">
                                    {KATEGORI.map((k) => (
                                        <label key={k.value} className={'is-' + k.tone + (kategori === k.value ? ' is-aktif' : '')}>
                                            <input type="radio" name="kategori_seri" value={k.value} checked={kategori === k.value} onChange={() => setKategori(k.value)} />
                                            <i />
                                            <span>{k.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </>
                    )}

                    <div className="au-gku-info">
                        <Icon path={INFO} />
                        <p>Beberapa file dengan judul &amp; seri yang sama akan digabung otomatis dan diberi nomor urut lanjutan.</p>
                    </div>

                    <div>
                        <label className="au-gku-label">
                            File Gambar / PDF <span className="au-req">*</span>
                        </label>
                        <div className={'au-gku-drop' + (files.length ? ' is-isi' : '')}>
                            <input type="file" name="files[]" accept=".jpg,.jpeg,.png,.pdf" multiple onChange={(e) => setFiles(Array.from(e.target.files))} />
                            <div className="au-gku-drop-isi">
                                <Icon path={AWAN} />
                                {files.length ? (
                                    <p className="is-dipilih">{files.length} file dipilih — klik untuk ganti</p>
                                ) : (
                                    <>
                                        <p>
                                            Klik atau <b>seret file</b> ke sini
                                        </p>
                                        <small>Pilih banyak file sekaligus — JPG, PNG, PDF, maks. 100MB/file</small>
                                    </>
                                )}
                            </div>
                        </div>
                        {files.length ? (
                            <ul className="au-gku-files">
                                {files.map((f, i) => {
                                    const pdf = f.name.toLowerCase().endsWith('.pdf');
                                    return (
                                        <li key={i}>
                                            <span className={pdf ? 'is-pdf' : 'is-img'}>{pdf ? 'PDF' : 'IMG'}</span>
                                            <span className="au-gku-files-nama">{f.name}</span>
                                            <small>{ukuranBerkas(f.size)}</small>
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : null}
                        {errors.files ? <span className="au-error">{errors.files}</span> : null}
                        {Object.keys(errors).filter((k) => k.startsWith('files.')).slice(0, 1).map((k) => (
                            <span className="au-error" key={k}>
                                {errors[k]}
                            </span>
                        ))}
                    </div>

                    <div>
                        <label className="au-gku-label">
                            Keterangan <span className={'au-gku-hitung' + (kata > 18 ? ' is-lebih' : '')}>({kata}/18 kata)</span>
                        </label>
                        <textarea
                            className={'au-textarea' + (kata > 18 ? ' has-error' : '')}
                            name="keterangan"
                            rows={2}
                            value={keterangan}
                            placeholder="Contoh: Revisi ke-2, Tampak depan dan samping..."
                            onChange={(e) => ubahKeterangan(e.target.value)}
                        />
                        {errors.keterangan ? <span className="au-error">{errors.keterangan}</span> : null}
                    </div>

                    <div className="au-gku-aksi">
                        <a href={indexUrl} className="au-gku-batal">
                            Batal
                        </a>
                        <button type="submit" className="au-gku-kirim">
                            <Icon path={AWAN} />
                            Upload
                        </button>
                    </div>
                </form>
            </div>

            {toast ? (
                <div className="au-gku-toast">
                    <Icon path={AWAS} />
                    {toast}
                </div>
            ) : null}

            {/* Overlay unggah: progres nyata dari XHR, bukan animasi yang hanya berputar. */}
            <div id="gkUploadOverlay" className="au-gku-overlay" style={{ display: up ? 'flex' : 'none' }}>
                <div className="au-gku-overlay-kotak">
                    <div className="au-gku-overlay-atas">
                        {/* Warna sisi atas dipasang inline supaya tidak tertimpa kelas border lain. */}
                        <div id="gkSpinner" className={'au-gku-spinner' + (up?.error ? '' : ' is-putar')} style={{ borderTopColor: up?.error ? '#ef4444' : '#2563eb' }} />
                        <div style={{ minWidth: 0, flex: 1 }}>
                            <p id="gkStage" className="au-gku-stage">{up?.stage || 'Mengupload…'}</p>
                            <p id="gkInfo" className="au-gku-info-teks">{up?.info ?? 'Menyiapkan berkas…'}</p>
                        </div>
                        <p id="gkPct" className="au-gku-pct">{up?.tidakTahu ? '—' : Math.round(up?.pct || 0) + '%'}</p>
                    </div>
                    <div className="au-gku-bar">
                        <div
                            id="gkBar"
                            className={(up?.error ? 'is-merah' : '') + (up?.tidakTahu ? ' is-denyut' : '')}
                            style={{ width: (up?.tidakTahu ? 100 : up?.pct || 0).toFixed(1) + '%' }}
                        />
                    </div>
                    {!up?.error ? <p className="au-gku-hint">Mohon tunggu, jangan tutup halaman.</p> : null}
                    {up?.error ? (
                        <div className="au-gku-error">
                            <p>{up.error}</p>
                            <div>
                                <button type="button" id="gkRetry" onClick={kirim}>
                                    Coba lagi
                                </button>
                                <button type="button" id="gkClose" onClick={() => setUp(null)}>
                                    Tutup
                                </button>
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
