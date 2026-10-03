import { Head, usePage } from '@inertiajs/react';

export default function SystemCheck({ stack }) {
    const { auth, menu } = usePage().props;

    return (
        <>
            <Head title="System Check" />

            <div className="min-h-full px-6 py-10">
                <div className="mx-auto max-w-3xl space-y-6">
                    <header>
                        <h1 className="text-2xl font-semibold text-white">
                            Fondasi React aktif
                        </h1>
                        <p className="mt-1 text-sm text-slate-400">
                            Halaman ini dirender React lewat Inertia, tanpa proses Node —
                            Apache Laragon menyajikan bundel hasil build.
                        </p>
                    </header>

                    <section className="rounded-xl border border-slate-700 bg-slate-800/50 p-5">
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
                            Stack
                        </h2>
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            {Object.entries(stack).map(([key, value]) => (
                                <div key={key} className="contents">
                                    <dt className="text-slate-400">{key}</dt>
                                    <dd className="font-mono text-slate-200">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section className="rounded-xl border border-slate-700 bg-slate-800/50 p-5">
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
                            Shared props — auth
                        </h2>
                        {auth.user ? (
                            <p className="text-sm text-slate-200">
                                {auth.user.name}{' '}
                                <span className="rounded bg-blue-900/60 px-2 py-0.5 text-xs text-blue-200">
                                    {auth.user.role}
                                </span>
                            </p>
                        ) : (
                            <p className="text-sm text-slate-400">tidak login</p>
                        )}
                    </section>

                    <section className="rounded-xl border border-slate-700 bg-slate-800/50 p-5">
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
                            Shared props — menu ({menu.length})
                        </h2>
                        <ul className="flex flex-wrap gap-2">
                            {menu.map((item) => (
                                <li
                                    key={item.key}
                                    className="rounded border border-slate-600 px-2 py-1 text-xs text-slate-300"
                                >
                                    {item.label}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}
