import { usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

/** Halaman diagnostik developer: bukti bahwa React + Inertia aktif dan props bersama terbaca. */
export default function SystemCheck({ stack }) {
    const { auth, menu } = usePage().props;

    return (
        <AppLayout title="System Check" subtitle="Fondasi React aktif">
            <div style={{ display: 'grid', gap: 16, maxWidth: 760 }}>
                <p className="au-hint" style={{ margin: 0 }}>
                    Halaman ini dirender React lewat Inertia, tanpa proses Node: server web menyajikan bundel hasil build.
                </p>

                <section className="au-card">
                    <h2 className="au-card-title" style={{ marginBottom: 10 }}>Stack</h2>
                    <dl style={{ display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '6px 24px', margin: 0, fontSize: 13 }}>
                        {Object.entries(stack).map(([key, value]) => (
                            <div key={key} style={{ display: 'contents' }}>
                                <dt style={{ color: 'var(--au-muted)' }}>{key}</dt>
                                <dd style={{ margin: 0, fontFamily: 'ui-monospace, monospace', fontWeight: 600 }}>{value}</dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section className="au-card">
                    <h2 className="au-card-title" style={{ marginBottom: 10 }}>Shared props: auth</h2>
                    {auth.user ? (
                        <p style={{ margin: 0, fontSize: 13 }}>
                            {auth.user.name} <span className="au-badge au-badge--accent">{auth.user.role}</span>
                        </p>
                    ) : (
                        <p className="au-hint" style={{ margin: 0 }}>tidak login</p>
                    )}
                </section>

                <section className="au-card">
                    <h2 className="au-card-title" style={{ marginBottom: 10 }}>Shared props: menu ({menu.length})</h2>
                    <ul style={{ display: 'flex', flexWrap: 'wrap', gap: 8, margin: 0, padding: 0, listStyle: 'none' }}>
                        {menu.map((item) => (
                            <li key={item.key} className="au-badge">{item.label}</li>
                        ))}
                    </ul>
                </section>
            </div>
        </AppLayout>
    );
}
