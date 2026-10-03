import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON, Icon, Input } from '../../Components/Ui';
import { csrf } from '../../csrf';
import { konfirmasi } from '../../dialog';

const COLORS = ['blue', 'green', 'teal', 'purple', 'amber', 'red', 'yellow', 'slate'];
const COLOR_HEX = {
    blue: '#3b82f6', green: '#22c55e', teal: '#14b8a6', purple: '#a855f7',
    amber: '#f59e0b', red: '#ef4444', yellow: '#eab308', slate: '#94a3b8',
};
const hex = (c) => COLOR_HEX[c] || COLOR_HEX.blue;

const ROLE = { developer: 'Developer', admin: 'Admin', supervisor: 'Supervisor', mandor: 'Mandor', operator: 'Operator', visitor: 'Visitor' };
const roleLabel = (r) => ROLE[r] || r || '';

const P = {
    cari: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    pena: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    orang: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    toa: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
    kirim: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
    kalender: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    gambar: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    kamera: 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z',
    centang: 'M5 13l4 4L19 7',
};

const svg = (d) => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d={d} />
    </svg>
);

/* Tombol perapi tulisan. execCommand memang tua, tapi satu-satunya cara yang
   jalan di semua peramban tanpa pustaka besar — dan HTML hasilnya disaring
   lagi di server (App\Support\HtmlCatatan). */
const ALAT = [
    { cmd: 'bold', judul: 'Tebal (Ctrl+B)', ikon: <span style={{ fontWeight: 800 }}>B</span> },
    { cmd: 'italic', judul: 'Miring (Ctrl+I)', ikon: <span style={{ fontStyle: 'italic', fontFamily: 'Georgia,serif' }}>I</span> },
    { cmd: 'underline', judul: 'Garis bawah (Ctrl+U)', ikon: <span style={{ textDecoration: 'underline' }}>U</span> },
    { cmd: 'strikeThrough', judul: 'Dicoret', ikon: <span style={{ textDecoration: 'line-through' }}>S</span> },
    {
        cmd: 'insertUnorderedList', judul: 'Daftar berbutir',
        ikon: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path strokeLinecap="round" strokeWidth="2" d="M8 6h13M8 12h13M8 18h13" /><circle cx="4" cy="6" r="1.4" fill="currentColor" stroke="none" /><circle cx="4" cy="12" r="1.4" fill="currentColor" stroke="none" /><circle cx="4" cy="18" r="1.4" fill="currentColor" stroke="none" /></svg>
        ),
    },
    {
        cmd: 'insertOrderedList', judul: 'Daftar bernomor',
        ikon: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path strokeLinecap="round" strokeWidth="2" d="M8 6h13M8 12h13M8 18h13" /><text x="1" y="8" fontSize="7" fill="currentColor" stroke="none">1</text><text x="1" y="14.5" fontSize="7" fill="currentColor" stroke="none">2</text><text x="1" y="21" fontSize="7" fill="currentColor" stroke="none">3</text></svg>
        ),
    },
    { cmd: 'outdent', judul: 'Kurangi menjorok', ikon: svg('M20 6H9M20 12h-8M20 18H9M7 9l-3 3 3 3') },
    { cmd: 'indent', judul: 'Menjorok ke dalam', ikon: svg('M20 6H9M20 12h-8M20 18H9M4 9l3 3-3 3') },
    { cmd: 'justifyLeft', judul: 'Rata kiri', ikon: svg('M4 6h16M4 12h10M4 18h13') },
    { cmd: 'justifyCenter', judul: 'Rata tengah', ikon: svg('M4 6h16M7 12h10M6 18h12') },
    { cmd: 'justifyRight', judul: 'Rata kanan', ikon: svg('M4 6h16M10 12h10M7 18h13') },
    { cmd: 'removeFormat', judul: 'Bersihkan format', ikon: svg('M6 5h12M9 5l-2 14M15 5l-1 7M14 20l6-6M14 14l6 6') },
];

function daysLeft(due) {
    if (!due) return null;
    const d = new Date(String(due).slice(0, 10) + 'T00:00:00');
    const t = new Date();
    t.setHours(0, 0, 0, 0);
    return Math.round((d - t) / 86400000);
}

const fmtTgl = (v) => new Date(v).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

