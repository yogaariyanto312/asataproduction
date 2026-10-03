import { useState } from 'react';
import { router } from '@inertiajs/react';

/* ── ikon ───────────────────────────────────────────────────────────────── */

export const ICON = {
    plus: 'M12 4v16m8-8H4',
    search: 'M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z',
    edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z',
    trash: 'M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3',
    eye: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7z',
    back: 'M15 19l-7-7 7-7',
    save: 'M5 13l4 4L19 7',
    close: 'M6 18L18 6M6 6l12 12',
    download: 'M12 4v12m0 0l-4-4m4 4l4-4M4 20h16',
    filter: 'M4 6h16M7 12h10M10 18h4',
    check: 'M5 13l4 4L19 7',
    alert: 'M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0l-7.1 12.25A2 2 0 004.98 19z',
    user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    refresh: 'M4 4v6h6M20 20v-6h-6M20 9A8 8 0 006 5.3L4 7m0 8a8 8 0 0014 3.7l2-1.7',
};

export function Icon({ path, className }) {
    return (
        <svg
            className={className}
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            {path.split(' M').map((seg, i) => (
                <path key={i} d={i === 0 ? seg : 'M' + seg} />
            ))}
        </svg>
    );
}

/* ── wadah ──────────────────────────────────────────────────────────────── */

export function Card({ title, sub, action, children, className }) {
    return (
        <section className={'au-card' + (className ? ' ' + className : '')}>
            {title || action ? (
                <div className="au-card-head">
                    <div style={{ minWidth: 0 }}>
                        {title ? <h2 className="au-card-title">{title}</h2> : null}
                        {sub ? <p className="au-card-sub">{sub}</p> : null}
                    </div>
                    {action ? <div style={{ marginLeft: 'auto' }}>{action}</div> : null}
                </div>
            ) : null}
            {children}
        </section>
    );
}

export function Empty({ children }) {
    return <p className="au-empty">{children}</p>;
}

export function Badge({ tone, children }) {
    return <span className={'au-badge' + (tone ? ' au-badge--' + tone : '')}>{children}</span>;
}

/* ── tombol ─────────────────────────────────────────────────────────────── */

export function Btn({ as = 'button', icon, tone, sm, children, ...rest }) {
    const cls =
        'au-btn' +
        (tone ? ' au-btn--' + tone : '') +
        (sm ? ' au-btn--sm' : '') +
        (rest.className ? ' ' + rest.className : '');
    const props = { ...rest, className: cls };
    const inner = (
        <>
            {icon ? <Icon path={icon} /> : null}
            {children}
        </>
    );

    if (as === 'a') return <a {...props}>{inner}</a>;

    return (
        <button type={rest.type || 'button'} {...props}>
            {inner}
        </button>
    );
}

export function IconBtn({ as = 'button', icon, danger, title, ...rest }) {
    const cls = 'au-icon-btn' + (danger ? ' au-icon-btn--danger' : '');
    if (as === 'a') {
        return (
            <a className={cls} title={title} aria-label={title} {...rest}>
                <Icon path={icon} />
            </a>
        );
    }
    return (
        <button type="button" className={cls} title={title} aria-label={title} {...rest}>
            <Icon path={icon} />
        </button>
    );
}

/* ── form ───────────────────────────────────────────────────────────────── */

export function Field({ label, required, error, hint, children }) {
    return (
        <div className="au-field">
            {label ? (
                <label className="au-label">
                    {label}
                    {required ? <span className="au-req"> *</span> : null}
                </label>
            ) : null}
            {children}
            {hint && !error ? <span className="au-hint">{hint}</span> : null}
            {error ? <span className="au-error">{error}</span> : null}
        </div>
    );
}

export function Input({ error, ...rest }) {
    return <input className={'au-input' + (error ? ' has-error' : '')} {...rest} />;
}

export function Textarea({ error, ...rest }) {
    return <textarea className={'au-textarea' + (error ? ' has-error' : '')} {...rest} />;
}

export function Select({ error, options = [], placeholder, ...rest }) {
    return (
        <select className={'au-select' + (error ? ' has-error' : '')} {...rest}>
            {placeholder ? <option value="">{placeholder}</option> : null}
            {options.map((o) => (
                <option key={String(o.value)} value={o.value}>
                    {o.label}
                </option>
            ))}
        </select>
    );
}

export function Check({ label, ...rest }) {
    return (
        <label className="au-check">
            <input type="checkbox" {...rest} />
            <span>{label}</span>
        </label>
    );
}

/* ── tabel + paginasi ───────────────────────────────────────────────────── */

