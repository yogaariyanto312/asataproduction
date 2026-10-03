import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, DeleteButton, ICON, Icon, IconBtn, Input, Select } from '../../Components/Ui';
import { gayaIkon } from './warna';

const CAL = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z';
const CUBE = 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4';

/**
 * Tata letak versi lama: panel filter di atas, lalu daftar dikelompokkan per
 * TAHUN (pill biru + garis + jumlah), dan tiap nama produk menjadi satu kartu
 * berisi varian seri/kVA sebagai baris.
 */
export default function ProductsIndex({
    sections,
    total,
    filters,
    indexUrl,
    createUrl,
    ukuranUrl,
    categories,
    years,
    can,
}) {
    const [f, setF] = useState({
        search: filters.search || '',
        category_id: filters.category_id || '',
        tahun: filters.tahun || '',
        status: filters.status || '',
    });

    function applyFilter(e) {
        e.preventDefault();
        const params = {};
        Object.entries(f).forEach(([k, v]) => {
            if (v !== '' && v !== null) params[k] = v;
        });
        router.get(indexUrl, params, { preserveState: true, replace: true });
    }

    function resetFilter() {
        setF({ search: '', category_id: '', tahun: '', status: '' });
        router.get(indexUrl, {}, { preserveState: true, replace: true });
    }

    function toggle(url) {
        router.patch(url, {}, { preserveScroll: true });
    }

    return (
        <AppLayout title="Master Produk" subtitle="Kelola daftar produk dan seri">
            <div className="au-panel">
                <form className="au-filter" onSubmit={applyFilter}>
                    <div className="au-filter-field is-wide">
                        <label className="au-filter-label">Cari Produk</label>
                        <Input
                            value={f.search}
                            placeholder="Nama atau seri..."
                            onChange={(e) => setF({ ...f, search: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Kategori</label>
                        <Select
                            value={f.category_id}
                            placeholder="Semua Kategori"
                            options={categories}
                            onChange={(e) => setF({ ...f, category_id: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Tahun</label>
                        <Select
                            value={f.tahun}
                            placeholder="Semua Tahun"
                            options={years}
                            onChange={(e) => setF({ ...f, tahun: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-field">
                        <label className="au-filter-label">Status</label>
                        <Select
                            value={f.status}
                            placeholder="Semua Status"
                            options={[
                                { value: '1', label: 'Aktif' },
                                { value: '0', label: 'Nonaktif' },
                            ]}
                            onChange={(e) => setF({ ...f, status: e.target.value })}
                        />
                    </div>

                    <div className="au-filter-actions">
                        <Btn type="submit">Filter</Btn>
                        <Btn tone="ghost" onClick={resetFilter}>
                            Reset
                        </Btn>
                        <Btn as="a" href={ukuranUrl} tone="ghost">
                            Ukuran
                        </Btn>
                        {can.create ? (
                            <Btn as="a" href={createUrl} icon={ICON.plus} className="au-btn--green">
                                Tambah Produk
                            </Btn>
                        ) : null}
                    </div>
                </form>
            </div>

            {sections.length ? (
                sections.map((sec) => (
                    <div className="au-section" key={sec.year || 'tanpa-tahun'}>
                        <div className="au-section-head">
                            {sec.year ? (
                                <span className="au-section-pill">
                                    <Icon path={CAL} />
                                    {sec.year}
                                </span>
                            ) : (
                                <span className="au-section-pill au-section-pill--muted">
                                    Belum ada tahun
                                </span>
                            )}
                            <span className="au-section-rule" />
                            <span className="au-section-count">{sec.count} produk</span>
                        </div>

                        <div className="au-groupgrid">
                            {sec.products.map((p) => (
                                <div className="au-groupcard" key={(sec.year || 'x') + '-' + p.name}>
                                    <div className="au-groupcard-head">
                                        <span
                                            className={
                                                'au-groupcard-mark' +
                                                (p.type === 'channel' ? ' au-groupcard-mark--blue' : '')
                                            }
                                            style={gayaIkon(p.warnaIkon)}
                                        >
                                            <Icon path={CUBE} />
                                        </span>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <h3 className="au-groupcard-title" style={p.warnaTeks ? { color: p.warnaTeks } : undefined}>{p.name}</h3>
                                            <p className="au-groupcard-sub">
                                                {p.category} · {p.variants.length} varian
                                            </p>
                                        </div>
                                        {can.create ? (
                                            <IconBtn
                                                as="a"
                                                href={p.addUrl}
                                                icon={ICON.plus}
                                                title="Tambah varian baru"
                                            />
                                        ) : null}
                                    </div>

                                    <div className="au-groupcard-body au-pd-gulir">
                                        {p.variants.map((v) => (
                                            <div className="au-itemrow" key={v.id}>
                                                <div className="au-itemrow-main">
                                                    {v.series ? (
                                                        <>
                                                            <p className="au-serial">{v.series}</p>
                                                            {v.kva ? (
                                                                <p className="au-itemrow-sub">
                                                                    {v.kva} KVA
                                                                </p>
                                                            ) : null}
                                                        </>
                                                    ) : (
                                                        v.manual ? (
                                                            <p className="au-manual">Input Seri &amp; KVA Manual</p>
                                                        ) : (
                                                            <p className="au-itemrow-sub">Tanpa seri</p>
                                                        )
                                                    )}
                                                </div>

                                                <span className="au-actions">
                                                    {can.edit ? (
                                                        <button
                                                            type="button"
                                                            className={
                                                                'au-toggle' + (v.is_active ? '' : ' is-off')
                                                            }
                                                            title="Klik untuk ubah status"
                                                            onClick={() => toggle(v.toggleUrl)}
                                                        >
                                                            {v.is_active ? 'Aktif' : 'Nonaktif'}
                                                        </button>
                                                    ) : (
                                                        <span
                                                            className={
                                                                'au-toggle' + (v.is_active ? '' : ' is-off')
                                                            }
                                                            style={{ cursor: 'default' }}
                                                        >
                                                            {v.is_active ? 'Aktif' : 'Nonaktif'}
                                                        </span>
                                                    )}

                                                    <IconBtn
                                                        as="a"
                                                        href={v.showUrl}
                                                        icon={ICON.eye}
                                                        title="Detail"
                                                    />

                                                    {can.edit ? (
                                                        <IconBtn
                                                            as="a"
                                                            href={v.editUrl}
                                                            icon={ICON.edit}
                                                            title="Edit"
                                                        />
                                                    ) : null}

                                                    {can.delete ? (
                                                        <DeleteButton
                                                            url={v.deleteUrl}
                                                            title={'Hapus ' + p.name + '?'}
                                                            text={
                                                                (v.series || 'Seri manual') +
                                                                ' akan dihapus permanen.'
                                                            }
                                                        />
                                                    ) : null}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))
            ) : (
                <div className="au-blank">
                    <Icon path={CUBE} />
                    <p>Belum ada produk</p>
                    {can.create ? (
                        <Btn as="a" href={createUrl}>
                            Tambah Produk Pertama
                        </Btn>
                    ) : null}
                </div>
            )}
        </AppLayout>
    );
}
