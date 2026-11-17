import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Btn, Card, Check, Field, ICON, Input, Select } from '../../Components/Ui';

/**
 * Form tambah/edit pengguna untuk semua role (admin, supervisor, mandor,
 * operator, visitor, developer). Perbedaan antar role — email wajib atau
 * tidak, username opsional, ada pilihan departemen, panjang minimal password —
 * dikirim server lewat prop `resource`, jadi aturan tampilan tidak pernah
 * berbeda dari aturan validasi di controller.
 */
export default function UserForm({ mode, action, user, resource, departments }) {
    const isEdit = mode === 'edit';

    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name || '',
        username: user?.username || '',
        email: user?.email || '',
        department: user?.department || '',
        password: '',
        password_confirmation: '',
        is_active: user ? user.is_active : true,
    });

    function submit(e) {
        e.preventDefault();
        if (isEdit) put(action);
        else post(action);
    }

    const title = (isEdit ? 'Edit ' : 'Tambah ') + resource.label;

    return (
        <AppLayout title={title} subtitle={isEdit ? user.name : 'Buat akun ' + resource.label.toLowerCase()}>
            <Card
                title={title}
                action={
                    <Btn as="a" href={resource.indexUrl} tone="ghost" sm icon={ICON.back}>
                        Kembali
                    </Btn>
                }
            >
                <form className="au-form" onSubmit={submit}>
                    <div className="au-form-grid au-form-grid-2">
                        <Field label="Nama lengkap" required error={errors.name}>
                            <Input
                                value={data.name}
                                error={errors.name}
                                autoFocus
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        </Field>

                        <Field
                            label="Username"
                            required={!resource.usernameOptional}
                            error={errors.username}
                            hint={resource.usernameOptional ? 'Boleh dikosongkan' : null}
                        >
                            <Input
                                value={data.username}
                                error={errors.username}
                                onChange={(e) => setData('username', e.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="au-form-grid au-form-grid-2">
                        <Field
                            label="Email"
                            required={resource.emailRequired}
                            error={errors.email}
                            hint={resource.emailRequired ? null : 'Boleh dikosongkan'}
                        >
                            <Input
                                type="email"
                                value={data.email}
                                error={errors.email}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        </Field>

                        {resource.withDepartment ? (
                            <Field label="Departemen" error={errors.department}>
                                <Select
                                    value={data.department}
                                    error={errors.department}
                                    placeholder="— Tanpa departemen —"
                                    options={departments || []}
                                    onChange={(e) => setData('department', e.target.value)}
                                />
                            </Field>
                        ) : null}
                    </div>

                    <div className="au-form-grid au-form-grid-2">
                        <Field
                            label="Password"
                            required={!isEdit}
                            error={errors.password}
                            hint={
                                isEdit
                                    ? 'Kosongkan jika tidak ingin mengganti password'
                                    : 'Minimal ' + resource.minPassword + ' karakter'
                            }
                        >
                            <Input
                                type="password"
                                value={data.password}
                                error={errors.password}
                                autoComplete="new-password"
                                onChange={(e) => setData('password', e.target.value)}
                            />
                        </Field>

                        <Field label="Ulangi password" error={errors.password_confirmation}>
                            <Input
                                type="password"
                                value={data.password_confirmation}
                                autoComplete="new-password"
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                        </Field>
                    </div>

                    {/* Saat membuat akun baru, controller selalu memaksa
                        is_active = true, jadi checkbox ini hanya ditampilkan
                        pada mode edit agar tidak menjanjikan sesuatu yang
                        tidak dipakai server. */}
                    {isEdit ? (
                        <Check
                            label="Akun aktif"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                    ) : null}

                    <div className="au-form-actions">
                        <Btn type="submit" icon={ICON.save} disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Btn>
                        <Btn as="a" href={resource.indexUrl} tone="ghost">
                            Batal
                        </Btn>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
