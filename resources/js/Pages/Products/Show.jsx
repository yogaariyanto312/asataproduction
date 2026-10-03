import AppLayout from '../../Layouts/AppLayout';
import { Badge, Btn, Card, ICON, Table } from '../../Components/Ui';

function Spec({ label, value }) {
    return (
        <div className="au-row">
            <span className="au-row-main">
                <span className="au-row-sub">{label}</span>
                <span className="au-row-title">{value || '—'}</span>
            </span>
        </div>
    );
}

export default function ProductShow({ product, monthlyLogs, monthLabel, indexUrl, editUrl }) {
    const total = monthlyLogs.reduce((sum, l) => sum + Number(l.total || 0), 0);
    const reject = monthlyLogs.reduce((sum, l) => sum + Number(l.reject || 0), 0);

    return (
        <AppLayout title={product.name} subtitle="Detail produk">
            <div className="au-grid au-grid-2">
                <Card
                    title={product.name}
                    sub={product.category || 'Tanpa kategori'}
                    action={
                        <span className="au-actions">
                            <Btn as="a" href={editUrl} icon={ICON.edit} sm>
                                Edit
                            </Btn>
                            <Btn as="a" href={indexUrl} tone="ghost" sm icon={ICON.back}>
                                Kembali
                            </Btn>
                        </span>
                    }
                >
                    <div className="au-list">
                        <Spec label="Seri" value={product.series} />
                        <Spec label="kVA" value={product.kva} />
                        <Spec label="Tahun" value={product.tahun} />
                        <Spec
                            label="Tipe"
                            value={product.type === 'channel' ? 'Channel' : 'Regular'}
                        />
                        <Spec label="Panjang" value={product.panjang} />
                        <Spec label="Lebar" value={product.lebar} />
                        <Spec label="Satuan" value={product.unit} />
                        <Spec label="Keterangan" value={product.description} />
                        <div className="au-row">
                            <span className="au-row-main">
                                <span className="au-row-sub">Status</span>
                                <span>
                                    <Badge tone={product.is_active ? 'teal' : 'muted'}>
                                        {product.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </span>
                            </span>
                        </div>
                    </div>
                </Card>

                <Card
                    title="Produksi Bulan Ini"
                    sub={monthLabel}
                    action={<Badge tone="teal">{total} unit</Badge>}
                >
                    <Table
                        head={[
                            'Tanggal',
                            'Operator',
                            { label: 'UP', align: 'right' },
                            { label: 'BT', align: 'right' },
                            { label: 'Total', align: 'right' },
                            { label: 'Reject', align: 'right' },
                        ]}
                        empty={monthlyLogs.length ? null : 'Belum ada produksi bulan ini.'}
                    >
                        {monthlyLogs.map((l) => (
                            <tr key={l.id}>
                                <td>{l.date}</td>
                                <td>{l.operator || '—'}</td>
                                <td style={{ textAlign: 'right' }}>{l.up}</td>
                                <td style={{ textAlign: 'right' }}>{l.bt}</td>
                                <td style={{ textAlign: 'right', fontWeight: 800 }}>{l.total}</td>
                                <td style={{ textAlign: 'right' }}>{l.reject}</td>
                            </tr>
                        ))}
                    </Table>

                    {monthlyLogs.length ? (
                        <p className="au-hint" style={{ marginTop: 10 }}>
                            Total {total} unit · reject {reject} unit dari {monthlyLogs.length} entri
                        </p>
                    ) : null}
                </Card>
            </div>
        </AppLayout>
    );
}
