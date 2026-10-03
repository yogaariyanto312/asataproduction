import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Btn, Card, Field, ICON, Input, Textarea } from '../../Components/Ui';
import { konfirmasi } from '../../dialog';

export default function ProfileEdit({
    user,
    action,
    avatarUrl,
    logoutOthersUrl,
    aboutAvatarUrl,
    aboutInfoUrl,
    isDeveloper,
    telegram,
}) {
    const [busy, setBusy] = useState(false);
    const galatFoto = usePage().props.errors?.photo;

    const profile = useForm({
        name: user.name || '',
        email: user.email || '',
        avatar: user.avatar && String(user.avatar).startsWith('http') ? user.avatar : '',
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const about = useForm({
        handle: user.handle || '',
        bio: user.bio || '',
        link_instagram: user.link_instagram || '',
        link_github: user.link_github || '',
        link_portfolio: user.link_portfolio || '',
        link_email: user.link_email || '',
    });

    function saveProfile(e) {
        e.preventDefault();
        profile.put(action, {
            onSuccess: () => {
                profile.setData('current_password', '');
                profile.setData('password', '');
                profile.setData('password_confirmation', '');
            },
        });
    }

    function saveAbout(e) {
        e.preventDefault();
        about.post(aboutInfoUrl, { preserveScroll: true });
    }

    function uploadAvatar(file, url) {
        if (!file) return;
        setBusy(true);
        router.post(
            url,
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    }

    function logoutOthers() {
        router.post(logoutOthersUrl, {}, { preserveScroll: true });
    }

    return (
        <AppLayout title="Profil" subtitle={user.name}>
            <div className="au-grid au-grid-2">
                <Card title="Data Akun">
                    <div className="au-dev" style={{ marginBottom: 16 }}>
                        <span className="au-avatar" style={{ width: 64, height: 64, fontSize: 20 }}>
                            {user.avatar_url ? (
                                <img
                                    src={user.avatar_url}
                                    alt=""
                                    onError={(e) => {
                                        e.currentTarget.style.display = 'none';
                                    }}
                                />
                            ) : (
                                user.name.charAt(0).toUpperCase()
                            )}
                        </span>
                        <div>
                            <p className="au-dev-name">{user.name}</p>
                            <p className="au-dev-handle">
                                {user.username || '—'} · <Badge tone="muted">{user.role}</Badge>
                            </p>
                            <label className="au-btn au-btn--sm au-btn--ghost" style={{ marginTop: 8 }}>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    style={{ display: 'none' }}
                                    onChange={(e) => uploadAvatar(e.target.files[0], avatarUrl)}
                                />
                                {busy ? 'Mengunggah...' : 'Ganti Foto'}
                            </label>
                            {galatFoto ? <span className="au-error" style={{ display: 'block', marginTop: 6 }}>{galatFoto}</span> : null}
                        </div>
                    </div>

                    <form className="au-form" onSubmit={saveProfile}>
                        <Field label="Nama" required error={profile.errors.name}>
                            <Input
                                value={profile.data.name}
                                error={profile.errors.name}
                                maxLength={150}
                                onChange={(e) => profile.setData('name', e.target.value)}
                            />
                        </Field>

                        <Field label="Email" required error={profile.errors.email}>
                            <Input
                                type="email"
                                value={profile.data.email}
                                error={profile.errors.email}
                                maxLength={150}
                                onChange={(e) => profile.setData('email', e.target.value)}
                            />
                        </Field>

                        <Field
                            label="URL foto"
                            error={profile.errors.avatar}
                            hint="Alternatif dari unggah berkas — isi dengan URL gambar"
                        >
                            <Input
                                value={profile.data.avatar}
                                error={profile.errors.avatar}
                                maxLength={1000}
                                onChange={(e) => profile.setData('avatar', e.target.value)}
                            />
                        </Field>

                        <Field
                            label="Password saat ini"
                            error={profile.errors.current_password}
                            hint="Wajib diisi hanya bila mengganti password"
                        >
                            <Input
                                type="password"
                                value={profile.data.current_password}
                                error={profile.errors.current_password}
                                autoComplete="current-password"
                                onChange={(e) => profile.setData('current_password', e.target.value)}
                            />
                        </Field>

                        <div className="au-form-grid au-form-grid-2">
                            <Field
                                label="Password baru"
                                error={profile.errors.password}
                                hint="Minimal 6 karakter"
                            >
                                <Input
                                    type="password"
                                    value={profile.data.password}
                                    error={profile.errors.password}
                                    autoComplete="new-password"
                                    onChange={(e) => profile.setData('password', e.target.value)}
                                />
                            </Field>

                            <Field label="Ulangi password baru">
                                <Input
                                    type="password"
                                    value={profile.data.password_confirmation}
                                    autoComplete="new-password"
                                    onChange={(e) =>
                                        profile.setData('password_confirmation', e.target.value)
                                    }
                                />
                            </Field>
                        </div>

                        <div className="au-form-actions">
                            <Btn type="submit" icon={ICON.save} disabled={profile.processing}>
                                {profile.processing ? 'Menyimpan...' : 'Simpan'}
                            </Btn>
                            <Btn tone="ghost" onClick={logoutOthers}>
                                Logout Perangkat Lain
                            </Btn>
                        </div>
                    </form>
                </Card>

                {telegram ? <TelegramCard telegram={telegram} /> : null}

                {isDeveloper ? (
                    <Card title="Profil Publik" sub="Tampil di halaman Tentang Aplikasi">
                        <form className="au-form" onSubmit={saveAbout}>
                            <label className="au-btn au-btn--sm au-btn--ghost">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    style={{ display: 'none' }}
                                    onChange={(e) => uploadAvatar(e.target.files[0], aboutAvatarUrl)}
                                />
                                Ganti Foto Publik
                            </label>

                            <Field label="Handle" error={about.errors.handle}>
                                <Input
                                    value={about.data.handle}
                                    error={about.errors.handle}
                                    maxLength={80}
                                    onChange={(e) => about.setData('handle', e.target.value)}
                                />
                            </Field>

                            <Field label="Bio" error={about.errors.bio}>
                                <Textarea
                                    value={about.data.bio}
                                    error={about.errors.bio}
                                    maxLength={500}
                                    onChange={(e) => about.setData('bio', e.target.value)}
                                />
                            </Field>

                            <div className="au-form-grid au-form-grid-2">
                                <Field label="Instagram" error={about.errors.link_instagram}>
                                    <Input
                                        value={about.data.link_instagram}
                                        error={about.errors.link_instagram}
                                        maxLength={255}
                                        onChange={(e) =>
                                            about.setData('link_instagram', e.target.value)
                                        }
                                    />
                                </Field>

                                <Field label="GitHub" error={about.errors.link_github}>
                                    <Input
                                        value={about.data.link_github}
                                        error={about.errors.link_github}
                                        maxLength={255}
                                        onChange={(e) => about.setData('link_github', e.target.value)}
                                    />
                                </Field>
                            </div>

                            <div className="au-form-grid au-form-grid-2">
                                <Field label="Portfolio" error={about.errors.link_portfolio}>
                                    <Input
                                        value={about.data.link_portfolio}
                                        error={about.errors.link_portfolio}
                                        maxLength={255}
                                        onChange={(e) =>
                                            about.setData('link_portfolio', e.target.value)
                                        }
                                    />
                                </Field>

                                <Field label="Email publik" error={about.errors.link_email}>
                                    <Input
                                        type="email"
                                        value={about.data.link_email}
                                        error={about.errors.link_email}
                                        maxLength={150}
                                        onChange={(e) => about.setData('link_email', e.target.value)}
                                    />
                                </Field>
                            </div>

                            <div className="au-form-actions">
                                <Btn type="submit" icon={ICON.save} disabled={about.processing}>
                                    {about.processing ? 'Menyimpan...' : 'Simpan Profil Publik'}
                                </Btn>
                            </div>
                        </form>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}

/**
 * Penautan akun Telegram. Kode dibuat di sini (orang yang sudah login yang
 * membuktikan diri), lalu dikirim ke bot: /tautkan KODE. Kode hanya tampil
 * sekali, tepat setelah dibuat.
 */
function TelegramCard({ telegram }) {
    const [busy, setBusy] = useState(false);
    const [copied, setCopied] = useState(false);
    const perintah = telegram.kode ? '/tautkan ' + telegram.kode : '';

    function buatKode() {
        setBusy(true);
        router.post(telegram.kodeUrl, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    async function putus() {
        const ok = await konfirmasi('Perintah bot yang mengubah data (mis. mengunggah jadwal) tidak bisa dipakai lagi.', {
            title: 'Putuskan Telegram',
            okText: 'Putuskan',
        });
        if (!ok) return;
        setBusy(true);
        router.delete(telegram.putusUrl, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    async function salin() {
        try {
            await navigator.clipboard.writeText(perintah);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (_) {
            /* clipboard tidak tersedia (http biasa) — pengguna menyalin manual */
        }
    }

    return (
        <Card title="Telegram" sub="Pakai bot untuk mengunggah jadwal produksi">
            {telegram.kode ? (
                <div className="au-tg-kode">
                    <p>Kirim pesan ini ke bot di Telegram:</p>
                    <button type="button" className="au-tg-kode-teks" onClick={salin} title="Salin">
                        {perintah}
                    </button>
                    <small>
                        {copied ? 'Tersalin ✓ · ' : 'Ketuk untuk menyalin · '}
                        Berlaku {telegram.berlakuMenit} menit dan hanya bisa dipakai sekali.
                    </small>
                </div>
            ) : null}

            {telegram.linked ? (
                <div className="au-tg-status">
                    <div>
                        <p className="au-tg-ok">
                            <span /> Sudah tertaut
                        </p>
                        <small>Sejak {telegram.linkedAt || '-'} WIB</small>
                    </div>
                    <Btn tone="ghost" sm onClick={putus} disabled={busy}>
                        Putuskan
                    </Btn>
                </div>
            ) : telegram.kodeUrl ? (
                <div className="au-form">
                    <p className="au-hint" style={{ margin: 0 }}>
                        Selama belum tertaut, bot tidak tahu Anda siapa — jadi perintah yang mengubah data
                        (mis. mengunggah jadwal) tidak bisa dipakai.
                    </p>
                    <div>
                        <Btn onClick={buatKode} disabled={busy}>
                            {busy ? 'Membuat kode...' : 'Tautkan Telegram'}
                        </Btn>
                    </div>
                </div>
            ) : null}
        </Card>
    );
}
