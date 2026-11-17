import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { Icon } from '../Components/Ui';
import { TOPIK } from '../tutorialTopik';

const TANYA = 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
const BUKA = 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14';
const PENA = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
const CHEV = 'M19 9l-7 7-7-7';

/**
 * Tutorial — mengikuti Production-QC-Logging-System: kepala bergradasi,
 * panduan interaktif (public/panduan) dalam iframe + tab Video opsional
 * (muncul hanya bila URL diisi; ubah lewat izin tutorial.edit), lalu daftar
 * tanya-jawab yang bisa dibentang.
 */
export default function Tutorial({ iframeUrl, canEdit, embedAction, panduanUrl, chatUrl }) {
    const [tab, setTab] = useState('panduan');
    const [edit, setEdit] = useState(false);
    const [buka, setBuka] = useState(null);
    const { data, setData, post, processing, errors } = useForm({ iframe_url: iframeUrl || '' });

    function simpan(e) {
        e.preventDefault();
        post(embedAction, { preserveScroll: true, onSuccess: () => setEdit(false) });
    }

    return (
        <AppLayout title="Tutorial" subtitle="Panduan penggunaan aplikasi">
            <div className="au-tu">
                <div className="au-tu-hero">
                    <span>
                        <Icon path={TANYA} />
                    </span>
                    <div>
                        <h2>Panduan Penggunaan Aplikasi</h2>
                        <p>Pilih topik di bawah untuk melihat langkah-langkahnya</p>
                    </div>
                </div>

                <div className="au-tu-kartu">
                    <div className="au-tu-bar">
                        <div className="au-tu-tab">
                            <button type="button" className={tab === 'panduan' ? 'is-aktif' : ''} onClick={() => setTab('panduan')}>
                                Panduan
                            </button>
                            {iframeUrl ? (
                                <button type="button" className={tab === 'video' ? 'is-aktif' : ''} onClick={() => setTab('video')}>
                                    Video
                                </button>
                            ) : null}
                        </div>
                        <div className="au-tu-aksi">
                            <a href={panduanUrl} target="_blank" rel="noopener noreferrer">
                                <Icon path={BUKA} />
                                Buka di tab baru
                            </a>
                            {canEdit ? (
                                <button type="button" onClick={() => setEdit(!edit)}>
                                    <Icon path={PENA} />
                                    Video
                                </button>
                            ) : null}
                        </div>
                    </div>

                    {canEdit && edit ? (
                        <form className="au-tu-edit" method="POST" action={embedAction} onSubmit={simpan}>
                            <div style={{ flex: 1 }}>
                                <label>
                                    URL Video <span>(opsional — YouTube embed; kosongkan untuk menyembunyikan tab Video)</span>
                                </label>
                                <input
                                    className="au-input au-mono"
                                    placeholder="https://www.youtube.com/embed/VIDEO_ID"
                                    value={data.iframe_url}
                                    onChange={(e) => setData('iframe_url', e.target.value)}
                                />
                                {errors.iframe_url ? <span className="au-error">{errors.iframe_url}</span> : null}
                            </div>
                            <button type="submit" disabled={processing}>
                                Simpan
                            </button>
                        </form>
                    ) : null}

                    {/* Tinggi iframe panduan diatur panduan.js sendiri (mengikuti isinya). */}
                    <div style={{ display: tab === 'panduan' ? 'block' : 'none' }}>
                        <iframe id="panduan-frame" src={panduanUrl} title="Panduan penggunaan aplikasi" className="au-tu-frame" loading="lazy" />
                    </div>
                    {iframeUrl && tab === 'video' ? (
                        <div className="au-tu-video">
                            <iframe
                                src={iframeUrl}
                                title="Video tutorial"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowFullScreen
                            />
                        </div>
                    ) : null}
                </div>

                {TOPIK.map((t) => (
                    <div className="au-tu-kartu" key={t.id}>
                        <button type="button" className="au-tu-tanya" onClick={() => setBuka(buka === t.id ? null : t.id)} aria-expanded={buka === t.id}>
                            <span className={'au-tu-ikon is-' + t.tone}>
                                <Icon path={t.icon} />
                            </span>
                            <b>{t.title}</b>
                            <svg className="au-tu-chev" style={{ transform: buka === t.id ? 'rotate(180deg)' : '' }} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d={CHEV} />
                            </svg>
                        </button>
                        <div className={'au-tu-jawab' + (buka === t.id ? ' is-buka' : '')}>
                            <div>
                                <ol>
                                    {t.steps.map((s, i) => (
                                        <li key={i}>
                                            <span className={'au-tu-no is-' + t.tone}>{i + 1}</span>
                                            {/* Langkah statis dari kode (bukan isian pengguna). */}
                                            <p dangerouslySetInnerHTML={{ __html: s }} />
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </div>
                    </div>
                ))}

                <p className="au-tu-kaki">
                    Butuh bantuan lebih lanjut? Hubungi developer atau admin melalui fitur{' '}
                    {chatUrl ? <a href={chatUrl}>Chatting</a> : 'Chatting'}.
                </p>
            </div>
        </AppLayout>
    );
}
