import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * Dropdown produk bergrup yang bisa dicari — pengganti TomSelect di aplikasi
 * acuan. Daftar selalu terbuka ke BAWAH di dalam halaman (tinggi maks 300px,
 * digulir), bukan popup <select> bawaan browser yang bisa menutupi kepala
 * halaman. Mendukung keyboard: ↑ ↓ pilih, Enter pakai, Esc tutup.
 *
 * groups: [{ label, options: [{ value, label }] }]
 */
export default function PilihProduk({ groups, value, onChange, placeholder = '-- Pilih Produk --', error }) {
    const [buka, setBuka] = useState(false);
    const [cari, setCari] = useState('');
    const [aktif, setAktif] = useState(0);
    const akar = useRef(null);
    const daftar = useRef(null);
    const kotakCari = useRef(null);

    const semua = useMemo(() => groups.flatMap((g) => g.options.map((o) => ({ ...o, grup: g.label }))), [groups]);
    const terpilih = semua.find((o) => String(o.value) === String(value)) || null;

    const tersaring = useMemo(() => {
        const q = cari.trim().toLowerCase();
        return q ? semua.filter((o) => (o.grup + ' ' + o.label).toLowerCase().includes(q)) : semua;
    }, [semua, cari]);

    // Tutup bila klik di luar.
    useEffect(() => {
        if (!buka) return undefined;
        const luar = (e) => akar.current && !akar.current.contains(e.target) && setBuka(false);
        document.addEventListener('mousedown', luar);
        document.addEventListener('touchstart', luar);
        return () => {
            document.removeEventListener('mousedown', luar);
            document.removeEventListener('touchstart', luar);
        };
    }, [buka]);

    // Saat dibuka: fokus ke kotak cari & sorot pilihan sekarang.
    useEffect(() => {
        if (!buka) return;
        const i = tersaring.findIndex((o) => String(o.value) === String(value));
        setAktif(i >= 0 ? i : 0);
        setTimeout(() => kotakCari.current?.focus(), 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [buka]);

    useEffect(() => setAktif(0), [cari]);

    // Pilihan yang disorot selalu terlihat di daftar.
    useEffect(() => {
        if (!buka || !daftar.current) return;
        daftar.current.querySelector('[data-aktif="1"]')?.scrollIntoView({ block: 'nearest' });
    }, [aktif, buka]);

    function pilih(o) {
        onChange(String(o.value));
        setBuka(false);
        setCari('');
    }

    function tombol(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setAktif((i) => Math.min(i + 1, tersaring.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setAktif((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (tersaring[aktif]) pilih(tersaring[aktif]);
        } else if (e.key === 'Escape') {
            setBuka(false);
        }
    }

    let grupSebelum = null;

    return (
        <div className={'au-pp' + (buka ? ' is-buka' : '')} ref={akar}>
            <button
                type="button"
                className={'au-pp-tombol' + (error ? ' has-error' : '')}
                onClick={() => setBuka((b) => !b)}
                onKeyDown={(e) => {
                    if (!buka && (e.key === 'ArrowDown' || e.key === 'Enter')) {
                        e.preventDefault();
                        setBuka(true);
                    }
                }}
                aria-haspopup="listbox"
                aria-expanded={buka}
            >
                <span className={terpilih ? '' : 'is-kosong'}>{terpilih ? terpilih.grup + ' — ' + terpilih.label : placeholder}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {buka ? (
                <div className="au-pp-panel">
                    <div className="au-pp-cari">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input ref={kotakCari} value={cari} placeholder="Cari produk / seri..." onChange={(e) => setCari(e.target.value)} onKeyDown={tombol} />
                    </div>
                    <div className="au-pp-daftar" ref={daftar} role="listbox">
                        {tersaring.length ? (
                            tersaring.map((o, i) => {
                                const kepala = o.grup !== grupSebelum;
                                grupSebelum = o.grup;
                                return [
                                    kepala ? (
                                        <div className="au-pp-grup" key={'g-' + o.grup + i}>
                                            {o.grup}
                                        </div>
                                    ) : null,
                                    <div
                                        key={o.value}
                                        role="option"
                                        aria-selected={String(o.value) === String(value)}
                                        data-aktif={i === aktif ? '1' : '0'}
                                        className={'au-pp-opsi' + (i === aktif ? ' is-aktif' : '') + (String(o.value) === String(value) ? ' is-pilih' : '')}
                                        onMouseEnter={() => setAktif(i)}
                                        onMouseDown={(e) => e.preventDefault()}
                                        onClick={() => pilih(o)}
                                    >
                                        {o.label}
                                    </div>,
                                ];
                            })
                        ) : (
                            <div className="au-pp-kosong">Tidak ada produk yang cocok</div>
                        )}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
