import { Head } from '@inertiajs/react';
import '../../../css/login-robot.css';

/**
 * Kerangka dua panel untuk halaman auth selain login (lupa & reset password).
 * Sengaja memakai kelas .lr-* yang sama dengan halaman login supaya tampilannya
 * konsisten tanpa menduplikasi CSS.
 */
export default function AuthShell({ title, subtitle, children }) {
    return (
        <>
            <Head title={title} />

            <div className="lr-scene">
                <aside className="lr-brand-panel">
                    <div className="lr-brand-head">
                        <span className="lr-brand-mark">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                        </span>
                        <span className="lr-brand-name">Asata Production System</span>
                    </div>

                    <div className="lr-brand-body">
                        <h2 className="lr-brand-title">
                            Sistem Pencatatan
                            <br />
                            Produksi Digital
                        </h2>
                        <p className="lr-brand-text">
                            Hubungi administrator bila Anda kesulitan mengakses akun.
                        </p>
                    </div>

                    <p className="lr-brand-foot">&copy; {new Date().getFullYear()} Asata Production System</p>
                </aside>

                <div className="lr-form-panel">
                    <main className="lr-stage">
                        <div className="lr-card">
                            <h1 className="lr-title">{title}</h1>
                            <p className="lr-subtitle">{subtitle}</p>
                            {children}
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
