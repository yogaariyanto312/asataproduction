import { useForm } from '@inertiajs/react';
import AuthShell from './Shell';

export default function ResetPassword({ action, loginUrl, token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token: token || '',
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        post(action);
    }

    const firstError = errors.email || errors.password || errors.token;

    return (
        <AuthShell title="Reset Password" subtitle="Buat password baru untuk akun Anda">
            {firstError ? (
                <div className="lr-alert" role="alert">
                    <span>{firstError}</span>
                </div>
            ) : null}

            <form onSubmit={submit}>
                <label className={'lr-field' + (errors.email ? ' has-error' : '')}>
                    <svg className="lr-field-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm8 7.3L4.4 7h15.2L12 12.3Z" />
                    </svg>
                    <input
                        type="email"
                        value={data.email}
                        placeholder="Email"
                        aria-label="Email"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </label>

                <label className={'lr-field' + (errors.password ? ' has-error' : '')}>
                    <svg className="lr-field-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 0 1 6 0v3H9Z" />
                    </svg>
                    <input
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        placeholder="Password baru (min. 8 karakter)"
                        aria-label="Password baru"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </label>

                <label className="lr-field">
                    <svg className="lr-field-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 0 1 6 0v3H9Z" />
                    </svg>
                    <input
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        placeholder="Ulangi password baru"
                        aria-label="Ulangi password baru"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </label>

                <button className="lr-btn" type="submit" disabled={processing}>
                    <span>{processing ? 'MENYIMPAN...' : 'SIMPAN PASSWORD BARU'}</span>
                </button>
            </form>

            <p className="lr-footnote">
                <a className="lr-link" href={loginUrl}>
                    Kembali ke halaman login
                </a>
            </p>
        </AuthShell>
    );
}