function dueLabel(due) {
    const d = daysLeft(due);
    if (d === null) return '';
    if (d < -1) return `Terlambat ${Math.abs(d)} hari`;
    if (d === -1) return 'Terlambat 1 hari';
    if (d === 0) return 'Deadline hari ini!';
    if (d === 1) return 'Besok';
    if (d <= 7) return `${d} hari lagi`;
    return fmtTgl(String(due).slice(0, 10) + 'T00:00:00');
}

function dueTone(due, done) {
    if (done) return 'is-done';
    const d = daysLeft(due);
    if (d < 0) return 'is-lewat';
    if (d === 0) return 'is-hariini';
    if (d <= 3) return 'is-dekat';
    return 'is-biasa';
}

const PREVIEW_WARNA = { 'is-lewat': '#f87171', 'is-hariini': '#fbbf24', 'is-dekat': '#facc15', 'is-biasa': '#64748b' };

const formKosong = () => ({ title: '', content: '', due_date: '', color: 'blue', is_done: false, target_user_id: '' });

/**
 * Catatan — mengikuti Production-QC-Logging-System: bilah cari + saring +
 * "Baru", ringkasan jumlah, grid kartu berpita warna (klik untuk membuka),
 * modal dua kolom dengan editor berformat, warna label, foto bukti (unggah /
 * kamera), dan mode baca bagi penerima (hanya bisa menandai selesai).
 * Memakai endpoint JSON, bukan redirect Inertia, supaya unggah foto & centang
 * selesai tidak memuat ulang halaman.
 */
