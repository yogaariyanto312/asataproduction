import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Icon } from '../../Components/Ui';
import { csrf } from '../../csrf';
import { konfirmasi } from '../../dialog';

const P = {
    tele: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
    dc: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z',
    lap: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    rawat: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    aman: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    konsol: 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    orang: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    mata: 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    kirim: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
    gambar: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    chev: 'M19 9l-7 7-7-7',
};

/**
 * Saklar aktif/nonaktif. Pasangan hidden "0" + checkbox "1" itu PENTING:
 * checkbox yang tidak dicentang tidak ikut terkirim, sedangkan controller
 * hanya menyentuh field yang dikirim — tanpa hidden, saklar tak bisa dimatikan.
 * Urutannya hidden dulu (nilai terakhir yang dipakai PHP).
 */
function Saklar({ nama, aktif, judul }) {
    return (
        <label className="au-st-saklar">
            <input type="hidden" name={nama} value="0" />
            <input type="checkbox" name={nama} value="1" defaultChecked={aktif} aria-label={judul} />
            <span className="au-st-saklar-batang" />
            <span className="au-st-saklar-mati">Nonaktif</span>
            <span className="au-st-saklar-nyala">Aktif</span>
        </label>
    );
}

function Kepala({ ikon, warna, judul, sub, children }) {
    return (
        <div className="au-st-kepala">
            <div className="au-st-kepala-kiri">
                <span className={'au-st-ikon is-' + warna}>
                    <Icon path={ikon} />
                </span>
                <div>
                    <h2>{judul}</h2>
                    <p>{sub}</p>
                </div>
            </div>
            {children}
        </div>
    );
}

function Rahasia({ id, nama, nilai, placeholder, label }) {
    const [lihat, setLihat] = useState(false);
    return (
        <div className="au-st-rahasia">
            <input id={id} type={lihat ? 'text' : 'password'} name={nama} defaultValue={nilai || ''} placeholder={placeholder} autoComplete="off" spellCheck="false" className="au-input au-mono" />
            <button type="button" onClick={() => setLihat(!lihat)} aria-label={lihat ? 'Sembunyikan' : label}>
                <Icon path={P.mata} />
            </button>
        </div>
    );
}

/**
 * Settings — mengikuti Production-QC-Logging-System: SATU form untuk seluruh
 * pengaturan (Telegram, Discord, Laporan & Alert, Maintenance, Keamanan) dengan
 * bilah simpan melayang yang menandai perubahan belum tersimpan; lalu Perintah
 * Bot (webhook), contoh format pesan, dan profil di halaman Tentang.
 */
