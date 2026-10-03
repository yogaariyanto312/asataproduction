/**
 * Modal konfirmasi & peringatan global — pengganti window.confirm/alert bawaan
 * browser (tampilannya tidak bisa diatur dan di HP terlihat seperti peringatan
 * sistem). Mengikuti appConfirm/appAlert di Production-QC-Logging-System.
 *
 *   if (await konfirmasi('Hapus catatan ini?', { title: 'Hapus Catatan' })) ...
 *   await peringatan('Berkas terlalu besar.');
 *
 * Enter = setuju, Esc / klik latar = batal.
 */

const IKON = {
    danger: {
        warna: '#ef4444',
        tombol: '#dc2626',
        svg: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    },
    warning: {
        warna: '#f59e0b',
        tombol: '#d97706',
        svg: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    info: {
        warna: '#3b82f6',
        tombol: '#2563eb',
        svg: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
};

let el = null;
let selesai = null;

function pasang() {
    if (el) return el;
    el = document.createElement('div');
    el.className = 'au-dlg';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML =
        '<div class="au-dlg-kotak">' +
        '<div class="au-dlg-kepala"><span class="au-dlg-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d=""/></svg></span>' +
        '<div><p class="au-dlg-judul"></p><p class="au-dlg-pesan"></p></div></div>' +
        '<div class="au-dlg-aksi"><button type="button" class="au-dlg-batal"></button><button type="button" class="au-dlg-ok"></button></div>' +
        '</div>';
    document.body.appendChild(el);

    el.addEventListener('click', (e) => {
        if (e.target === el) tutup(false);
    });
    el.querySelector('.au-dlg-batal').addEventListener('click', () => tutup(false));
    el.querySelector('.au-dlg-ok').addEventListener('click', () => tutup(true));
    document.addEventListener('keydown', (e) => {
        if (!el.classList.contains('is-buka')) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            tutup(false);
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            tutup(true);
        }
    });

    return el;
}

function tutup(hasil) {
    if (!el) return;
    el.classList.remove('is-tampil');
    setTimeout(() => el.classList.remove('is-buka'), 180);
    if (selesai) {
        selesai(hasil);
        selesai = null;
    }
}

function buka({ message, title, type = 'danger', okText, cancelText, alertOnly }) {
    const d = pasang();
    if (selesai) selesai(false); // modal sebelumnya dianggap batal
    const cfg = IKON[type] || IKON.danger;

    d.querySelector('.au-dlg-ikon').style.color = cfg.warna;
    d.querySelector('.au-dlg-ikon').style.background = cfg.warna + '22';
    d.querySelector('.au-dlg-ikon path').setAttribute('d', cfg.svg);
    d.querySelector('.au-dlg-judul').textContent = title || 'Konfirmasi';
    d.querySelector('.au-dlg-pesan').textContent = message || '';
    const ok = d.querySelector('.au-dlg-ok');
    ok.textContent = okText || (alertOnly ? 'OK' : 'Ya, Lanjutkan');
    ok.style.background = cfg.tombol;
    const batal = d.querySelector('.au-dlg-batal');
    batal.style.display = alertOnly ? 'none' : '';
    batal.textContent = cancelText || 'Batal';

    d.classList.add('is-buka');
    requestAnimationFrame(() => requestAnimationFrame(() => d.classList.add('is-tampil')));
    ok.focus();

    return new Promise((res) => {
        selesai = res;
    });
}

/** Minta persetujuan. Resolve true bila disetujui. */
export function konfirmasi(message, opts = {}) {
    return buka({ message, type: 'danger', ...opts });
}

/** Pemberitahuan dengan satu tombol OK. */
export function peringatan(message, opts = {}) {
    return buka({ message, alertOnly: true, type: 'warning', ...opts });
}
