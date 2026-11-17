import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { TAMPILAN, aturRingan, ringanAktif, terapkanTampilan } from './Glass';

/*
 * Palet perintah (Ctrl/Cmd+K): cari halaman dan aksi cepat dari keyboard.
 * Daftar halaman diambil dari menu yang sudah disaring hak aksesnya (props `menu`),
 * jadi tidak pernah menampilkan menu yang tidak boleh dibuka pengguna.
 * Dimuat menyusul (lazy) saat pertama dibuka agar bundel awal tidak membesar.
 */
export default function PaletKomando({ menu, profileUrl, logoutUrl, onTutup }) {
    const [q, setQ] = useState('');
    const [pos, setPos] = useState(0);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    const butir = useMemo(() => {
        const halaman = (menu || [])
            .filter((m) => m.url)
            .map((m) => ({
                grup: 'Halaman',
                label: m.label,
                info: m.group === 'produksi' ? 'QC-Welding' : '',
                jalan: () => (m.spa ? router.visit(m.url) : (window.location.href = m.url)),
            }));
        const aksi = [
            ...TAMPILAN.map((t) => ({ grup: 'Tampilan', label: 'Tema ' + t.nama, info: '', jalan: () => terapkanTampilan(t.k) })),
            { grup: 'Tampilan', label: ringanAktif() ? 'Matikan mode ringan' : 'Nyalakan mode ringan', info: 'Tanpa blur dan animasi latar', jalan: () => aturRingan(!ringanAktif()) },
            { grup: 'Tampilan', label: 'Kecilkan atau besarkan sidebar', info: '', jalan: () => document.getElementById('sb-mini-toggle')?.click() },
            { grup: 'Akun', label: 'Profil Saya', info: '', jalan: () => profileUrl && router.visit(profileUrl) },
            { grup: 'Akun', label: 'Logout', info: '', jalan: () => logoutUrl && router.post(logoutUrl) },
        ];
        return [...halaman, ...aksi];
    }, [menu, profileUrl, logoutUrl]);

    const tampil = useMemo(() => {
        const k = q.trim().toLowerCase();
        return k ? butir.filter((b) => (b.label + ' ' + b.info).toLowerCase().includes(k)) : butir;
    }, [q, butir]);

    useEffect(() => inputRef.current?.focus(), []);
    useEffect(() => setPos(0), [q]);
    useEffect(() => {
        listRef.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    }, [pos]);

    function pilih(b) {
        onTutup();
        // Tutup dulu supaya fokus kembali ke halaman, lalu jalankan.
        setTimeout(() => b.jalan(), 0);
    }

    function tombol(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            onTutup();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            setPos((p) => Math.min(p + 1, tampil.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setPos((p) => Math.max(p - 1, 0));
        } else if (e.key === 'Enter' && tampil[pos]) {
            e.preventDefault();
            pilih(tampil[pos]);
        }
    }

    let grupTerakhir = '';
    return (
        <div className="gl-palet-latar" onMouseDown={(e) => e.target === e.currentTarget && onTutup()}>
            <div className="gl-palet" role="dialog" aria-modal="true" aria-label="Palet perintah" onKeyDown={tombol}>
                <div className="gl-palet-cari">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4.5 4.5" /></svg>
                    <input
                        ref={inputRef}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Cari halaman atau aksi…"
                        autoComplete="off"
                        spellCheck="false"
                        aria-label="Cari halaman atau aksi"
                    />
                    <span className="gl-kbd">Esc</span>
                </div>
                <ul className="gl-palet-daftar" ref={listRef} role="listbox">
                    {tampil.length === 0 ? <li className="gl-palet-kosong">Tidak ada yang cocok dengan “{q}”.</li> : null}
                    {tampil.map((b, i) => {
                        const kepala = b.grup !== grupTerakhir;
                        grupTerakhir = b.grup;
                        return (
                            <li key={b.grup + b.label} role="presentation">
                                {kepala ? <div className="gl-palet-grup">{b.grup}</div> : null}
                                <div
                                    role="option"
                                    aria-selected={i === pos}
                                    className="gl-palet-butir"
                                    onMouseMove={() => setPos(i)}
                                    onClick={() => pilih(b)}
                                >
                                    <span>{b.label}</span>
                                    {b.info ? <small>{b.info}</small> : null}
                                </div>
                            </li>
                        );
                    })}
                </ul>
                <div className="gl-palet-kaki">
                    <span><span className="gl-kbd">↑</span> <span className="gl-kbd">↓</span> pilih</span>
                    <span><span className="gl-kbd">Enter</span> buka</span>
                </div>
            </div>
        </div>
    );
}
