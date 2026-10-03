import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, Card, Check, Field, ICON, Input, Textarea } from '../../Components/Ui';

export default function CategoryForm({ mode, action, indexUrl, category }) {
    const isEdit = mode === 'edit';

    const { data, setData, post, put, processing, errors } = useForm({
        name: category?.name || '',
        code: category?.code || '',
        description: category?.description || '',
        is_active: category ? category.is_active : true,
        has_manual_serial: category ? !!category.has_manual_serial : false,
    });

    function submit(e) {
        e.preventDefault();
        if (isEdit) put(action);
        else post(action);
    }

    const title = isEdit ? 'Edit Kategori' : 'Tambah Kategori';

    return (
        <AppLayout title={title} subtitle={isEdit ? category.name : 'Kategori produk baru'}>
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
                        <Field label="Nama kategori" required error={errors.name}>
                            <Input
                                value={data.name}
                                error={errors.name}
                                autoFocus
                                maxLength={100}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        </Field>

                        <Field label="Kode" error={errors.code} hint="Opsional, maksimal 20 karakter">
                            <Input
                                value={data.code}
                                error={errors.code}
                                maxLength={20}
                                onChange={(e) => setData('code', e.target.value)}
                            />
                        </Field>
                    </div>

                    <Field label="Keterangan" error={errors.description}>
                        <Textarea
                            value={data.description}
                            error={errors.description}
                            maxLength={500}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </Field>

                    <Check
                        label="Seri & KVA diinput manual saat Input Produksi"
                        checked={data.has_manual_serial}
                        onChange={(e) => setData('has_manual_serial', e.target.checked)}
                    />
                    <p className="au-hint" style={{ marginTop: -6 }}>
                        Centang untuk kategori seperti Channel/Cover/Tangki: produknya cukup dibuat satu (tanpa seri), lalu nomor seri &amp; KVA
                        diketik operator saat input dan otomatis tersimpan sebagai varian baru.
                    </p>

                    <Check
                        label="Kategori aktif"
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
