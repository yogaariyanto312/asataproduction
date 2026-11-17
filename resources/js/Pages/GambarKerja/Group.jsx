import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON, Icon, Input, Textarea } from '../../Components/Ui';
import { berkasTersimpan } from '../../offlineFiles';
import { csrf } from '../../csrf';
import { konfirmasi } from '../../dialog';
import { createPortal } from 'react-dom';
import PdfViewer from '../../Components/PdfViewer';
import { useRealtime } from '../../realtime';
import PratinjauPdf from '../../Components/PratinjauPdf';

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
    // Edit keterangan per file (mis. "Revisi ke-2") langsung di kartu file.
    const [edit, setEdit] = useState(null);
    // Berkas yang sedang dibuka penuh (modal); pratinjau halaman 1 tampil di kartu tanpa memuat seluruh PDF.
    const [penuh, setPenuh] = useState(null);
    // Realtime ringan: berkas ditambah/diubah/dihapus orang lain langsung terlihat.
    useRealtime('gambar', () => router.reload({ only: ['files', 'group'], preserveScroll: true }), 0);

    useEffect(() => {
        if (!penuh) return undefined;
        const esc = (e) => e.key === 'Escape' && setPenuh(null);
        document.addEventListener('keydown', esc);
        return () => document.removeEventListener('keydown', esc);
    }, [penuh]);

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

    function simpanInfo(e) {
        e.preventDefault();
        router.patch(
            infoUrl,
            { ...identity, judul_baru: judul, keterangan, kategori_seri: kat },
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

    function simpanCatatan(e) {
        e.preventDefault();
        router.patch(edit.url, { catatan: edit.teks }, { preserveScroll: true, onSuccess: () => setEdit(null) });
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
                <section className="au-berkas-grid" aria-label="Berkas gambar kerja">
                    {files.map((f) => (
                        <article className="au-berkas" key={f.id}>
                            <button type="button" className="au-berkas-gambar" onClick={() => setPenuh(f)} aria-label={'Buka File ' + f.urutan + ' layar penuh'}>
                                {f.isPdf ? (
                                    <PratinjauPdf src={f.url} pdfjs={pdfjs} />
                                ) : (
                                    <img loading="lazy" decoding="async" src={f.url} alt={'File ' + f.urutan} />
                                )}
                                <span className="au-berkas-besar" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" /></svg>
                                </span>
                            </button>

                            <div className="au-berkas-isi">
                                <div className="au-berkas-baris">
                                    <span className="au-berkas-no">{f.urutan}</span>
                                    <b>File {f.urutan}</b>
                                    <span className={'au-prodtag ' + (f.isPdf ? 'au-prodtag--red' : 'au-prodtag--blue')}>{f.isPdf ? 'PDF' : 'Gambar'}</span>
                                </div>
                                <div className="au-berkas-baris is-meta">
                                    <span>{f.at || '—'}</span>
                                    {simpanan.set.has(f.url) ? (
                                        <span className="au-prodtag au-offline-tag">
                                            <Icon path={SIMPAN} />
                                            Tersimpan
                                        </span>
                                    ) : null}
                                </div>

                                <div className="au-berkas-aksi">
                                    <button type="button" className="au-filebtn au-berkas-layar" onClick={() => setPenuh(f)}>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" /></svg>
                                        Layar Penuh
                                    </button>
                                    {can.download ? (
                                        <a className="au-filebtn au-filebtn--green" href={f.url} target="_blank" rel="noreferrer" download title="Download" aria-label="Download">
                                            <Icon path={DOWN} />
                                        </a>
                                    ) : null}
                                    {can.delete ? (
                                        <button type="button" className="au-filebtn au-filebtn--red" onClick={() => hapusFile(f)} title="Hapus" aria-label="Hapus">
                                            <Icon path={ICON.trash} />
                                        </button>
                                    ) : null}
                                </div>

                                {edit && edit.id === f.id ? (
                                    <form className="au-berkas-edit" onSubmit={simpanCatatan}>
                                        <Input
                                            autoFocus
                                            value={edit.teks}
                                            maxLength={200}
                                            placeholder="Keterangan file ini, mis. Revisi ke-2"
                                            onChange={(e) => setEdit({ ...edit, teks: e.target.value })}
                                        />
                                        <div>
                                            <Btn type="submit" sm>Simpan</Btn>
                                            <Btn tone="ghost" sm onClick={() => setEdit(null)}>Batal</Btn>
                                        </div>
                                    </form>
                                ) : f.catatan ? (
                                    <button
                                        type="button"
                                        className="au-berkas-catatan is-isi"
                                        disabled={!can.edit}
                                        onClick={() => can.edit && setEdit({ id: f.id, url: f.catatanUrl, teks: f.catatan || '' })}
                                    >
                                        <span>{f.catatan}</span>
                                        {can.edit ? <Icon path={ICON.edit} /> : null}
                                    </button>
                                ) : can.edit ? (
                                    <button type="button" className="au-berkas-catatan" onClick={() => setEdit({ id: f.id, url: f.catatanUrl, teks: '' })}>
                                        <Icon path={ICON.plus} />
                                        <span>Tambah keterangan (mis. Revisi ke-2)</span>
                                    </button>
                                ) : null}
                            </div>
                        </article>
                    ))}
                </section>
            ) : (
                <div className="au-blank">
                    <Icon path={DOC} />
                    <p>Belum ada file untuk dokumen ini</p>
                </div>
            )}

            {penuh
                ? createPortal(
                      <div className="au-berkas-modal" role="dialog" aria-modal="true" aria-label={'File ' + penuh.urutan} onClick={() => setPenuh(null)}>
                          <div className="au-berkas-modal-kotak" onClick={(e) => e.stopPropagation()}>
                              <div className="au-berkas-modal-kepala">
                                  <b>
                                      File {penuh.urutan}
                                      {penuh.catatan ? ' · ' + penuh.catatan : ''}
                                  </b>
                                  <button type="button" className="au-berkas-tutup" onClick={() => setPenuh(null)} aria-label="Tutup">×</button>
                              </div>
                              <div className="au-berkas-modal-isi">
                                  {penuh.isPdf ? (
                                      <PdfViewer id={'pdf-penuh-' + penuh.id} src={penuh.url} title={'File ' + penuh.urutan} download={can.pdfDownload ? penuh.url : null} pdfjs={pdfjs} height="100%" />
                                  ) : (
                                      <img src={penuh.url} alt={'File ' + penuh.urutan} />
                                  )}
                              </div>
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </AppLayout>
    );
}
