import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Btn, DeleteButton, ICON, IconBtn, Input, Pagination } from '../../Components/Ui';

/**
 * Tata letak mengikuti versi Blade lama: baris pencarian + tombol Cari di kiri,
 * tombol Tambah di kanan, lalu tabel langsung (tanpa judul kartu) dengan kolom
 * No, status & aksi rata tengah, dan paginasi di kaki kartu.
 */
export default function CategoriesIndex({ rows, search, indexUrl, createUrl, canManage }) {
    const [q, setQ] = useState(search || '');

    function submit(e) {
        e.preventDefault();
        router.get(indexUrl, q ? { search: q } : {}, { preserveState: true, replace: true });
    }

    const cols = canManage ? 7 : 6;

    return (
        <AppLayout title="Kategori Produk" subtitle="Kelola kategori produk produksi">
            <div className="au-filter" style={{ marginBottom: 20 }}>
                <form onSubmit={submit} style={{ display: 'flex', gap: 10 }}>
                    <Input
                        value={q}
                        placeholder="Cari kategori..."
                        style={{ width: 200 }}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <Btn type="submit">Cari</Btn>
                </form>

                {canManage ? (
                    <div className="au-toolbar-end">
                        <Btn as="a" href={createUrl} icon={ICON.plus} className="au-btn--green">
                            Tambah Kategori
                        </Btn>
                    </div>
                ) : null}
            </div>

            <div className="au-tablecard">
                <div className="au-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Kode</th>
                                <th>Keterangan</th>
                                <th style={{ textAlign: 'center' }}>Produk</th>
                                <th style={{ textAlign: 'center' }}>Status</th>
                                {canManage ? <th style={{ textAlign: 'center' }}>Aksi</th> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length ? (
                                rows.data.map((c, i) => (
                                    <tr key={c.id}>
                                        <td className="au-num">{(rows.from || 1) + i}</td>
                                        <td style={{ fontWeight: 800 }}>
                                            {c.name} {c.manual ? <Badge tone="accent">Seri manual</Badge> : null}
                                        </td>
                                        <td className="au-mono">{c.code || '-'}</td>
                                        <td style={{ maxWidth: 280 }}>{c.description || '-'}</td>
                                        <td style={{ textAlign: 'center' }}>
                                            <Badge>{c.products} produk</Badge>
                                        </td>
                                        <td style={{ textAlign: 'center' }}>
                                            <Badge tone={c.is_active ? 'teal' : 'accent'}>
                                                {c.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        </td>
                                        {canManage ? (
                                            <td style={{ textAlign: 'center' }}>
                                                <span className="au-actions">
                                                    <IconBtn
                                                        as="a"
                                                        href={c.editUrl}
                                                        icon={ICON.edit}
                                                        title="Edit"
                                                    />
                                                    <DeleteButton
                                                        url={c.deleteUrl}
                                                        title={'Hapus kategori ' + c.name + '?'}
                                                        text="Kategori yang masih memiliki produk tidak bisa dihapus."
                                                    />
                                                </span>
                                            </td>
                                        ) : null}
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={cols}
                                        style={{ padding: '60px 20px', textAlign: 'center', color: '#94a3b8' }}
                                    >
                                        Belum ada kategori.
                                        {canManage ? (
                                            <>
                                                {' '}
                                                <a className="au-link-inline" href={createUrl}>
                                                    Tambah sekarang
                                                </a>
                                            </>
                                        ) : null}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {rows.last_page > 1 ? (
                    <div className="au-tablefoot">
                        <Pagination paginator={rows} />
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
