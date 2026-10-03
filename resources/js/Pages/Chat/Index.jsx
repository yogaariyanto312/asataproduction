import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Btn, Card, Empty, ICON, Input } from '../../Components/Ui';
import { csrf } from '../../csrf';

function initials(name) {
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w.charAt(0).toUpperCase())
        .join('');
}

function clock(value) {
    if (!value) return '';
    const d = new Date(value);
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function isOnline(lastSeen) {
    if (!lastSeen) return false;
    return Date.now() - new Date(lastSeen).getTime() < 70000;
}

/**
 * Chat terpadu. Semua data lewat endpoint JSON yang sudah ada
 * (chat-contacts, messages/my, messages/since) supaya perilaku realtime —
 * polling pesan baru, indikator mengetik, status online — sama dengan versi
 * Blade sebelumnya.
 */
export default function ChatIndex({
    contactsUrl,
    messagesUrl,
    sinceUrl,
    storeUrl,
    pingUrl,
    typingUrl,
    readUrlBase,
    deleteUrlBase,
}) {
    const { auth } = usePage().props;
    const me = auth.user.id;

    const [contacts, setContacts] = useState([]);
    const [messages, setMessages] = useState([]);
    const [active, setActive] = useState(null);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [query, setQuery] = useState('');

    const lastIdRef = useRef(0);
    const bottomRef = useRef(null);
    const typingTimer = useRef(null);

    const loadContacts = useCallback(async () => {
        try {
            const res = await fetch(contactsUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (res.ok) setContacts(await res.json());
        } catch (_) {
            /* diamkan */
        }
    }, [contactsUrl]);

    const loadMessages = useCallback(async () => {
        try {
            const res = await fetch(messagesUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            setMessages(data);
            lastIdRef.current = data.reduce((m, x) => Math.max(m, x.id), 0);
        } catch (_) {
            /* diamkan */
        }
    }, [messagesUrl]);

    useEffect(() => {
        loadContacts();
        loadMessages();
    }, [loadContacts, loadMessages]);

    // Polling pesan baru + ping status online + refresh kontak (indikator ketik).
    useEffect(() => {
        let alive = true;

        async function tick() {
            try {
                await fetch(pingUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                });

                const res = await fetch(sinceUrl + '?last_id=' + lastIdRef.current, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok || !alive) return;

                const data = await res.json();
                if (data.messages && data.messages.length) {
                    setMessages((prev) => [...prev, ...data.messages]);
                    lastIdRef.current = data.messages.reduce(
                        (m, x) => Math.max(m, x.id),
                        lastIdRef.current,
                    );
                }
            } catch (_) {
                /* diamkan */
            }

            if (alive) loadContacts();
        }

        const timer = setInterval(tick, 5000);

        return () => {
            alive = false;
            clearInterval(timer);
        };
    }, [pingUrl, sinceUrl, loadContacts]);

    const thread = active
        ? messages.filter(
              (m) =>
                  (m.sender_id === me && m.recipient_id === active.id) ||
                  (m.sender_id === active.id && m.recipient_id === me),
          )
        : [];

    useEffect(() => {
        if (bottomRef.current) bottomRef.current.scrollIntoView({ block: 'end' });
    }, [thread.length, active]);

    async function openContact(contact) {
        setActive(contact);
        try {
            await fetch(readUrlBase + '/' + contact.id, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
        } catch (_) {
            /* diamkan */
        }
    }

    function onTyping(value) {
        setDraft(value);
        if (!active) return;

        clearTimeout(typingTimer.current);
        typingTimer.current = setTimeout(() => {
            fetch(typingUrl, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ recipient_id: active.id }),
            }).catch(() => {});
        }, 400);
    }

    async function send(e) {
        e.preventDefault();
        if (!draft.trim() || !active || sending) return;

        setSending(true);
        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ message: draft.trim(), recipient_id: active.id }),
            });
            if (res.ok) {
                setDraft('');
                await loadMessages();
            }
        } catch (_) {
            /* diamkan */
        }
        setSending(false);
    }

    async function clearThread() {
        if (!active) return;
        await fetch(deleteUrlBase + '/' + active.id, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: new URLSearchParams({ _method: 'DELETE' }),
        });
        await loadMessages();
    }

    const shownContacts = contacts.filter((c) =>
        query ? c.name.toLowerCase().includes(query.toLowerCase()) : true,
    );

    function unreadCount(contactId) {
        return messages.filter((m) => m.sender_id === contactId && m.recipient_id === me && !m.is_read)
            .length;
    }

    return (
        <AppLayout title="Chatting" subtitle={contacts.length + ' kontak'}>
            <div className="au-chat">
                <Card className="au-chat-side">
                    <div className="au-toolbar" style={{ marginBottom: 10 }}>
                        <Input
                            value={query}
                            placeholder="Cari kontak..."
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </div>

                    <div className="au-chat-contacts">
                        {shownContacts.length ? (
                            shownContacts.map((c) => {
                                const unread = unreadCount(c.id);
                                return (
                                    <button
                                        type="button"
                                        key={c.id}
                                        className={
                                            'au-chat-contact' +
                                            (active && active.id === c.id ? ' is-active' : '')
                                        }
                                        onClick={() => openContact(c)}
                                    >
                                        <span className="au-chat-avatar">
                                            {c.avatar ? (
                                                <img
                                                    src={c.avatar}
                                                    alt=""
                                                    onError={(e) => {
                                                        e.currentTarget.style.display = 'none';
                                                    }}
                                                />
                                            ) : (
                                                initials(c.name)
                                            )}
                                            {isOnline(c.last_seen_at) ? (
                                                <i className="au-chat-online" />
                                            ) : null}
                                        </span>

                                        <span className="au-chat-contact-main">
                                            <span className="au-chat-contact-name">{c.name}</span>
                                            <span className="au-chat-contact-sub">
                                                {c.is_typing
                                                    ? 'sedang mengetik...'
                                                    : c.role +
                                                      (c.department ? ' · ' + c.department : '')}
                                            </span>
                                        </span>

                                        {unread ? <Badge tone="accent">{unread}</Badge> : null}
                                    </button>
                                );
                            })
                        ) : (
                            <Empty>Tidak ada kontak.</Empty>
                        )}
                    </div>
                </Card>

                <Card className="au-chat-main">
                    {active ? (
                        <>
                            <div className="au-chat-head">
                                <span className="au-chat-avatar">
                                    {active.avatar ? (
                                        <img
                                            src={active.avatar}
                                            alt=""
                                            onError={(e) => {
                                                e.currentTarget.style.display = 'none';
                                            }}
                                        />
                                    ) : (
                                        initials(active.name)
                                    )}
                                </span>
                                <div style={{ minWidth: 0 }}>
                                    <p className="au-chat-title">{active.name}</p>
                                    <p className="au-chat-sub">
                                        {isOnline(active.last_seen_at) ? 'online' : 'offline'}
                                    </p>
                                </div>
                                <Btn tone="danger" sm icon={ICON.trash} onClick={clearThread}>
                                    Hapus Chat
                                </Btn>
                            </div>

                            <div className="au-chat-body">
                                {thread.length ? (
                                    thread.map((m) => (
                                        <div
                                            key={m.id}
                                            className={
                                                'au-bubble' + (m.sender_id === me ? ' is-mine' : '')
                                            }
                                        >
                                            <span className="au-bubble-text">{m.message}</span>
                                            <span className="au-bubble-time">
                                                {clock(m.created_at)}
                                            </span>
                                        </div>
                                    ))
                                ) : (
                                    <Empty>Belum ada percakapan. Kirim pesan pertama.</Empty>
                                )}
                                <div ref={bottomRef} />
                            </div>

                            <form className="au-chat-form" onSubmit={send}>
                                <Input
                                    value={draft}
                                    maxLength={1000}
                                    placeholder="Tulis pesan..."
                                    onChange={(e) => onTyping(e.target.value)}
                                />
                                <Btn type="submit" disabled={sending || !draft.trim()}>
                                    {sending ? '...' : 'Kirim'}
                                </Btn>
                            </form>
                        </>
                    ) : (
                        <Empty>Pilih kontak di sebelah kiri untuk mulai mengobrol.</Empty>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
