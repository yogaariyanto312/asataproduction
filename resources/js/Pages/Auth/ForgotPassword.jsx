import { useForm, usePage } from '@inertiajs/react';
import AuthShell from './Shell';

export default function ForgotPassword({ action, loginUrl }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ identifier: '' });

    function submit(e) {
        e.preventDefault();
        post(action);
    }

    return (
        <AuthShell
            title="Lupa Password"
            subtitle="Masukkan username atau email akun Anda"
        >
            {errors.identifier ? (
                <div className="lr-alert" role="alert">
                    <span>{errors.identifier}</span>
                </div>
            ) : null}

            {flash && flash.success ? (
                <div className="lr-alert lr-alert--ok">
                    <span>{flash.success}</span>
                </div>
            ) : null}

            <form onSubmit={submit}>
                <label className={'lr-field' + (errors.identifier ? ' has-error' : '')}>
                    <svg className="lr-field-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.5 4.5 0 0 0 12 12Zm0 2c-3.9 0-8 2-8 5v1.5h16V19c0-3-4.1-5-8-5Z" />
                    </svg>
                    <input
                        type="text"
                        value={data.identifier}
                        autoFocus
                        placeholder="Username atau email"
                        aria-label="Username atau email"
                        onChange={(e) => setData('identifier', e.target.value)}
                    />
                </label>

                <button className="lr-btn" type="submit" disabled={processing}>
                    <span>{processing ? 'MENGIRIM...' : 'KIRIM LINK RESET'}</span>
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
