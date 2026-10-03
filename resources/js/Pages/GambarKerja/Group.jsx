import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON, Icon, Input, Textarea } from '../../Components/Ui';
import { berkasTersimpan } from '../../offlineFiles';
import { csrf } from '../../csrf';
import { konfirmasi } from '../../dialog';
import PdfViewer from '../../Components/PdfViewer';

const DOC =
    'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
const IMG =
    'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z';
const DOWN = 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4';
const SIMPAN =
    'M5 13l4 4L19 7M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z';

const KATEGORI = [
    { value: 'pln', label: 'Standar / PLN', tone: 'slate' },
    { value: 'swasta', label: 'Seri Swasta', tone: 'amber' },
    { value: 'typetest', label: 'Seri Typetest', tone: 'purple' },
];

/**
 * Tata letak versi lama: satu kartu kepala berisi identitas kelompok, pengaturan
 * thumbnail, kategori seri, serta judul & keterangan; lalu daftar berkas sebagai
 * kartu selebar halaman dengan pratinjau besar (gambar bisa diperbesar, PDF
 * ditampilkan dalam bingkai).
 */
export default function GambarKerjaGroup({
    group,
    files,
    backUrl,
    infoUrl,
    kategoriUrl,
    thumbUrl,
    thumbDestroyUrl,
    addFileUrl,
    can,
    pdfjs,
}) {
    const [busy, setBusy] = useState(false);
    const [kat, setKat] = useState(group.kategori || 'pln');
    const [judul, setJudul] = useState(group.judul || '');
    const [keterangan, setKeterangan] = useState(group.keterangan || '');
    const [thumbPreview, setThumbPreview] = useState(group.thumbnail || '');
    const [thumbFile, setThumbFile] = useState(null);
    const [zoom, setZoom] = useState({});
    const [simpanan, setSimpanan] = useState({ set: new Set() });

    // Viewer PDF.js membersihkan dirinya lewat event 'spa:leave'; dikirim saat
    // halaman ditinggalkan, bukan saat satu berkas hilang dari daftar.
    useEffect(() => () => document.dispatchEvent(new Event('spa:leave')), []);

    // Berkas diunduh oleh service worker saat pratinjau dimuat, jadi statusnya
    // dipantau beberapa saat sampai semuanya tersimpan.
    useEffect(() => {
        const urls = files.map((f) => f.url);
        let hidup = true;
        let sisa = 24;

        async function periksa() {
            const hasil = await berkasTersimpan(urls);
            if (!hidup) return;
            setSimpanan(hasil);
            if (hasil.jumlah >= urls.length) clearInterval(timer);
        }

        const timer = setInterval(() => {
            if (sisa-- <= 0) return clearInterval(timer);
            periksa();
        }, 2500);

        periksa();

        return () => {
            hidup = false;
            clearInterval(timer);
        };
    }, [files]);

    const seriKva = (group.seri || '') + (group.kva ? '(' + group.kva + ')' : '');
    const identity = {
        judul: group.judul,
        seri: group.seri || '',
        kva: group.kva || '',
        tahun: group.tahun || '',
    };

    const kataKeterangan = keterangan.trim() ? keterangan.trim().split(/\s+/).length : 0;

    function simpanKategori() {
        router.patch(kategoriUrl, { ...identity, kategori_seri: kat }, { preserveScroll: true });
    }

    function simpanInfo(e) {
        e.preventDefault();
        router.patch(
            infoUrl,
            { ...identity, judul_baru: judul, keterangan },
            { preserveScroll: true },
        );
    }

    async function simpanThumbnail() {
        if (!thumbFile) return;
        setBusy(true);

        const body = new FormData();
        Object.entries(identity).forEach(([k, v]) => body.append(k, v));
        body.append('thumbnail', thumbFile);

        await fetch(thumbUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body,
        });

        setBusy(false);
        setThumbFile(null);
        router.reload();
    }

    async function hapusThumbnail() {
        if (!(await konfirmasi('Hapus thumbnail kelompok ini?', { title: 'Hapus Thumbnail', okText: 'Hapus' }))) return;
        setThumbPreview('');
        router.delete(thumbDestroyUrl, { data: identity, preserveScroll: true });
    }

    async function hapusFile(f) {
        const ok = await konfirmasi(
            'Hapus file ' + f.urutan + " dari '" + group.judul + "'? Nomor urut akan diperbarui otomatis.",
            { title: 'Hapus File', okText: 'Hapus' },
        );
        if (!ok) return;
        router.delete(f.deleteUrl, { preserveScroll: true });
    }

    return (
        <AppLayout
            title={group.judul}
            subtitle={
                'Gambar Kerja' + (seriKva ? ' · ' + seriKva : '') + ' · ' + files.length + ' file'
            }
        >
            <div className="au-panel">
                <div className="au-gkhead">
                    <div>
                        <h2 className="au-gkhead-title">{group.judul}</h2>
                        {seriKva ? <p className="au-gkhead-sub">{seriKva}</p> : null}
                    </div>
                    <div className="au-gkhead-actions">
                        {can.upload ? (
                            <Btn as="a" href={addFileUrl} icon={ICON.plus} className="au-btn--green">
                                Tambah File
                            </Btn>
                        ) : null}
                        <Btn as="a" href={backUrl} tone="ghost">
                            ← Kembali
                        </Btn>
                    </div>
                </div>

                {can.edit ? (
                    <>
                        <div className="au-gksection">
                            <p className="au-gksection-label">Thumbnail Card</p>
                            <div className="au-gkrow">
                                <div className="au-gkthumb">
                                    {thumbPreview ? (
                                        <img src={thumbPreview} alt="" />
                                    ) : (
                                        <Icon path={IMG} />
                                    )}
                                </div>

                                <label className="au-btn au-btn--ghost">
                                    <input
                                        type="file"
                                        accept="image/jpg,image/jpeg,image/png"
                                        style={{ display: 'none' }}
                                        onChange={(e) => {
                                            const file = e.target.files[0];
                                            if (!file) return;
                                            setThumbFile(file);
                                            setThumbPreview(URL.createObjectURL(file));
                                        }}
                                    />
                                    <Icon path={IMG} />
                                    Pilih Gambar
                                </label>

                                <Btn disabled={!thumbFile || busy} onClick={simpanThumbnail}>
                                    {busy ? 'Menyimpan...' : 'Simpan Thumbnail'}
                                </Btn>

                                {group.thumbnail ? (
                                    <button
                                        type="button"
                                        className="au-gk-del au-gk-del--inline"
                                        onClick={hapusThumbnail}
                                    >
                                        <Icon path={ICON.trash} />
                                        Hapus Thumbnail
                                    </button>
                                ) : null}

                                <span className="au-hint">JPG / PNG · maks. 5MB</span>
                            </div>
                        </div>

                        <div className="au-gksection">
                            <p className="au-gksection-label">Kategori Seri</p>
                            <div className="au-gkrow">
                                {KATEGORI.map((k) => (
                                    <button
                                        type="button"
                                        key={k.value}
                                        className={
                                            'au-katopt au-katopt--' +
                                            k.tone +
                                            (kat === k.value ? ' is-on' : '')
                                        }
                                        onClick={() => setKat(k.value)}
                                    >
                                        <span className={'au-dot au-dot--' + k.tone} />
                                        {k.label}
                                    </button>
                                ))}
                                <Btn onClick={simpanKategori}>Simpan</Btn>
                            </div>
                        </div>

                        <div className="au-gksection">
                            <p className="au-gksection-label">Edit Judul &amp; Keterangan</p>
                            <form className="au-form" onSubmit={simpanInfo}>
                                <div className="au-field">
                                    <label className="au-label">
                                        Judul<span className="au-req"> *</span>
                                        <span
                                            className={
                                                'au-counter' + (judul.length > 46 ? ' is-over' : '')
                                            }
                                        >
                                            ({judul.length}/46 karakter)
                                        </span>
                                    </label>
                                    <Input
                                        value={judul}
                                        maxLength={150}
                                        onChange={(e) => setJudul(e.target.value)}
                                    />
                                </div>

                                <div className="au-field">
                                    <label className="au-label">
                                        Keterangan
                                        <span
                                            className={
                                                'au-counter' +
                                                (kataKeterangan > 18 ? ' is-over' : '')
                                            }
                                        >
                                            ({kataKeterangan}/18 kata)
                                        </span>
                                    </label>
                                    <Textarea
                                        value={keterangan}
                                        maxLength={300}
                                        style={{ minHeight: 62 }}
                                        placeholder="Catatan untuk semua file di grup ini..."
                                        onChange={(e) => setKeterangan(e.target.value)}
                                    />
                                </div>

                                <div className="au-form-actions">
                                    <Btn type="submit">Simpan Perubahan</Btn>
                                </div>
                            </form>
                        </div>
                    </>
                ) : null}
            </div>

            {files.length ? (
                files.map((f) => (
                    <div className="au-filecard" key={f.id}>
                        <div className="au-filecard-head">
                            <span className="au-filecard-left">
                                <span className="au-filecard-no">{f.urutan}</span>
                                <span className="au-filecard-info">
                                    <b>File {f.urutan}</b>
                                    {f.keterangan ? <i>{f.keterangan}</i> : null}
                                </span>
                                <span
                                    className={
                                        'au-prodtag ' +
                                        (f.isPdf ? 'au-prodtag--red' : 'au-prodtag--blue')
                                    }
                                >
                                    {f.isPdf ? 'PDF' : 'Gambar'}
                                </span>
                                {simpanan.set.has(f.url) ? (
                                    <span className="au-prodtag au-offline-tag">
                                        <Icon path={SIMPAN} />
                                        Tersimpan
                                    </span>
                                ) : null}
                            </span>

                            <span className="au-filecard-right">
                                <span className="au-filecard-date">{f.at || '—'}</span>

                                {can.download ? (
                                    <a
                                        className="au-filebtn au-filebtn--green"
                                        href={f.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        download
                                    >
                                        <Icon path={DOWN} />
                                        Download
                                    </a>
                                ) : null}

                                {can.delete ? (
                                    <button
                                        type="button"
                                        className="au-filebtn au-filebtn--red"
                                        onClick={() => hapusFile(f)}
                                    >
                                        <Icon path={ICON.trash} />
                                        Hapus
                                    </button>
                                ) : null}
                            </span>
                        </div>

                        {f.isPdf ? (
                            <PdfViewer
                                id={'pdf-' + f.id}
                                src={f.url}
                                title={'File ' + f.urutan}
                                download={can.pdfDownload ? f.url : null}
                                pdfjs={pdfjs}
                            />
                        ) : (
                            <div className="au-filepreview">
                                <img
                                    className={zoom[f.id] ? 'is-zoom' : ''}
                                    src={f.url}
                                    alt={'File ' + f.urutan}
                                    onClick={() =>
                                        setZoom((z) => ({ ...z, [f.id]: !z[f.id] }))
                                    }
                                />
                            </div>
                        )}
                    </div>
                ))
            ) : (
                <div className="au-blank">
                    <Icon path={DOC} />
                    <p>Belum ada file untuk dokumen ini</p>
                </div>
            )}
        </AppLayout>
    );
}
