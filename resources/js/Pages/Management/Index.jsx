import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, Check, DeleteButton, ICON, Icon, IconBtn, Input } from '../../Components/Ui';

const URUTAN = ['developer', 'admin', 'supervisor', 'mandor', 'operator', 'visitor'];
const USERS = 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z';

/**
 * Manajemen Pengguna — mengikuti Production-QC-Logging-System: kotak cari,
 * tab peran berwarna dengan jumlah (ikut pencarian), lalu satu daftar untuk
 * peran yang dibuka. Tombol Tambah/Edit/Hapus hanya muncul bila boleh.
 * Tambahan asata: tab Departemen (khusus developer).
 */
export default function ManagementIndex({
    tab,
    search,
    indexUrl,
    peran,
    jumlah,
    bisaLihat,
    pengguna,
    createUrl,
    departemen,
}) {
    const [q, setQ] = useState(search || '');

    function buka(params) {
        router.get(indexUrl, params, { preserveScroll: true });
    }

    function cari(e) {
        e.preventDefault();
        buka({ tab, ...(q.trim() ? { search: q.trim() } : {}) });
    }

    const tabs = URUTAN.filter((k) => bisaLihat[k]).map((k) => ({ key: k, ...peran[k], jumlah: jumlah[k] ?? 0 }));
    if (bisaLihat.department) {
        tabs.push({ key: 'department', label: 'Departemen', warna: 'slate', jumlah: jumlah.department ?? 0 });
    }

    const info = peran[tab];

    return (
        <AppLayout title="Manajemen Pengguna" subtitle="Kelola akun dan perannya">
            <form className="au-mgmt-cari" onSubmit={cari}>
                <div className="au-mgmt-cari-input">
                    <Icon path={ICON.search} />
                    <Input value={q} placeholder="Cari nama, email, atau username…" onChange={(e) => setQ(e.target.value)} />
                </div>
                <Btn type="submit">Cari</Btn>
                {search ? (
                    <Btn tone="ghost" onClick={() => buka({ tab })}>
                        Reset
                    </Btn>
                ) : null}
            </form>

            <div className="au-mgmt-tabs">
                {tabs.map((t) => (
                    <button
                        type="button"
                        key={t.key}
                        className={'au-mgmt-tab au-warna-' + t.warna + (t.key === tab ? ' is-aktif' : '')}
                        onClick={() => buka({ tab: t.key, ...(search ? { search } : {}) })}
                    >
                        {t.key !== tab ? <i /> : null}
                        {t.label}
                        <span>{t.jumlah}</span>
                    </button>
                ))}
            </div>

            {tab === 'department' ? (
                <Departemen data={departemen} />
            ) : (
                <div className="au-mgmt-kartu">
                    <div className="au-mgmt-kepala">
                        <div>
                            <h2>
                                {info.label} <small>· {pengguna.length} akun</small>
                            </h2>
                            <p>{info.ket}</p>
                        </div>
                        {createUrl ? (
                            <Btn as="a" href={createUrl} icon={ICON.plus} className={'au-mgmt-tambah au-warna-' + info.warna}>
                                Tambah
                            </Btn>
                        ) : null}
                    </div>

                    {pengguna.length ? (
                        <div className="au-table-wrap">
                            <table className="au-mgmt-tabel">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th className="au-sembunyi-hp">Email</th>
                                        {tab === 'operator' ? <th className="au-sembunyi-hp">Departemen</th> : null}
                                        <th className="au-sembunyi-hp">Bergabung</th>
                                        <th>Status</th>
                                        {pengguna.some((u) => u.editUrl || u.deleteUrl) ? <th style={{ textAlign: 'right' }}>Aksi</th> : null}
                                    </tr>
                                </thead>
                                <tbody>
                                    {pengguna.map((u) => (
                                        <tr key={u.id}>
                                            <td>
                                                <div className="au-mgmt-nama">
                                                    <span className={'au-mgmt-avatar au-warna-' + info.warna}>
                                                        {u.avatar_url ? (
                                                            <img src={u.avatar_url} alt="" onError={(e) => e.currentTarget.remove()} />
                                                        ) : null}
                                                        <b>{u.name.charAt(0).toUpperCase()}</b>
                                                    </span>
                                                    <span>
                                                        <span className="au-mgmt-n">
                                                            {u.name}
                                                            {u.is_me ? <em>Anda</em> : null}
                                                        </span>
                                                        <small>{u.username ? '@' + u.username : u.email}</small>
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="au-sembunyi-hp">{u.email}</td>
                                            {tab === 'operator' ? <td className="au-sembunyi-hp">{u.department || '—'}</td> : null}
                                            <td className="au-sembunyi-hp">{u.joined || '-'}</td>
                                            <td>
                                                {u.toggleUrl ? (
                                                    <button
                                                        type="button"
                                                        className={'au-status' + (u.is_active ? ' is-aktif' : '')}
                                                        title={u.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                        onClick={() => router.patch(u.toggleUrl, {}, { preserveScroll: true })}
                                                    >
                                                        {u.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </button>
                                                ) : (
                                                    <span className={'au-status' + (u.is_active ? ' is-aktif' : '')}>
                                                        {u.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </span>
                                                )}
                                            </td>
                                            {pengguna.some((x) => x.editUrl || x.deleteUrl) ? (
                                                <td style={{ textAlign: 'right' }}>
                                                    <span className="au-actions">
                                                        {u.editUrl ? <IconBtn as="a" href={u.editUrl} icon={ICON.edit} title="Edit" /> : null}
                                                        {u.deleteUrl ? (
                                                            <DeleteButton
                                                                url={u.deleteUrl}
                                                                title={'Hapus ' + info.label.toLowerCase() + " '" + u.name + "'?"}
                                                                text="Akun ini akan dihapus permanen."
                                                            />
                                                        ) : null}
                                                    </span>
                                                </td>
                                            ) : null}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="au-mgmt-kosong">
                            <Icon path={USERS} />
                            <p>
                                {search
                                    ? 'Tidak ada ' + info.label.toLowerCase() + ' yang cocok dengan "' + search + '"'
                                    : 'Belum ada ' + info.label.toLowerCase()}
                            </p>
                        </div>
                    )}
                </div>
            )}
        </AppLayout>
    );
}

/** Tab Departemen (khas asata, khusus developer). */
function Departemen({ data }) {
    const [baru, setBaru] = useState('');
    const [edit, setEdit] = useState(null);
    const [busy, setBusy] = useState(false);

    function tambah(e) {
        e.preventDefault();
        if (!baru.trim() || busy) return;
        setBusy(true);
        router.post(data.storeUrl, { name: baru.trim(), is_active: true }, {
            preserveScroll: true,
            onSuccess: () => setBaru(''),
            onFinish: () => setBusy(false),
        });
    }

    function simpan(e) {
        e.preventDefault();
        if (!edit || busy) return;
        setBusy(true);
        router.put(edit.updateUrl, { name: edit.name, is_active: edit.is_active }, {
            preserveScroll: true,
            onSuccess: () => setEdit(null),
            onFinish: () => setBusy(false),
        });
    }

    return (
        <div className="au-mgmt-kartu">
            <div className="au-mgmt-kepala">
                <div>
                    <h2>
                        Departemen <small>· {data.rows.length} departemen</small>
                    </h2>
                    <p>Pengelompokan operator per bagian produksi</p>
                </div>
            </div>
            <form className="au-toolbar" onSubmit={tambah} style={{ padding: '0 16px 12px' }}>
                <Input value={baru} placeholder="Nama departemen baru" onChange={(e) => setBaru(e.target.value)} />
                <Btn type="submit" icon={ICON.plus} disabled={busy || !baru.trim()}>
                    Tambah
                </Btn>
            </form>
            <div className="au-table-wrap">
                <table className="au-mgmt-tabel">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Status</th>
                            <th style={{ textAlign: 'right' }}>Operator</th>
                            <th style={{ textAlign: 'right' }}>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.rows.length ? (
                            data.rows.map((d) => (
                                <tr key={d.id}>
                                    <td style={{ fontWeight: 800 }}>{d.name}</td>
                                    <td>
                                        <span className={'au-status' + (d.is_active ? ' is-aktif' : '')}>{d.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                    </td>
                                    <td style={{ textAlign: 'right' }}>{d.operators}</td>
                                    <td style={{ textAlign: 'right' }}>
                                        <span className="au-actions">
                                            <IconBtn icon={ICON.edit} title="Edit" onClick={() => setEdit({ ...d })} />
                                            <DeleteButton
                                                url={d.deleteUrl}
                                                title={'Hapus departemen ' + d.name + '?'}
                                                text="Departemen yang masih dipakai operator tidak bisa dihapus."
                                            />
                                        </span>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={4} className="au-mgmt-kosong">Belum ada departemen.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {edit ? (
                <div className="au-modal-backdrop" onClick={() => !busy && setEdit(null)}>
                    <form className="au-modal au-form" onClick={(e) => e.stopPropagation()} onSubmit={simpan}>
                        <h3 className="au-modal-title">Edit Departemen</h3>
                        <Input value={edit.name} onChange={(e) => setEdit({ ...edit, name: e.target.value })} />
                        <Check label="Aktif" checked={edit.is_active} onChange={(e) => setEdit({ ...edit, is_active: e.target.checked })} />
                        <div className="au-modal-actions">
                            <Btn tone="ghost" onClick={() => setEdit(null)} disabled={busy}>
                                Batal
                            </Btn>
                            <Btn type="submit" icon={ICON.save} disabled={busy}>
                                Simpan
                            </Btn>
                        </div>
                    </form>
                </div>
            ) : null}
        </div>
    );
}
