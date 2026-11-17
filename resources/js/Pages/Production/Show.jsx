import AppLayout from '../../Layouts/AppLayout';
import { Btn, ICON } from '../../Components/Ui';

function Baris({ label, value }) {
    return (
        <div className="au-dp-baris">
            <span>{label}</span>
            <b>{value || '—'}</b>
        </div>
    );
}

export default function ProductionShow({ log, lastChannelSerials, indexUrl, editUrl }) {
    const channel = log.type === 'channel';
    const seriKva = [log.series, log.kva ? log.kva + ' kVA' : null].filter(Boolean).join(' · ');

    return (
        <AppLayout title="Detail Produksi" subtitle={log.dateLabel}>
            <article className="au-card au-dp">
                <header className="au-dp-kepala">
                    <div>
                        <h2>{log.product}</h2>
                        <p>{seriKva || 'Tanpa seri'}</p>
                    </div>
                    <div className="au-dp-total">
                        <b>{log.total}</b>
                        <span>Total Unit</span>
                    </div>
                </header>

                <div className="au-dp-isi">
                    {channel ? (
                        <>
                            <div className="au-dp-kotak">
                                <div className="is-up">
                                    <span>Channel UP</span>
                                    <b>{log.up}</b>
                                    <small>unit</small>
                                </div>
                                <div className="is-bt">
                                    <span>Channel BT</span>
                                    <b>{log.bt}</b>
                                    <small>unit</small>
                                </div>
                            </div>
                            <p className="au-dp-rumus">
                                Total = (UP + BT) ÷ 2 = ({log.up} + {log.bt}) ÷ 2 = {log.total}
                            </p>
                        </>
                    ) : null}

                    <Baris label="Tanggal produksi" value={log.dateLabel} />
                    <Baris label="Nama operator" value={log.operator} />
                    <Baris label="Kategori" value={log.category} />
                    <Baris label="Departemen" value={log.department} />
                    <Baris label="Tipe produk" value={channel ? 'Channel' : 'Regular'} />
                    <Baris label="Satuan" value="unit" />
                    <Baris label="Reject" value={String(log.reject)} />
                    {log.reject ? (
                        <>
                            <Baris label="Kategori reject" value={log.rejectCategory} />
                            <Baris label="Catatan reject" value={log.rejectNotes} />
                        </>
                    ) : null}
                    <Baris label="Keterangan" value={log.keterangan} />
                    <Baris label="Waktu input" value={log.createdAt} />

                    <div className="au-dp-nomor">
                        <span>Nomor urut / catatan</span>
                        <pre>{log.notes || '—'}</pre>
                        {channel && lastChannelSerials ? (
                            <small>
                                Nomor urut channel terakhir: {lastChannelSerials.up ?? '—'} · {lastChannelSerials.bt ?? '—'}
                            </small>
                        ) : null}
                    </div>

                    <div className="au-dp-aksi">
                        <Btn as="a" href={indexUrl} tone="ghost" icon={ICON.back}>
                            Kembali
                        </Btn>
                        <Btn as="a" href={editUrl} icon={ICON.edit}>
                            Edit Data
                        </Btn>
                    </div>
                </div>
            </article>
        </AppLayout>
    );
}
