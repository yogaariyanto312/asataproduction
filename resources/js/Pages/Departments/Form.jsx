import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, Card, Check, Field, ICON, Input } from '../../Components/Ui';

export default function DepartmentForm({ mode, action, indexUrl, department }) {
    const isEdit = mode === 'edit';

    const { data, setData, post, put, processing, errors } = useForm({
        name: department?.name || '',
        is_active: department ? department.is_active : true,
    });

    function submit(e) {
        e.preventDefault();
        if (isEdit) put(action);
        else post(action);
    }

    const title = isEdit ? 'Edit Departemen' : 'Tambah Departemen';

    return (
        <AppLayout title={title} subtitle={isEdit ? department.name : 'Departemen baru'}>
            <Card
                title={title}
                action={
                    <Btn as="a" href={indexUrl} tone="ghost" sm icon={ICON.back}>
                        Kembali
                    </Btn>
                }
            >
                <form className="au-form" onSubmit={submit}>
                    <Field label="Nama departemen" required error={errors.name}>
                        <Input
                            value={data.name}
                            error={errors.name}
                            autoFocus
                            maxLength={100}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </Field>

                    <Check
                        label="Departemen aktif"
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
