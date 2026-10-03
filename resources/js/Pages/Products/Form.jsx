import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, Card, Check, Field, ICON, Input, Select, Textarea } from '../../Components/Ui';

export default function ProductForm({ mode, action, indexUrl, categories, maxYear, product }) {
    const isEdit = mode === 'edit';

    const { data, setData, post, put, processing, errors } = useForm({
        category_id: product?.category_id || '',
        type: product?.type || 'regular',
        name: product?.name || '',
        series: product?.series || '',
        kva: product?.kva || '',
        tahun: product?.tahun || '',
        panjang: product?.panjang || '',
        lebar: product?.lebar || '',
        unit: product?.unit || 'pcs',
        description: product?.description || '',
        is_active: product ? product.is_active : true,
    });

    function submit(e) {
        e.preventDefault();
        if (isEdit) put(action);
        else post(action);
    }

    const title = isEdit ? 'Edit Produk' : 'Tambah Produk';

    return (
        <AppLayout title={title} subtitle={isEdit ? product.name : 'Produk baru'}>
            <Card
                title={title}
                action={
                    <Btn as="a" href={indexUrl} tone="ghost" sm icon={ICON.back}>
                        Kembali
                    </Btn>
                }
            >
                <form className="au-form" onSubmit={submit}>
                    <div className="au-form-grid au-form-grid-2">
                        <Field label="Nama produk" required error={errors.name}>
                            <Input
                                value={data.name}
                                error={errors.name}
                                autoFocus
                                maxLength={150}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        </Field>

                        <Field label="Kategori" required error={errors.category_id}>
                            <Select
                                value={data.category_id}
                                error={errors.category_id}
                                placeholder="— Pilih kategori —"
                                options={categories}
                                onChange={(e) => setData('category_id', e.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="au-form-grid au-form-grid-2">
                        <Field label="Seri" error={errors.series}>
                            <Input
                                value={data.series}
                                error={errors.series}
                                maxLength={100}
                                onChange={(e) => setData('series', e.target.value)}
                            />
                        </Field>

                        <Field label="kVA" error={errors.kva}>
                            <Input
                                value={data.kva}
                                error={errors.kva}
                                maxLength={20}
                                onChange={(e) => setData('kva', e.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="au-form-grid au-form-grid-2">
                        <Field label="Tipe" required error={errors.type}>
                            <Select
                                value={data.type}
                                error={errors.type}
                                options={[
                                    { value: 'regular', label: 'Regular' },
                                    { value: 'channel', label: 'Channel' },
                                ]}
                                onChange={(e) => setData('type', e.target.value)}
                            />
                        </Field>

                        <Field
                            label="Tahun"
                            error={errors.tahun}
                            hint={'Antara 2025 dan ' + maxYear}
                        >
                            <Input
                                type="number"
                                min={2025}
                                max={maxYear}
                                value={data.tahun}
                                error={errors.tahun}
                                onChange={(e) => setData('tahun', e.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="au-form-grid au-form-grid-2">
                        <Field label="Panjang" error={errors.panjang}>
                            <Input
                                value={data.panjang}
                                error={errors.panjang}
                                maxLength={50}
                                onChange={(e) => setData('panjang', e.target.value)}
                            />
                        </Field>

                        <Field label="Lebar" error={errors.lebar}>
                            <Input
                                value={data.lebar}
                                error={errors.lebar}
                                maxLength={50}
                                onChange={(e) => setData('lebar', e.target.value)}
                            />
                        </Field>
                    </div>

                    <Field label="Satuan" required error={errors.unit}>
                        <Input
                            value={data.unit}
                            error={errors.unit}
                            maxLength={20}
                            onChange={(e) => setData('unit', e.target.value)}
                        />
                    </Field>

                    <Field label="Keterangan" error={errors.description}>
                        <Textarea
                            value={data.description}
                            error={errors.description}
                            maxLength={500}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </Field>

                    <Check
                        label="Produk aktif"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />

                    <div className="au-form-actions">
                        <Btn type="submit" icon={ICON.save} disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Btn>
                        <Btn as="a" href={indexUrl} tone="ghost">
                            Batal
                        </Btn>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
