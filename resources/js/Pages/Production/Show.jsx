import AppLayout from '../../Layouts/AppLayout';
import { Badge, Btn, Card, ICON } from '../../Components/Ui';

function Row({ label, value, badge }) {
    return (
        <div className="au-row">
            <span className="au-row-main">
                <span className="au-row-sub">{label}</span>
                <span className="au-row-title">{value || '—'}</span>
            </span>
            {badge || null}
        </div>
    );
}

export default function ProductionShow({ log, lastChannelSerials, indexUrl, editUrl }) {
    return (
        <AppLayout title="Detail Produksi" subtitle={log.dateLabel}>
            <div className="au-grid au-grid-2">
                <Card
                    title={log.product}
                    sub={log.category || 'Tanpa kategori'}
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
                        <Row label="Tanggal produksi" value={log.dateLabel} />
                        <Row
                            label="Seri / kVA"
                            value={
                                [log.series, log.kva ? log.kva + ' kVA' : null]
                                    .filter(Boolean)
                                    .join(' · ') || null
                            }
                        />
                        <Row label="Operator" value={log.operator} />
                        <Row label="Departemen" value={log.department} />
                        <Row
                            label="Tipe produk"
                            value={log.type === 'channel' ? 'Channel' : 'Regular'}
                            badge={log.type === 'channel' ? <Badge tone="yellow">CH</Badge> : null}
                        />
                        <Row label="Dicatat pada" value={log.createdAt} />
                    </div>
                </Card>

                <Card title="Hasil Produksi" sub={'Total ' + log.total + ' unit'}>
                    <div className="au-list">
                        <Row label="UP" value={String(log.up)} />
                        <Row label="BT" value={String(log.bt)} />
                        <Row
                            label="Total"
                            value={String(log.total)}
                            badge={<Badge tone="teal">{log.total} unit</Badge>}
                        />
                        <Row
                            label="Reject"
                            value={String(log.reject)}
                            badge={log.reject ? <Badge tone="accent">{log.reject}</Badge> : null}
                        />
                        {log.reject ? (
                            <>
                                <Row label="Kategori reject" value={log.rejectCategory} />
                                <Row label="Catatan reject" value={log.rejectNotes} />
                            </>
                        ) : null}
                        <Row label="Catatan" value={log.notes} />
                        <Row label="Keterangan" value={log.keterangan} />
                    </div>

                    {log.type === 'channel' && lastChannelSerials ? (
                        <p className="au-hint" style={{ marginTop: 12 }}>
                            Nomor urut channel terakhir — UP: {lastChannelSerials.up ?? '—'} · BT:{' '}
                            {lastChannelSerials.bt ?? '—'}
                        </p>
                    ) : null}
                </Card>
            </div>
        </AppLayout>
    );
}
