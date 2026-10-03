import { Fragment, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON, Icon, Select } from '../../Components/Ui';

const LOCK =
    'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z';
const EYE = [
    'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
];
const INFO = 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';

function Paths({ paths }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            {paths.map((d) => (
                <path key={d} d={d} />
            ))}
        </svg>
    );
}

/**
 * Hak Akses Menu & Aksi — mengikuti Production-QC-Logging-System:
 * penjelasan yang bisa dilipat, pemilih role di HP, pencarian, saklar massal
 * per menu & per kolom, tombol "Bawaan", titik penanda sel yang berbeda dari
 * bawaan, aksi redup bila menu induknya dimatikan, baris bergembok untuk menu
 * khusus Developer, dan bilah simpan lengket dengan hitungan perubahan.
 *
 * Tambahan khas asata: mode per DEPARTEMEN (state datar "key").
 */
export default function PermissionsIndex({
    mode,
    rows,
    roles,
    roleMeta,
    departments,
    selectedDept,
    state,
    bawaan,
    indexUrl,
    updateUrl,
}) {
    const isDept = mode === 'department';
    const kolom = isDept ? ['__dept'] : roles;
    const sel = (role, key) => (isDept ? key : role + '|' + key);

    const [nilai, setNilai] = useState(() => ({ ...state }));
    const [cari, setCari] = useState('');
    const [roleHp, setRoleHp] = useState(kolom[0]);
    const [busy, setBusy] = useState(false);

    // Hitung perubahan dibanding keadaan tersimpan.
    const jumlahUbah = useMemo(
        () => Object.keys(state).filter((k) => Boolean(nilai[k]) !== Boolean(state[k])).length,
        [nilai, state],
    );

    const q = cari.trim().toLowerCase();
    const barisTampil = useMemo(
        () =>
            rows
                .map((row) => {
                    if (!q) return row;
                    const cocokMenu = row.label.toLowerCase().includes(q);
                    const perms = row.perms.filter(
                        (p) =>
                            cocokMenu ||
                            (row.label + ' ' + p.label + ' ' + p.key).toLowerCase().includes(q),
                    );
                    return perms.length ? { ...row, perms } : null;
                })
                .filter(Boolean),
        [rows, q],
    );

    function set(keys, v) {
        setNilai((n) => {
            const b = { ...n };
            keys.forEach((k) => {
                b[k] = v;
            });
            return b;
        });
    }

    // Saklar massal: kalau semua sudah menyala → matikan semua, selain itu nyalakan semua.
    function massal(keys) {
        const semuaNyala = keys.every((k) => nilai[k]);
        set(keys, !semuaNyala);
    }

    function kunciRow(row, role) {
        return row.perms.map((p) => sel(role, p.key));
    }

    function kunciKolom(role) {
        return rows.filter((r) => !r.locked).flatMap((r) => kunciRow(r, role));
    }

    function keBawaan(keys) {
        setNilai((n) => {
            const b = { ...n };
            keys.forEach((k) => {
                b[k] = Boolean(bawaan[k]);
            });
            return b;
        });
    }

    function simpan(e) {
        e.preventDefault();
        setBusy(true);
        const allowed = {};
        Object.keys(nilai).forEach((k) => {
            if (!nilai[k]) return;
            if (isDept) {
                allowed[k] = 1;
            } else {
                const [role, key] = k.split('|');
                (allowed[role] ||= {})[key] = 1;
            }
        });
        router.post(
            updateUrl,
            { allowed, department: isDept ? selectedDept : null },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    }

    function gantiMode(value) {
        router.get(indexUrl, value ? { department: value } : {}, { preserveScroll: true });
    }

    const labelKolom = (role) =>
        isDept ? selectedDept : (roleMeta[role] && roleMeta[role].label) || role;

    return (
        <AppLayout
            title="Hak Akses Menu & Aksi"
            subtitle={isDept ? 'Mode departemen: ' + selectedDept : 'Atur menu & aksi yang bisa diakses tiap role'}
        >
            <form onSubmit={simpan} className="au-hak">
                <details className="au-hak-info">
                    <summary>
                        <Icon path={INFO} />
                        <span>Cara kerja halaman ini</span>
                    </summary>
                    <div>
                        <p>
                            <b>Lihat</b> = boleh membuka menu. Baris di bawahnya = izin aksi (Tambah/Edit/Hapus/Export).
                            Yang dimatikan akan <b>disembunyikan</b> dari layar dan URL-nya <b>ditolak (403)</b>.
                        </p>
                        <p>
                            Mematikan <b>Lihat</b> otomatis mematikan semua aksi di menu itu — aksi hanya berlaku bila
                            menunya boleh dibuka. Aksi yang terkena aturan ini ditampilkan <b>redup</b>.
                        </p>
                        <p>
                            Titik <span className="au-hak-titik" /> berarti sel itu <b>sudah diubah dari bawaan</b>.
                            Tombol <b>Bawaan</b> mengembalikannya.
                        </p>
                        <p>
                            Baris <b>bergembok</b> tidak bisa diberikan ke role lain: keduanya mengatur sistem izin itu
                            sendiri, jadi memberikannya sama saja membiarkan role itu memberi dirinya semua izin.
                        </p>
                        <p>
                            Khusus <b>Manajemen</b>: saklar di sini menentukan boleh mengelola pengguna atau tidak.
                            <b> Siapa</b> yang boleh dikelola tetap dibatasi terpisah — Admin hanya Operator, Visitor,
                            Supervisor &amp; Mandor.
                        </p>
                        <p>
                            Mode <b>departemen</b>: izin role dan izin departemen keduanya harus mengizinkan. Role{' '}
                            <b>Developer</b> tidak ada di tabel karena selalu berakses penuh.
                        </p>
                    </div>
                </details>

                <div className="au-hak-alat">
                    <div className="au-hak-cari">
                        <Icon path={ICON.search} />
                        <input
                            type="search"
                            value={cari}
                            autoComplete="off"
                            placeholder="Cari menu atau aksi — mis. hapus, gambar, laporan"
                            onChange={(e) => setCari(e.target.value)}
                        />
                    </div>
                    {departments && departments.length ? (
                        <div className="au-hak-mode">
                            <Select
                                value={selectedDept || ''}
                                placeholder="Mode role"
                                options={departments}
                                onChange={(e) => gantiMode(e.target.value)}
                            />
                        </div>
                    ) : null}
                </div>

                {!isDept ? (
                    <div className="au-hak-pil">
                        <p>Role yang sedang diatur</p>
                        <div>
                            {roles.map((r) => (
                                <button
                                    type="button"
                                    key={r}
                                    className={'au-hak-pil-btn au-warna-' + (roleMeta[r]?.warna || 'slate') + (roleHp === r ? ' is-aktif' : '')}
                                    onClick={() => setRoleHp(r)}
                                >
                                    {roleMeta[r]?.label || r}
                                    <span>{roleMeta[r]?.user ?? 0}</span>
                                </button>
                            ))}
                        </div>
                        <small>{roleMeta[roleHp]?.ket}</small>
                    </div>
                ) : null}

                <div className="au-hak-tabel">
                    <table>
                        <thead>
                            <tr>
                                <th className="au-hak-th-menu">Menu / Aksi</th>
                                {kolom.map((r) => (
                                    <th key={r} className={'au-hak-col' + (r === roleHp || isDept ? ' is-hp' : '')}>
                                        <span className="au-hak-col-judul">
                                            <i className={'au-warna-' + (isDept ? 'blue' : roleMeta[r]?.warna || 'slate')} />
                                            {labelKolom(r)}
                                        </span>
                                        {!isDept ? (
                                            <small>{roleMeta[r]?.user ?? 0} pengguna</small>
                                        ) : null}
                                        <span className="au-hak-col-aksi">
                                            <button type="button" onClick={() => massal(kunciKolom(r))} title={'Nyalakan/matikan seluruh kolom ' + labelKolom(r)}>
                                                Semua
                                            </button>
                                            <button type="button" onClick={() => keBawaan(kunciKolom(r))} title={'Kembalikan kolom ' + labelKolom(r) + ' ke bawaan'}>
                                                Bawaan
                                            </button>
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {barisTampil.length ? (
                                barisTampil.map((row) => (
                                    <Fragment key={row.key}>
                                        <tr className="au-hak-grup">
                                            <th scope="row">
                                                <span>
                                                    {row.paths.length ? <Paths paths={row.paths} /> : null}
                                                    {row.label}
                                                    {row.locked ? (
                                                        <span className="au-hak-gembok" title="Terkunci untuk Developer">
                                                            <Icon path={LOCK} />
                                                        </span>
                                                    ) : null}
                                                    {row.shared && isDept ? <em>dipakai bersama</em> : null}
                                                </span>
                                            </th>
                                            {kolom.map((r) => (
                                                <td key={r} className={'au-hak-col' + (r === roleHp || isDept ? ' is-hp' : '')}>
                                                    {!row.locked ? (
                                                        <button type="button" className="au-hak-semua" onClick={() => massal(kunciRow(row, r))}>
                                                            semua
                                                        </button>
                                                    ) : null}
                                                </td>
                                            ))}
                                        </tr>
                                        {row.perms.map((perm) => (
                                            <tr key={row.key + perm.key} className={perm.is_view ? 'au-hak-lihat' : 'au-hak-aksi'}>
                                                <th scope="row">
                                                    <span>
                                                        {perm.is_view ? <Paths paths={EYE} /> : null}
                                                        {perm.label}
                                                    </span>
                                                </th>
                                                {kolom.map((r) => {
                                                    if (row.locked) {
                                                        return (
                                                            <td key={r} className={'au-hak-col' + (r === roleHp || isDept ? ' is-hp' : '')}>
                                                                <span className="au-hak-dev" title="Hanya Developer. Memberikan menu ini berarti role tsb bisa memberi dirinya semua izin.">
                                                                    <Icon path={LOCK} /> Dev
                                                                </span>
                                                            </td>
                                                        );
                                                    }
                                                    const k = sel(r, perm.key);
                                                    const kunciLihat = row.perms.find((p) => p.is_view);
                                                    const indukMati = !perm.is_view && kunciLihat && !nilai[sel(r, kunciLihat.key)];
                                                    const beda = Boolean(nilai[k]) !== Boolean(bawaan[k]);
                                                    return (
                                                        <td key={r} className={'au-hak-col' + (r === roleHp || isDept ? ' is-hp' : '') + (indukMati ? ' is-redup' : '')}>
                                                            <label className="au-saklar" title={indukMati ? 'Tidak berlaku — menu ini tidak boleh dibuka' : ''}>
                                                                <input
                                                                    type="checkbox"
                                                                    checked={Boolean(nilai[k])}
                                                                    aria-label={labelKolom(r) + ' — ' + row.label + ': ' + perm.label}
                                                                    onChange={() => set([k], !nilai[k])}
                                                                />
                                                                <span />
                                                                {beda ? <i className="au-hak-titik" title="Berbeda dari bawaan" /> : null}
                                                            </label>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </Fragment>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={kolom.length + 1} className="au-hak-kosong">
                                        Tidak ada menu atau aksi yang cocok.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="au-hak-bilah">
                    <div>
                        <p>{jumlahUbah ? jumlahUbah + ' perubahan belum disimpan.' : 'Belum ada perubahan.'}</p>
                        <button type="button" onClick={() => kolom.forEach((r) => keBawaan(kunciKolom(r)))}>
                            Kembalikan semua ke bawaan
                        </button>
                    </div>
                    <div>
                        {jumlahUbah ? (
                            <Btn tone="ghost" onClick={() => setNilai({ ...state })} disabled={busy}>
                                Batalkan
                            </Btn>
                        ) : null}
                        <Btn type="submit" icon={ICON.check} disabled={busy || !jumlahUbah}>
                            {busy ? 'Menyimpan...' : 'Simpan'}
                        </Btn>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
