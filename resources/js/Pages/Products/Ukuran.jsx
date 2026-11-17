import AppLayout from '../../Layouts/AppLayout';
import { Btn, Card, FilterBar, ICON, IconBtn, Table } from '../../Components/Ui';

export default function ProductsUkuran({ rows, filters, indexUrl, backUrl, categories }) {
    return (
        <AppLayout title="Ukuran Produk" subtitle="Panjang & lebar tiap produk aktif">
            <Card
                title="Ukuran Produk"
                sub={rows.length + ' produk aktif'}
                action={
                    <Btn as="a" href={backUrl} tone="ghost" sm icon={ICON.back}>
                        Master Produk
                    </Btn>
                }
            >
                <FilterBar
                    url={indexUrl}
                    values={filters}
                    fields={[
                        { name: 'search', placeholder: 'Cari nama atau seri...' },
                        {
                            name: 'category_id',
                            type: 'select',
                            placeholder: 'Semua kategori',
                            options: categories,
                        },
                    ]}
                />

                <Table
                    head={[
                        'Nama',
                        'Seri',
                        'kVA',
                        'Kategori',
                        'Panjang',
                        'Lebar',
                        'Satuan',
                        { label: 'Aksi', align: 'right' },
                    ]}
                    empty={rows.length ? null : 'Tidak ada produk yang cocok.'}
                >
                    {rows.map((p) => (
                        <tr key={p.id}>
                            <td style={{ fontWeight: 800 }}>{p.name}</td>
                            <td>{p.series || '—'}</td>
                            <td>{p.kva || '—'}</td>
                            <td>{p.category || '—'}</td>
                            <td>{p.panjang || '—'}</td>
                            <td>{p.lebar || '—'}</td>
                            <td>{p.unit || '—'}</td>
                            <td style={{ textAlign: 'right' }}>
                                <IconBtn as="a" href={p.editUrl} icon={ICON.edit} title="Edit" />
                            </td>
                        </tr>
                    ))}
                </Table>
            </Card>
        </AppLayout>
    );
}