export default function SettingsBot(props) {
    const { setting, action, testUrl, webhookRegisterUrl, webhookInfoUrl, dailyReportUrl, webhookUrl, perintah, profil, aboutInfoUrl, aboutAvatarUrl, maintenanceUntilLabel } = props;
    const { errors, flash } = usePage().props;
    const form = useRef(null);
    const awal = useRef('');
    const [ubah, setUbah] = useState(false);
    const [kirim, setKirim] = useState(false);

    // Seluruh isi form dirangkum jadi satu untaian (bukan FormData.get per
    // entri: tiap saklar punya DUA entri bernama sama).
    const sidik = () => Array.from(new FormData(form.current).entries()).map(([k, v]) => k + '=' + v).join('&');
    useEffect(() => {
        awal.current = sidik();
        setUbah(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [setting]);
    const segarkan = () => setUbah(sidik() !== awal.current);

    function simpan(e) {
        e.preventDefault();
        setKirim(true);
        router.post(action, new FormData(form.current), { preserveScroll: true, onFinish: () => setKirim(false) });
    }

    function batalkan() {
        form.current.reset();
        segarkan();
    }

    const aksi = (url, data = {}) => router.post(url, data, { preserveScroll: true });

    return (
        <AppLayout title="Settings" subtitle="Notifikasi bot, laporan, maintenance, dan keamanan">
            <div className="au-st">
                <form ref={form} method="POST" action={action} className="au-st-form" onSubmit={simpan} onInput={segarkan} onChange={segarkan}>
                    <input type="hidden" name="_token" value={csrf()} />

                    <section className="au-st-kartu">
                        <Kepala ikon={P.tele} warna="sky" judul="Telegram" sub="Kirim aktivitas ke grup / channel">
                            <Saklar nama="telegram_enabled" aktif={setting.telegram_enabled} judul="Aktifkan Telegram" />
                        </Kepala>
                        <div className="au-st-isi">
                            <div>
                                <label className="au-st-label" htmlFor="telegram_token">
                                    Bot Token <span>(dari BotFather)</span>
                                </label>
                                <Rahasia id="telegram_token" nama="telegram_token" nilai={setting.telegram_token} placeholder="1234567890:AAFxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" label="Tampilkan token" />
                                {errors.telegram_token ? <p className="au-st-galat">{errors.telegram_token}</p> : null}
                            </div>
                            <div>
                                <label className="au-st-label" htmlFor="telegram_chat_id">Chat ID — Log Server</label>
                                <input id="telegram_chat_id" name="telegram_chat_id" className="au-input" defaultValue={setting.telegram_chat_id || ''} placeholder="-1001234567890" autoComplete="off" />
                                <p className="au-st-bantu">Aktivitas: login, input, edit, hapus.</p>
                                {errors.telegram_chat_id ? <p className="au-st-galat">{errors.telegram_chat_id}</p> : null}
                            </div>
                            <div>
                                <label className="au-st-label" htmlFor="telegram_report_chat_id">Chat ID — Laporan Produksi</label>
                                <input id="telegram_report_chat_id" name="telegram_report_chat_id" className="au-input" defaultValue={setting.telegram_report_chat_id || ''} placeholder="Kosongkan untuk pakai Chat ID Log Server" autoComplete="off" />
                                <p className="au-st-bantu">Grup khusus laporan harian &amp; alert reject. Kosong = ikut Log Server.</p>
                                {errors.telegram_report_chat_id ? <p className="au-st-galat">{errors.telegram_report_chat_id}</p> : null}
                            </div>
                            <details className="au-st-cara">
                                <summary>Cara mendapatkan Chat ID</summary>
                                <ol>
                                    <li>Tambahkan bot ke grup, lalu jadikan admin.</li>
                                    <li>Kirim satu pesan apa saja di grup itu.</li>
                                    <li>
                                        Buka <code>api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code>.
                                    </li>
                                    <li>
                                        Salin nilai <code>chat.id</code> (grup biasanya diawali <code>-100</code>).
                                    </li>
                                </ol>
                            </details>
                            <button type="button" className="au-st-tombol" onClick={() => aksi(testUrl, { type: 'telegram' })}>
                                <Icon path={P.kirim} />
                                Kirim pesan uji
                            </button>
                        </div>
                    </section>

                    <section className="au-st-kartu">
                        <Kepala ikon={P.dc} warna="indigo" judul="Discord" sub="Kirim aktivitas ke channel">
                            <Saklar nama="discord_enabled" aktif={setting.discord_enabled} judul="Aktifkan Discord" />
                        </Kepala>
                        <div className="au-st-isi">
                            <div>
                                <label className="au-st-label" htmlFor="discord_webhook">Webhook URL</label>
                                <Rahasia id="discord_webhook" nama="discord_webhook" nilai={setting.discord_webhook} placeholder="https://discord.com/api/webhooks/..." label="Tampilkan webhook" />
                                <p className="au-st-bantu">Channel → Edit Channel → Integrations → Webhooks → New Webhook.</p>
                                {errors.discord_webhook ? <p className="au-st-galat">{errors.discord_webhook}</p> : null}
                            </div>
                            <button type="button" className="au-st-tombol" onClick={() => aksi(testUrl, { type: 'discord' })}>
                                <Icon path={P.kirim} />
                                Kirim pesan uji
                            </button>
                        </div>
                    </section>

                    <section className="au-st-kartu">
                        <Kepala ikon={P.lap} warna="amber" judul="Laporan & Alert" sub="Rekap harian dan peringatan reject">
                            <Saklar nama="report_enabled" aktif={setting.report_enabled} judul="Aktifkan laporan harian" />
                        </Kepala>
                        <div className="au-st-isi">
                            <div>
                                <label className="au-st-label" htmlFor="reject_threshold">Batas Reject untuk Alert</label>
                                <div className="au-st-persen">
                                    <input id="reject_threshold" type="number" name="reject_threshold" step="0.5" min="0" max="100" inputMode="decimal" className="au-input" defaultValue={setting.reject_threshold ?? 5} />
                                    <span>%</span>
                                </div>
                                <p className="au-st-bantu">Alert dikirim kalau reject rate suatu produk melewati angka ini.</p>
                                {errors.reject_threshold ? <p className="au-st-galat">{errors.reject_threshold}</p> : null}
                            </div>
                            <div className="au-st-catatan">
                                <p>Laporan berisi ringkasan produksi per kategori, total qty, total reject, dan reject rate hari itu. Dikirim ke semua bot yang aktif.</p>
                                <p>
                                    Butuh scheduler aktif di server: <code>* * * * * php artisan schedule:run</code>
                                </p>
                            </div>
                            <button
                                type="button"
                                className="au-st-tombol"
                                onClick={async () => {
                                    if (await konfirmasi('Kirim laporan produksi hari ini ke semua bot yang aktif?', { title: 'Kirim Laporan Harian', type: 'warning', okText: 'Kirim' })) aksi(dailyReportUrl);
                                }}
                            >
                                <Icon path={P.kirim} />
                                Kirim laporan sekarang
                            </button>
                        </div>
                    </section>

                    <section className="au-st-kartu">
                        <Kepala ikon={P.rawat} warna="orange" judul="Maintenance" sub="Tutup aplikasi sementara">
                            <Saklar nama="maintenance_mode" aktif={setting.maintenance_mode} judul="Aktifkan maintenance" />
                        </Kepala>
                        <div className="au-st-isi">
                            <p className="au-st-teks">
                                Saat aktif, semua role selain <strong>Developer</strong> melihat layar peringatan dan tidak bisa mengakses aplikasi.
                            </p>
                            <div>
                                <label className="au-st-label" htmlFor="maintenance_message">
                                    Pesan <span>(opsional)</span>
                                </label>
                                <input id="maintenance_message" name="maintenance_message" className="au-input" defaultValue={setting.maintenance_message || ''} placeholder="Sistem sedang dalam pemeliharaan." />
                                {errors.maintenance_message ? <p className="au-st-galat">{errors.maintenance_message}</p> : null}
                            </div>
                            <div>
                                <label className="au-st-label" htmlFor="maintenance_until">
                                    Selesai otomatis <span>(opsional)</span>
                                </label>
                                <input id="maintenance_until" type="datetime-local" name="maintenance_until" className="au-input au-st-tanggal" defaultValue={setting.maintenance_until || ''} />
                                <p className="au-st-bantu">
                                    Kosongkan agar maintenance berlaku sampai dimatikan manual.
                                    {maintenanceUntilLabel ? <b className="au-st-sekarang"> Sekarang: {maintenanceUntilLabel}</b> : null}
                                </p>
                                {errors.maintenance_until ? <p className="au-st-galat">{errors.maintenance_until}</p> : null}
                            </div>
                        </div>
                    </section>

                    <section className="au-st-kartu">
                        <Kepala ikon={P.aman} warna="slate" judul="Keamanan" sub="Batasi developer tools di peramban">
                            <Saklar nama="disable_devtools" aktif={setting.disable_devtools} judul="Batasi DevTools" />
                        </Kepala>
                        <div className="au-st-isi">
                            <p className="au-st-teks">Saat aktif, semua role kecuali Developer tidak bisa membuka F12, Ctrl+Shift+I/J/C, Ctrl+U, dan klik kanan.</p>
                            <p className="au-st-awas">Ini pencegahan dasar, bukan perlindungan mutlak — kode sisi peramban tetap bisa dibaca lewat cara lain. Jangan pernah menaruh rahasia di sana.</p>
                        </div>
                    </section>

                    {/* Bilah simpan selebar kartu, melayang di bawah. */}
                    <div className="au-st-bilah">
                        <div>
                            <span className={'au-st-titik' + (ubah ? ' is-ubah' : '')} />
                            <p className={ubah ? 'is-ubah' : ''}>{ubah ? 'Ada perubahan yang belum disimpan' : 'Semua perubahan tersimpan'}</p>
                            {ubah ? (
                                <button type="button" className="au-st-batal" onClick={batalkan}>
                                    Batalkan
                                </button>
                            ) : null}
                            <button type="submit" className="au-st-simpan" disabled={kirim}>
                                {kirim ? 'Menyimpan…' : 'Simpan'}
                            </button>
                        </div>
                    </div>
                </form>

                <section className="au-st-kartu">
                    <Kepala ikon={P.konsol} warna="emerald" judul="Perintah Bot" sub="Agar bot bisa menerima perintah dari Telegram" />
                    <div className="au-st-isi">
                        {flash?.webhook_info ? <p className="au-st-info">{flash.webhook_info}</p> : null}
                        <dl className="au-st-perintah">
                            {perintah.map((p) => (
                                <div key={p.contoh}>
                                    <dt>{p.contoh}</dt>
                                    <dd>{p.arti}</dd>
                                </div>
                            ))}
                        </dl>
                        <div>
                            <p className="au-st-label">URL Webhook</p>
                            <code className="au-st-url">{webhookUrl}</code>
                            <p className="au-st-bantu">Harus bisa diakses publik lewat HTTPS. Di jaringan lokal, Telegram tidak bisa menjangkaunya.</p>
                        </div>
                        <div className="au-st-baris">
                            <button type="button" className="au-st-hijau" onClick={() => aksi(webhookRegisterUrl)}>
                                Daftarkan webhook
                            </button>
                            <button type="button" className="au-st-tombol" onClick={() => aksi(webhookInfoUrl)}>
                                Cek status
                            </button>
                        </div>
                    </div>
                </section>

                <details className="au-st-kartu au-st-contoh">
                    <summary>
                        <span>Contoh format pesan notifikasi</span>
                        <Icon path={P.chev} />
                    </summary>
                    <div>
                        <div className="au-st-catatan">
                            <p>
                                <b>➕ Create</b>
                            </p>
                            <p>Input produksi: Trafo 50KVA #001</p>
                            <p className="au-redup">oleh Yoga · Chrome / Windows · 08:15 WIB</p>
                        </div>
                        <div className="au-st-warna">
                            {[
                                ['Create', '#10b981'],
                                ['Update', '#f59e0b'],
                                ['Delete', '#f43f5e'],
                                ['Login', '#0ea5e9'],
                                ['Logout', '#64748b'],
                            ].map(([a, w]) => (
                                <span key={a}>
                                    <i style={{ background: w }} />
                                    {a}
                                </span>
                            ))}
                        </div>
                        <p className="au-st-bantu">Warna di atas dipakai sebagai warna sisi kiri embed Discord.</p>
                    </div>
                </details>

                <ProfilTentang profil={profil} infoUrl={aboutInfoUrl} avatarUrl={aboutAvatarUrl} errors={errors} />
            </div>
        </AppLayout>
    );
}

function ProfilTentang({ profil, infoUrl, avatarUrl, errors }) {
    const [p, setP] = useState(profil);
    const [foto, setFoto] = useState(null);
    const [pratinjau, setPratinjau] = useState(profil.foto);
    const set = (k, v) => setP((x) => ({ ...x, [k]: v }));

    return (
        <section className="au-st-kartu">
            <Kepala ikon={P.orang} warna="violet" judul="Profil di Halaman Tentang" sub="Bio, tautan, dan foto yang tampil di menu Tentang Aplikasi" />
            <form
                className="au-st-isi"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.post(infoUrl, p, { preserveScroll: true });
                }}
            >
                <div>
                    <label className="au-st-label" htmlFor="handle">Handle</label>
                    <input id="handle" className="au-input" placeholder="@yoga" value={p.handle || ''} onChange={(e) => set('handle', e.target.value)} />
                </div>
                <div>
                    <label className="au-st-label" htmlFor="bio">Bio</label>
                    <textarea id="bio" className="au-textarea" rows={5} style={{ resize: 'none' }} placeholder="Tuliskan bio singkat..." value={p.bio || ''} onChange={(e) => set('bio', e.target.value)} />
                    {errors.bio ? <p className="au-st-galat">{errors.bio}</p> : null}
                </div>
                <div className="au-form-grid au-form-grid-2">
                    {[
                        ['link_instagram', 'Instagram', 'https://instagram.com/...', 'url'],
                        ['link_github', 'GitHub', 'https://github.com/...', 'url'],
                        ['link_portfolio', 'Portfolio', 'https://...', 'url'],
                        ['link_email', 'Email', 'nama@email.com', 'email'],
                    ].map(([k, l, ph, t]) => (
                        <div key={k}>
                            <label className="au-st-label" htmlFor={k}>
                                {l}
                            </label>
                            <input id={k} type={t} className="au-input" placeholder={ph} value={p[k] || ''} onChange={(e) => set(k, e.target.value)} />
                            {errors[k] ? <p className="au-st-galat">{errors[k]}</p> : null}
                        </div>
                    ))}
                </div>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <button type="submit" className="au-st-ungu">
                        Simpan profil
                    </button>
                </div>
            </form>
            <form
                className="au-st-foto"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (!foto) return;
                    router.post(avatarUrl, { about_avatar: foto }, { preserveScroll: true, forceFormData: true, onSuccess: () => setFoto(null) });
                }}
            >
                <p className="au-st-label">Foto di halaman Tentang</p>
                <div className="au-st-foto-baris">
                    <div className="au-st-foto-kotak">{pratinjau ? <img src={pratinjau} alt="" /> : <Icon path={P.gambar} />}</div>
                    <div className="au-st-baris">
                        <label className="au-st-tombol">
                            Pilih foto
                            <input
                                type="file"
                                accept="image/*"
                                hidden
                                onChange={(e) => {
                                    const f = e.target.files[0];
                                    if (!f) return;
                                    setFoto(f);
                                    setPratinjau(URL.createObjectURL(f));
                                }}
                            />
                        </label>
                        {foto ? (
                            <button type="submit" className="au-st-ungu">
                                Unggah
                            </button>
                        ) : null}
                    </div>
                </div>
                <p className="au-st-bantu">Maksimal 5 MB.</p>
                {errors.about_avatar ? <p className="au-st-galat">{errors.about_avatar}</p> : null}
            </form>
        </section>
    );
}
