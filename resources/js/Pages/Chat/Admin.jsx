import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, Btn, Card, DeleteButton, Empty, ICON, Input } from '../../Components/Ui';

/** Kotak masuk pesan untuk admin — membalas & menandai sudah dibaca. */
export default function ChatAdmin({ messages, unreadCount, chatUrl, replyBase }) {
    const [replying, setReplying] = useState(null);
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);

    function sendReply(e) {
        e.preventDefault();
        if (!text.trim() || busy) return;

        setBusy(true);
        router.post(
            replyBase + '/' + replying + '/reply',
            { reply: text.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setText('');
                    setReplying(null);
                },
                onFinish: () => setBusy(false),
            },
        );
    }

    function markRead(id) {
        router.patch(replyBase + '/' + id + '/read', {}, { preserveScroll: true });
    }

    return (
        <AppLayout title="Pesan Masuk" subtitle={unreadCount + ' belum dibaca'}>
            <Card
                title="Pesan Masuk"
                sub={messages.length + ' pesan'}
                action={
                    <Btn as="a" href={chatUrl} tone="ghost" sm>
                        Buka Chat Terpadu
                    </Btn>
                }
            >
                {messages.length ? (
                    <ul className="au-list">
                        {messages.map((m) => (
                            <li className="au-msg" key={m.id}>
                                <div className="au-msg-head">
                                    <span className="au-row-title">{m.sender}</span>
                                    {m.senderRole ? (
                                        <Badge tone="muted">{m.senderRole}</Badge>
                                    ) : null}
                                    {!m.isRead && !m.mine ? (
                                        <Badge tone="accent">Baru</Badge>
                                    ) : null}
                                    <span className="au-msg-time">{m.at}</span>
                                </div>

                                <p className="au-msg-text">{m.message}</p>

                                {m.reply ? (
                                    <div className="au-msg-reply">
                                        <span className="au-row-sub">
                                            Balasan · {m.repliedAt || '—'}
                                        </span>
                                        <p className="au-msg-text">{m.reply}</p>
                                    </div>
                                ) : null}

                                <div className="au-actions">
                                    <Btn
                                        sm
                                        tone="ghost"
                                        onClick={() => {
                                            setReplying(replying === m.id ? null : m.id);
                                            setText(m.reply || '');
                                        }}
                                    >
                                        {m.reply ? 'Ubah Balasan' : 'Balas'}
                                    </Btn>
                                    {!m.isRead ? (
                                        <Btn sm tone="ghost" onClick={() => markRead(m.id)}>
                                            Tandai Dibaca
                                        </Btn>
                                    ) : null}
                                    <DeleteButton
                                        url={replyBase + '/' + m.id}
                                        title="Hapus pesan ini?"
                                        text={m.message.slice(0, 80)}
                                    />
                                </div>

                                {replying === m.id ? (
                                    <form className="au-chat-form" onSubmit={sendReply}>
                                        <Input
                                            value={text}
                                            maxLength={1000}
                                            autoFocus
                                            placeholder="Tulis balasan..."
                                            onChange={(e) => setText(e.target.value)}
                                        />
                                        <Btn type="submit" icon={ICON.save} disabled={busy}>
                                            {busy ? '...' : 'Kirim'}
                                        </Btn>
                                    </form>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <Empty>Belum ada pesan masuk.</Empty>
                )}
            </Card>
        </AppLayout>
    );
}