export default function NotesIndex({ listUrl, storeUrl, baseUrl, targets }) {
    const { auth } = usePage().props;
    const userId = auth.user.id;

    const [notes, setNotes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [modal, setModal] = useState(null); // null | { note }

    const load = useCallback(async (diam = false) => {
        if (!diam) setLoading(true);
        try {
            const res = await fetch(listUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (res.ok) setNotes(await res.json());
        } catch (_) {
            /* jaringan putus: biarkan daftar lama */
        }
        setLoading(false);
    }, [listUrl]);

    useEffect(() => {
        load();
        const t = setInterval(() => document.visibilityState === 'visible' && load(true), 60000);
        return () => clearInterval(t);
    }, [load]);

    const isAssigned = (n) => n.user_id !== userId && (n.target_user_id === userId || n.is_broadcast);

    const stats = useMemo(() => {
        const total = notes.length;
        const done = notes.filter((n) => n.is_done).length;
        const overdue = notes.filter((n) => n.due_date && daysLeft(n.due_date) < 0 && !n.is_done).length;
        const today = notes.filter((n) => n.due_date && daysLeft(n.due_date) === 0 && !n.is_done).length;
        return [
            { label: 'total', count: total },
            { label: 'aktif', count: total - done },
            { label: 'selesai', count: done },
            ...(overdue ? [{ label: 'terlambat', count: overdue }] : []),
            ...(today ? [{ label: 'hari ini', count: today }] : []),
        ];
    }, [notes]);

    const filtered = useMemo(() => {
        let list = notes;
        const q = search.trim().toLowerCase();
        if (q) list = list.filter((n) => n.title.toLowerCase().includes(q) || (n.content_text || '').toLowerCase().includes(q));
        if (filter === 'active') list = list.filter((n) => !n.is_done);
        if (filter === 'done') list = list.filter((n) => n.is_done);
        if (filter === 'today') list = list.filter((n) => n.due_date && daysLeft(n.due_date) === 0 && !n.is_done);
        if (filter === 'overdue') list = list.filter((n) => n.due_date && daysLeft(n.due_date) < 0 && !n.is_done);
        return list;
    }, [notes, search, filter]);

    function simpanLokal(saved, hapus = false) {
        setNotes((list) => {
            if (hapus) return list.filter((n) => n.id !== saved.id);
            return list.some((n) => n.id === saved.id) ? list.map((n) => (n.id === saved.id ? saved : n)) : [saved, ...list];
        });
    }

    return (
        <AppLayout title="Catatan" subtitle="Catat deadline, pengingat, atau hal penting">
            <div className="au-nt-bar">
                <div className="au-nt-cari">
                    {svg(P.cari)}
                    <Input value={search} placeholder="Cari catatan..." onChange={(e) => setSearch(e.target.value)} />
                </div>
                <select className="au-select" value={filter} onChange={(e) => setFilter(e.target.value)}>
                    <option value="all">Semua</option>
                    <option value="active">Aktif</option>
                    <option value="done">Selesai</option>
                    <option value="today">Hari Ini</option>
                    <option value="overdue">Terlambat</option>
                </select>
                <Btn icon={ICON.plus} onClick={() => setModal({ note: null })}>
                    Baru
                </Btn>
            </div>

            <div className="au-nt-stats">
                {stats.map((s) => (
                    <div key={s.label}>
                        <b>{s.count}</b>
                        <span>{s.label}</span>
                    </div>
                ))}
            </div>

            {loading && !notes.length ? (
                <div className="au-nt-kosong">
                    <p>Memuat catatan...</p>
                </div>
            ) : filtered.length === 0 ? (
                <div className="au-nt-kosong">
                    <div className="au-nt-kosong-ikon">{svg(P.pena)}</div>
                    <p>{search || filter !== 'all' ? 'Tidak ada catatan yang cocok' : 'Belum ada catatan'}</p>
                    {!search && filter === 'all' ? <small>Klik "Baru" untuk mulai mencatat</small> : null}
                </div>
            ) : (
                <div className="au-nt-grid">
                    {filtered.map((n) => (
                        <button type="button" key={n.id} className={'au-nt-kartu' + (n.is_done ? ' is-done' : '')} onClick={() => setModal({ note: n })}>
                            <div className="au-nt-pita" style={{ background: hex(n.color) }} />
                            {n.photo_url ? (
                                <div className="au-nt-thumb">
                                    {/* Catatan lama bisa menunjuk berkas yang sudah hilang: sembunyikan, jangan ikon rusak. */}
                                    <img src={n.photo_url} alt="" loading="lazy" onError={(e) => (e.currentTarget.parentNode.style.display = 'none')} />
                                </div>
                            ) : null}
                            <div className="au-nt-isi">
                                <div className="au-nt-judul-baris">
                                    <h3 className="au-nt-judul">{n.title}</h3>
                                    {n.is_done ? <span className="au-nt-selesai">SELESAI</span> : null}
                                </div>

                                {isAssigned(n) ? (
                                    <div>
                                        <span className="au-nt-lencana is-dari">
                                            {svg(P.orang)}
                                            {'Dari ' + roleLabel(n.user?.role) + ': ' + (n.user?.name ?? '?')}
                                        </span>
                                    </div>
                                ) : n.is_broadcast && n.user_id === userId ? (
                                    <div>
                                        <span className="au-nt-lencana is-semua">
                                            {svg(P.toa)}
                                            {n.done_count ? 'Semua User · ' + n.done_count + ' selesai' : 'Semua User'}
                                        </span>
                                    </div>
                                ) : n.target_user_id ? (
                                    <div>
                                        <span className="au-nt-lencana is-untuk">
                                            {svg(P.kirim)}
                                            {'Untuk ' + (n.target_user?.name ?? '')}
                                        </span>
                                    </div>
                                ) : null}

                                {/* content_html sudah disaring di server (HtmlCatatan). */}
                                {n.content ? <div className="au-nt-cuplik qc-isi-catatan" dangerouslySetInnerHTML={{ __html: n.content_html }} /> : null}

                                {n.due_date ? (
                                    <div>
                                        <span className={'au-nt-tenggat ' + dueTone(n.due_date, n.is_done)}>
                                            {svg(P.kalender)}
                                            {dueLabel(n.due_date)}
                                        </span>
                                    </div>
                                ) : null}

                                <p className="au-nt-waktu">{n.updated_at ? fmtTgl(n.updated_at) : ''}</p>
                            </div>
                        </button>
                    ))}
                </div>
            )}

            {modal ? (
                <ModalCatatan
                    note={modal.note}
                    readOnly={modal.note ? isAssigned(modal.note) : false}
                    targets={targets}
                    storeUrl={storeUrl}
                    baseUrl={baseUrl}
                    onClose={() => setModal(null)}
                    onSaved={simpanLokal}
                />
            ) : null}
        </AppLayout>
    );
}

function ModalCatatan({ note, readOnly, targets, storeUrl, baseUrl, onClose, onSaved }) {
    const editId = note?.id ?? null;
    const [form, setForm] = useState(() =>
        note
            ? {
                  title: note.title,
                  content: note.content_html || '',
                  due_date: note.due_date ? String(note.due_date).slice(0, 10) : '',
                  color: note.color || 'blue',
                  is_done: !!note.is_done,
                  target_user_id: note.is_broadcast ? 'all' : note.target_user_id ? String(note.target_user_id) : '',
              }
            : formKosong(),
    );
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [aktif, setAktif] = useState({});

    const [photoFile, setPhotoFile] = useState(null);
    const [photoPreview, setPhotoPreview] = useState(null);
    const [photoUrl, setPhotoUrl] = useState(note?.photo_url || null);
    const [removePhoto, setRemovePhoto] = useState(false);
    const [camera, setCamera] = useState(null); // MediaStream

    const editorRef = useRef(null);
    const titleRef = useRef(null);
    const videoRef = useRef(null);
    const fileRef = useRef(null);
    const camInputRef = useRef(null);

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    // Isi editor sekali saat modal dibuka (contentEditable tidak dikendalikan React).
    useEffect(() => {
        if (editorRef.current) editorRef.current.innerHTML = form.content || '';
        titleRef.current?.focus();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const esc = (e) => e.key === 'Escape' && tutup();
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    });

    useEffect(() => {
        if (camera && videoRef.current) videoRef.current.srcObject = camera;
    }, [camera]);

    // Lepas kamera & URL pratinjau saat modal ditutup.
    useEffect(() => () => photoPreview && URL.revokeObjectURL(photoPreview), [photoPreview]);
    useEffect(() => () => camera && camera.getTracks().forEach((t) => t.stop()), [camera]);

    function tutup() {
        if (saving) return;
        onClose();
    }

    /* Peramban meninggalkan bungkus kosong (<br>, <div><br></div>) di editor
       yang sudah dihapus isinya — itu bukan catatan. */
    function rekamIsi() {
        const ed = editorRef.current;
        if (!ed) return;
        const kosong = ed.textContent.trim() === '' && !ed.querySelector('img, li');
        if (kosong && ed.innerHTML !== '') ed.innerHTML = '';
        set('content', kosong ? '' : ed.innerHTML);
        segarkanAktif();
    }

    function segarkanAktif() {
        const k = {};
        ALAT.forEach((a) => {
            try {
                k[a.cmd] = document.queryCommandState(a.cmd);
            } catch (_) {
                k[a.cmd] = false;
            }
        });
        setAktif(k);
    }

    function perintah(cmd) {
        if (readOnly || !editorRef.current) return;
        editorRef.current.focus();
        try {
            document.execCommand(cmd, false, null);
        } catch (_) {
            /* perintah tak didukung */
        }
        rekamIsi();
    }

    /* Tempelan masuk sebagai teks polos: salinan dari Word/web membawa gaya &
       tag yang tidak dipakai di sini. */
    function tempelPolos(e) {
        e.preventDefault();
        const teks = e.clipboardData?.getData('text/plain') || '';
        try {
            document.execCommand('insertText', false, teks);
        } catch (_) {
            /* abaikan */
        }
        rekamIsi();
    }

    function pilihBerkas(e) {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            setErrors((x) => ({ ...x, photo: 'Foto terlalu besar, maksimal 5MB' }));
            return;
        }
        setErrors(({ photo, ...x }) => x);
        setPhotoFile(file);
        setPhotoPreview(URL.createObjectURL(file));
        setRemovePhoto(false);
    }

    function hapusFoto() {
        setPhotoFile(null);
        setPhotoPreview(null);
        setRemovePhoto(!!photoUrl || removePhoto);
        setPhotoUrl(null);
    }

    async function bukaKamera() {
        try {
            setCamera(await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }));
        } catch (_) {
            // Tanpa izin / tanpa https: pakai pemilih berkas kamera bawaan HP.
            camInputRef.current?.click();
        }
    }

    function jepret() {
        const v = videoRef.current;
        if (!v) return;
        const c = document.createElement('canvas');
        c.width = v.videoWidth;
        c.height = v.videoHeight;
        c.getContext('2d').drawImage(v, 0, 0);
        c.toBlob(
            (blob) => {
                if (!blob) return;
                setPhotoFile(new File([blob], 'kamera.jpg', { type: 'image/jpeg' }));
                setPhotoPreview(URL.createObjectURL(blob));
                setRemovePhoto(false);
                setCamera(null);
            },
            'image/jpeg',
            0.88,
        );
    }

    async function kirim(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body,
        });
        return res;
    }

    async function save() {
        setErrors({});
        if (!readOnly && !form.title.trim()) {
            setErrors({ title: 'Judul wajib diisi' });
            return;
        }
        setSaving(true);

        const fd = new FormData();
        // Laravel tidak membaca multipart pada PUT, jadi dipalsukan lewat POST.
        if (editId) fd.append('_method', 'PUT');
        if (readOnly) {
            // Penerima hanya mengubah status selesai miliknya.
            fd.append('is_done', form.is_done ? '1' : '0');
        } else {
            fd.append('title', form.title.trim());
            fd.append('content', (form.content || '').trim());
            fd.append('color', form.color);
            fd.append('is_done', form.is_done ? '1' : '0');
            if (form.due_date) fd.append('due_date', form.due_date);
            if (form.target_user_id) fd.append('target_user_id', form.target_user_id);
            if (photoFile) fd.append('photo', photoFile);
            if (removePhoto) fd.append('remove_photo', '1');
        }

        try {
            const res = await kirim(editId ? baseUrl + '/' + editId : storeUrl, fd);
            if (res.ok) {
                onSaved(await res.json());
                setSaving(false);
                onClose();
                return;
            }
            const err = await res.json().catch(() => ({}));
            if (err.errors) setErrors(Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])));
        } catch (_) {
            /* jaringan putus */
        }
        setSaving(false);
    }

    async function hapus() {
        if (!(await konfirmasi('Catatan ini akan dihapus permanen.', { title: 'Hapus Catatan?' }))) return;
        const res = await kirim(baseUrl + '/' + editId, new URLSearchParams({ _method: 'DELETE' }));
        if (res.ok) {
            onSaved({ id: editId }, true);
            onClose();
        }
    }

    const tone = form.due_date ? dueTone(form.due_date, false) : null;
    const fotoTampil = photoPreview || photoUrl;

    return (
        <div className="au-modal-backdrop" onClick={tutup}>
            <div className="au-nt-modal" onClick={(e) => e.stopPropagation()}>
                <div className="au-nt-kepala">
                    <div className="au-nt-titik" style={{ background: hex(form.color) }} />
                    <h2>{editId ? 'Edit Catatan' : 'Catatan Baru'}</h2>
                    <button type="button" className="au-nt-tutup" onClick={tutup} aria-label="Tutup">
                        <Icon path={ICON.close} />
                    </button>
                </div>

                <div className="au-nt-badan">
                    {readOnly ? (
                        <div className="au-nt-info">
                            {svg(P.info)}
                            <p>
                                Catatan dari{' '}
                                <b>
                                    {note.user?.role ? roleLabel(note.user.role) + ' — ' : ''}
                                    {note.user?.name || ''}
                                </b>
                                . Kamu hanya bisa menandai selesai.
                            </p>
                        </div>
                    ) : null}

                    <div className="au-nt-kolom">
                        <div className="au-nt-kiri">
                            <div>
                                <label className="au-nt-lbl">Judul</label>
                                <input
                                    ref={titleRef}
                                    className={'au-input' + (errors.title ? ' has-error' : '')}
                                    value={form.title}
                                    placeholder="Judul catatan..."
                                    maxLength={255}
                                    disabled={readOnly}
                                    onChange={(e) => set('title', e.target.value)}
                                />
                                {errors.title ? <span className="au-error">{errors.title}</span> : null}
                            </div>

                            {!readOnly ? (
                                <div>
                                    <label className="au-nt-lbl">Kirim Ke</label>
                                    <select className="au-select" value={form.target_user_id} onChange={(e) => set('target_user_id', e.target.value)}>
                                        <option value="">— Catatan pribadi —</option>
                                        <option value="all">— Semua User —</option>
                                        {targets.map((t) => (
                                            <option key={t.value} value={String(t.value)}>
                                                {t.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.target_user_id ? <span className="au-error">{errors.target_user_id}</span> : null}
                                </div>
                            ) : null}

                            <div>
                                <label className="au-nt-lbl">Deadline</label>
                                <div className="au-nt-tgl">
                                    {svg(P.kalender)}
                                    <input type="date" className="au-input" value={form.due_date} disabled={readOnly} onChange={(e) => set('due_date', e.target.value)} />
                                </div>
                                {form.due_date ? (
                                    <p className="au-nt-pratinjau-tgl" style={{ color: PREVIEW_WARNA[tone] }}>
                                        {dueLabel(form.due_date)}
                                    </p>
                                ) : null}
                            </div>

                            {editId ? (
                                <label className="au-nt-selesai-box">
                                    <input type="checkbox" checked={form.is_done} onChange={(e) => set('is_done', e.target.checked)} />
                                    <div>
                                        <p>Tandai selesai</p>
                                        <small>{form.is_done ? 'Catatan ini sudah selesai' : 'Catatan masih aktif'}</small>
                                    </div>
                                </label>
                            ) : null}
                        </div>

                        <div className="au-nt-kanan">
                            <div className="au-nt-tulis">
                                <label className="au-nt-lbl">Catatan</label>
                                <div className={'au-nt-lembar' + (readOnly ? ' is-baca' : '')}>
                                    {!readOnly ? (
                                        <div className="au-nt-alat">
                                            {ALAT.map((a) => (
                                                <button
                                                    type="button"
                                                    key={a.cmd}
                                                    title={a.judul}
                                                    aria-label={a.judul}
                                                    aria-pressed={aktif[a.cmd] ? 'true' : 'false'}
                                                    className={aktif[a.cmd] ? 'is-aktif' : ''}
                                                    onMouseDown={(e) => e.preventDefault()}
                                                    onClick={() => perintah(a.cmd)}
                                                >
                                                    {a.ikon}
                                                </button>
                                            ))}
                                        </div>
                                    ) : null}
                                    <div
                                        ref={editorRef}
                                        className="au-nt-editor qc-editor qc-isi-catatan"
                                        contentEditable={!readOnly}
                                        suppressContentEditableWarning
                                        data-placeholder="Isi catatan, detail pekerjaan, hal yang perlu diingat..."
                                        onInput={rekamIsi}
                                        onBlur={rekamIsi}
                                        onKeyUp={segarkanAktif}
                                        onMouseUp={segarkanAktif}
                                        onPaste={tempelPolos}
                                    />
                                </div>
                                {errors.content ? <span className="au-error">{errors.content}</span> : null}
                            </div>

                            {!readOnly ? (
                                <div>
                                    <label className="au-nt-lbl">Warna Label</label>
                                    <div className="au-nt-warna">
                                        {COLORS.map((c) => (
                                            <button type="button" key={c} title={c} className={form.color === c ? 'is-aktif' : ''} style={{ background: hex(c) }} onClick={() => set('color', c)}>
                                                {form.color === c ? svg(P.centang) : null}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    </div>

                    {!readOnly || fotoTampil ? (
                        <div className="au-nt-foto-blok">
                            <label className="au-nt-lbl">Foto Bukti</label>
                            {fotoTampil ? (
                                <div className="au-nt-foto">
                                    <img src={fotoTampil} alt="" />
                                    {!readOnly ? (
                                        <button type="button" onClick={hapusFoto} aria-label="Hapus foto">
                                            <Icon path={ICON.close} />
                                        </button>
                                    ) : null}
                                </div>
                            ) : null}
                            {!readOnly && !fotoTampil ? (
                                <div className="au-nt-unggah">
                                    <label>
                                        {svg(P.gambar)}
                                        <span>Upload Foto</span>
                                        <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={pilihBerkas} />
                                    </label>
                                    <button type="button" onClick={bukaKamera}>
                                        {svg(P.kamera)}
                                        <span>Kamera</span>
                                    </button>
                                    <input ref={camInputRef} type="file" accept="image/*" capture="environment" onChange={pilihBerkas} />
                                </div>
                            ) : null}
                            {errors.photo ? <span className="au-error">{errors.photo}</span> : null}
                            {!readOnly ? <p className="au-nt-catatan-kecil">Maks. 5MB · JPG, PNG, WebP</p> : null}
                        </div>
                    ) : null}
                </div>

                {camera ? (
                    <div className="au-nt-kamera">
                        <div className="au-nt-kamera-kepala">
                            <span>Ambil Foto</span>
                            <button type="button" className="au-nt-tutup" onClick={() => setCamera(null)} aria-label="Tutup kamera">
                                <Icon path={ICON.close} />
                            </button>
                        </div>
                        <video ref={videoRef} autoPlay playsInline muted />
                        <div className="au-nt-jepret">
                            <button type="button" onClick={jepret} aria-label="Jepret">
                                <span />
                            </button>
                        </div>
                    </div>
                ) : null}

                <div className="au-nt-kaki">
                    {editId && !readOnly ? (
                        <button type="button" className="au-nt-hapus" onClick={hapus}>
                            <Icon path={ICON.trash} />
                            Hapus
                        </button>
                    ) : (
                        <span />
                    )}
                    <div>
                        <button type="button" className="au-nt-batal" onClick={tutup}>
                            Batal
                        </button>
                        <button type="button" className="au-nt-simpan" disabled={saving} style={{ background: hex(form.color) }} onClick={save}>
                            {saving ? 'Menyimpan...' : editId ? 'Simpan' : 'Buat'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
