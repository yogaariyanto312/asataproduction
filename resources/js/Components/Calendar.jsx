import { useEffect, useRef, useState } from 'react';
import { Icon, ICON } from './Ui';
import { csrf } from '../csrf';
import { konfirmasi, peringatan } from '../dialog';

const DOW = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const CAL_ICON = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';
const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function tglPanjang(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return d + ' ' + BULAN[m - 1] + ' ' + y;
}

/**
 * Kartu kalender dashboard — mengikuti Production-QC-Logging-System: tombol
 * pindah bulan (+ "Hari ini" saat di bulan lain), tanggal bulat, titik penanda
 * (libur merah, agenda kuning, purnama putih, bulan baru gelap), tooltip yang
 * dirapatkan ke sisi untuk kolom pinggir, legenda, dan modal agenda (tambah &
 * hapus). Bulan lain diambil utuh dari server (api.dashboard.calendar).
 */
export default function Calendar({ initial, calendarUrl, storeUrl, eventBase }) {
    const [cal, setCal] = useState(initial);
    const [loading, setLoading] = useState(false);
    const [openDate, setOpenDate] = useState(null);
    const memuat = useRef(false);

    async function gantiBulan(bulan) {
        if (memuat.current) return;
        memuat.current = true;
        setLoading(true);
        try {
            const res = await fetch(calendarUrl + '?month=' + encodeURIComponent(bulan), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (res.ok) setCal(await res.json());
        } catch (_) {
            /* jaringan putus: tetap di bulan lama */
        }
        memuat.current = false;
        setLoading(false);
    }

    // Agenda tanggal tertentu diubah di tempat (tanpa memuat ulang bulan).
    function ubahAgenda(tanggal, fn) {
        setCal((c) => ({ ...c, days: c.days.map((d) => (d.date === tanggal ? { ...d, events: fn(d.events) } : d)) }));
    }

    const hariBuka = openDate ? cal.days.find((d) => d.date === openDate) : null;

    return (
        // Tanpa overflow:hidden — tooltip tanggal di baris/kolom pinggir harus
        // boleh menjulur keluar kartu, bukan terpotong garis tepinya.
        <section className="au-card au-db-cal" style={{ opacity: loading ? 0.5 : 1 }}>
            <div className="au-cal-head">
                <span className="au-cal-mark">
                    <Icon path={CAL_ICON} />
                </span>
                <div style={{ minWidth: 0, flex: 1 }}>
                    <p className="au-cal-month">{cal.label}</p>
                    <p className="au-cal-today-label">{cal.sub}</p>
                </div>
                <div className="au-cal-nav">
                    <button type="button" className="au-cal-nav-btn" data-cal-month={cal.prev} onClick={() => gantiBulan(cal.prev)} aria-label="Bulan sebelumnya" title="Bulan sebelumnya">
                        <Icon path="M15 19l-7-7 7-7" />
                    </button>
                    {!cal.isCurrent ? (
                        <button type="button" className="au-cal-nav-today" data-cal-month={cal.current} onClick={() => gantiBulan(cal.current)} title="Kembali ke bulan ini">
                            Hari ini
                        </button>
                    ) : null}
                    <button type="button" className="au-cal-nav-btn" data-cal-month={cal.next} onClick={() => gantiBulan(cal.next)} aria-label="Bulan berikutnya" title="Bulan berikutnya">
                        <Icon path="M9 5l7 7-7 7" />
                    </button>
                </div>
            </div>

            <div className="au-db-cal-body">
                <div className="au-cal">
                    {DOW.map((d, i) => (
                        <div className={'au-cal-dow' + (i >= 5 ? ' is-weekend' : '')} key={d}>
                            {d}
                        </div>
                    ))}
                </div>

                <div className="au-cal">
                    {Array.from({ length: cal.offset }, (_, i) => (
                        <div key={'k' + i} />
                    ))}
                    {cal.days.map((d) => {
                        const ada = d.events.length > 0;
                        const merah = d.weekend || !!d.holiday;
                        const cls = 'au-cal-num' + (d.today ? ' is-today' : '') + (!d.today && merah ? ' is-weekend' : '') + (!d.today && ada ? ' is-event' : '');
                        return (
                            <button type="button" className="au-cal-cell" data-cal-day={d.date} key={d.date} onClick={() => setOpenDate(d.date)}>
                                <span className={cls}>{d.day}</span>
                                <span className="au-cal-dots">
                                    {d.holiday ? <i className="au-cal-dot au-cal-dot--holiday" /> : null}
                                    {ada ? <i className="au-cal-dot au-cal-dot--event" /> : null}
                                    {d.phase === 'purnama' ? <i className="au-cal-dot au-cal-dot--purnama" /> : null}
                                    {d.phase === 'baru' ? <i className="au-cal-dot au-cal-dot--baru" /> : null}
                                </span>
                                {d.holiday || ada || d.phase ? (
                                    // Kolom paling kiri & kanan dirapatkan ke sisinya; kalau
                                    // selalu dipusatkan, tooltip Senin/Minggu terpotong layar.
                                    <span className={'au-cal-tip is-' + d.tip}>
                                        <p className={'au-cal-tip-date ' + (d.holiday ? 'is-holiday' : 'is-event')}>{d.label}</p>
                                        {d.holiday ? <p>{d.holiday}</p> : null}
                                        {d.phase ? <p className="is-fase">{d.phase === 'purnama' ? 'Bulan purnama' : 'Bulan baru'}</p> : null}
                                        {d.events.map((ev) => (
                                            <p className="is-ev" key={ev.id}>
                                                • {ev.title}
                                            </p>
                                        ))}
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}
                </div>

                <div className="au-cal-legend">
                    <span>
                        <i className="lg-today" />
                        Hari ini
                    </span>
                    <span>
                        <i className="lg-holiday" />
                        Hari libur nasional
                    </span>
                    <span>
                        <i className="lg-event" />
                        Ada agenda
                    </span>
                    <span>
                        <i className="lg-fase" />
                        Purnama / bulan baru
                    </span>
                    <span className="lg-hint">Klik tanggal untuk tambah agenda</span>
                </div>
            </div>

            {hariBuka ? (
                <ModalAgenda
                    hari={hariBuka}
                    canManage={cal.canManage}
                    storeUrl={storeUrl}
                    eventBase={eventBase}
                    onClose={() => setOpenDate(null)}
                    onChange={(fn) => ubahAgenda(hariBuka.date, fn)}
                />
            ) : null}
        </section>
    );
}

function ModalAgenda({ hari, canManage, storeUrl, eventBase, onClose, onChange }) {
    const [title, setTitle] = useState('');
    const [desc, setDesc] = useState('');
    const [busy, setBusy] = useState(false);
    const titleRef = useRef(null);

    useEffect(() => {
        setTimeout(() => titleRef.current?.focus(), 50);
        const esc = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [onClose]);

    async function simpan() {
        const t = title.trim();
        if (!t) {
            titleRef.current?.focus();
            return;
        }
        setBusy(true);
        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ event_date: hari.date, title: t, description: desc.trim() || null }),
            });
            if (res.ok) {
                const data = await res.json();
                onChange((evs) => [...evs, data.event]);
                setTitle('');
                setDesc('');
            } else {
                peringatan('Gagal menyimpan agenda.');
            }
        } catch (_) {
            peringatan('Koneksi bermasalah.');
        }
        setBusy(false);
    }

    async function hapus(id) {
        if (!(await konfirmasi('Agenda ini akan dihapus permanen.', { title: 'Hapus Agenda?', okText: 'Ya, Hapus' }))) return;
        try {
            const res = await fetch(eventBase + '/' + id, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            if (res.ok) onChange((evs) => evs.filter((e) => e.id !== id));
            else peringatan('Gagal menghapus agenda.');
        } catch (_) {
            peringatan('Koneksi bermasalah.');
        }
    }

    return (
        <div className="au-modal-backdrop" onClick={onClose}>
            <div className="au-db-modal" onClick={(e) => e.stopPropagation()}>
                <div className="au-db-modal-kepala is-biru">
                    <div>
                        <h3>Agenda</h3>
                        <p>{tglPanjang(hari.date)}</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup">
                        <Icon path={ICON.close} />
                    </button>
                </div>

                <div className="au-db-agenda-list">
                    {hari.events.length ? (
                        hari.events.map((ev) => (
                            <div className="au-db-agenda" key={ev.id}>
                                <span className="au-db-agenda-dot" />
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <p className="au-db-agenda-judul">{ev.title}</p>
                                    {ev.description ? <p className="au-db-agenda-ket">{ev.description}</p> : null}
                                    {ev.by ? <p className="au-db-agenda-oleh">oleh {ev.by}</p> : null}
                                </div>
                                {ev.can_delete && canManage ? (
                                    <button type="button" className="au-db-agenda-hapus" title="Hapus" onClick={() => hapus(ev.id)}>
                                        <Icon path={ICON.trash} />
                                    </button>
                                ) : null}
                            </div>
                        ))
                    ) : (
                        <p className="au-db-agenda-kosong">{canManage ? 'Belum ada agenda. Tambahkan di bawah.' : 'Belum ada agenda.'}</p>
                    )}
                </div>

                {canManage ? (
                    <div className="au-db-agenda-form">
                        <input
                            ref={titleRef}
                            className="au-input"
                            maxLength={150}
                            placeholder="Judul agenda (mis. Meeting, Kirim barang...)"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    simpan();
                                }
                            }}
                        />
                        <textarea className="au-textarea" rows={2} maxLength={1000} placeholder="Keterangan (opsional)..." value={desc} onChange={(e) => setDesc(e.target.value)} />
                        <button type="button" className="au-db-agenda-simpan" disabled={busy} onClick={simpan}>
                            {busy ? 'Menyimpan...' : '+ Tambah Agenda'}
                        </button>
                    </div>
                ) : null}
            </div>
        </div>
    );
}