export function Table({ head, children, empty }) {
    return (
        <div className="au-table-wrap">
            <table className="au-table">
                <thead>
                    <tr>
                        {head.map((h, i) => (
                            <th key={i} style={h.align ? { textAlign: h.align } : undefined}>
                                {h.label ?? h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
            {empty ? <Empty>{empty}</Empty> : null}
        </div>
    );
}

/**
 * Paginasi Laravel. `paginator` adalah hasil serialisasi LengthAwarePaginator
 * (punya links[], current_page, last_page, total, from, to).
 */
export function Pagination({ paginator }) {
    if (!paginator || !paginator.links || paginator.last_page <= 1) return null;

    return (
        <div className="au-pagination">
            {paginator.links.map((link, i) => {
                const label = link.label
                    .replace('&laquo; Previous', '‹')
                    .replace('Next &raquo;', '›')
                    .replace('pagination.previous', '‹')
                    .replace('pagination.next', '›');

                if (!link.url) {
                    return (
                        <span className="au-page-link is-disabled" key={i}>
                            {label}
                        </span>
                    );
                }

                return (
                    <a
                        key={i}
                        href={link.url}
                        className={'au-page-link' + (link.active ? ' is-current' : '')}
                    >
                        {label}
                    </a>
                );
            })}
            <span className="au-page-info">
                {paginator.from || 0}–{paginator.to || 0} dari {paginator.total}
            </span>
        </div>
    );
}

/* ── konfirmasi hapus ───────────────────────────────────────────────────── */

/**
 * Tombol hapus dengan modal konfirmasi. Sengaja memakai router.delete milik
 * Inertia, bukan form + method spoofing, supaya flash message dari server
 * langsung masuk ke toast layout.
 */
export function DeleteButton({ url, title = 'Hapus data?', text, label, onDone }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    function confirm() {
        setBusy(true);
        router.delete(url, {
            preserveScroll: true,
            onFinish: () => {
                setBusy(false);
                setOpen(false);
                if (onDone) onDone();
            },
        });
    }

    return (
        <>
            {label ? (
                <Btn tone="danger" sm icon={ICON.trash} onClick={() => setOpen(true)}>
                    {label}
                </Btn>
            ) : (
                <IconBtn danger icon={ICON.trash} title="Hapus" onClick={() => setOpen(true)} />
            )}

            {open ? (
                <div className="au-modal-backdrop" onClick={() => !busy && setOpen(false)}>
                    <div className="au-modal" onClick={(e) => e.stopPropagation()}>
                        <h3 className="au-modal-title">{title}</h3>
                        <p className="au-modal-text">
                            {text || 'Tindakan ini tidak bisa dibatalkan.'}
                        </p>
                        <div className="au-modal-actions">
                            <Btn tone="ghost" onClick={() => setOpen(false)} disabled={busy}>
                                Batal
                            </Btn>
                            <Btn tone="danger" onClick={confirm} disabled={busy}>
                                {busy ? 'Menghapus...' : 'Hapus'}
                            </Btn>
                        </div>
                    </div>
                </div>
            ) : null}
        </>
    );
}

/* ── toolbar pencarian ──────────────────────────────────────────────────── */

/**
 * Toolbar filter yang mengirim ulang query string ke URL yang sama.
 * `fields` = [{ name, type, placeholder, options }].
 */
export function FilterBar({ url, fields, values, children }) {
    const [state, setState] = useState(values || {});

    function apply(next) {
        setState(next);
        const params = {};
        Object.keys(next).forEach((k) => {
            if (next[k] !== '' && next[k] !== null && next[k] !== undefined) params[k] = next[k];
        });
        router.get(url, params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function reset() {
        setState({});
        router.get(url, {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    let timer = null;
    function onText(name, value) {
        const next = { ...state, [name]: value };
        setState(next);
        clearTimeout(timer);
        timer = setTimeout(() => apply(next), 400);
    }

    return (
        <div className="au-toolbar">
            {fields.map((f) => {
                if (f.type === 'select') {
                    return (
                        <Select
                            key={f.name}
                            value={state[f.name] || ''}
                            placeholder={f.placeholder}
                            options={f.options}
                            onChange={(e) => apply({ ...state, [f.name]: e.target.value })}
                        />
                    );
                }

                return (
                    <Input
                        key={f.name}
                        type={f.type || 'text'}
                        value={state[f.name] || ''}
                        placeholder={f.placeholder}
                        onChange={(e) =>
                            f.type === 'date'
                                ? apply({ ...state, [f.name]: e.target.value })
                                : onText(f.name, e.target.value)
                        }
                    />
                );
            })}

            <Btn tone="ghost" sm icon={ICON.refresh} onClick={reset}>
                Reset
            </Btn>

            {children ? <div className="au-toolbar-end">{children}</div> : null}
        </div>
    );
}
