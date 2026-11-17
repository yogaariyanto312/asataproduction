import { useEffect, useRef, useState } from 'react';

/**
 * Penampil PDF gambar kerja — mengikuti x-pdf-viewer di Production-QC-Logging-System.
 *
 * - Desktop: penampil bawaan browser lewat iframe (cari teks, cetak, pilih teks).
 * - Layar sentuh kecil / browser tanpa penampil PDF: halaman digambar sendiri ke
 *   <canvas> lewat PDF.js (public/vendor/pdfjs/qc-pdf-viewer.js) — iframe PDF di
 *   Chrome Android tampil kosong / memaksa unduh, Safari iOS hanya halaman 1.
 * - Dimuat saat mendekati layar (IntersectionObserver), supaya HP tidak menarik
 *   puluhan MB untuk berkas yang belum tentu dilihat.
 *
 * Khas asata: isi PDF diambil sekali dengan fetch() (lewat service worker) lalu
 * ditampilkan sebagai blob — penampil bawaan Chrome mengambil berkas lewat jalur
 * yang melewati service worker, sehingga tanpa ini berkas terunduh ulang terus.
 *
 * Isi elemen akar diurus manual (bukan oleh React) karena viewer PDF.js
 * menulis DOM-nya sendiri.
 */
export default function PdfViewer({ id, src, title = 'PDF', download = null, height = '92vh', pdfjs }) {
    const root = useRef(null);
    const [terlihat, setTerlihat] = useState(false);

    useEffect(() => {
        const el = root.current;
        if (!el) return undefined;
        if (!('IntersectionObserver' in window)) {
            setTerlihat(true);
            return undefined;
        }
        tulisStatus(el, 'Menunggu digulir ke sini…');
        const io = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                setTerlihat(true);
                io.disconnect();
            }
        }, { rootMargin: '300px 0px' });
        io.observe(el);
        return () => io.disconnect();
    }, []);

    useEffect(() => {
        if (!terlihat) return undefined;
        const el = root.current;
        let hidup = true;
        let blobUrl = null;

        tulisStatus(el, 'Menyiapkan preview…');

        (async () => {
            let sumber = src;
            try {
                const res = await fetch(src, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('gagal');
                blobUrl = URL.createObjectURL(await res.blob());
                sumber = blobUrl;
            } catch (_) {
                /* biarkan memakai URL asli sebagai cadangan */
            }
            if (!hidup) return;

            const sentuh = window.matchMedia('(pointer: coarse)').matches;
            const sempit = window.matchMedia('(max-width: 1279px)').matches;
            const pakaiCanvas = navigator.pdfViewerEnabled === false || (sentuh && sempit);

            if (!pakaiCanvas || !pdfjs) {
                const frame = document.createElement('iframe');
                frame.src = sumber + '#view=FitH';
                frame.title = title;
                el.textContent = '';
                el.appendChild(frame);
                return;
            }

            try {
                const m = await import(/* @vite-ignore */ pdfjs.viewer);
                if (!hidup) return;
                el.textContent = '';
                m.mount(el, {
                    src: sumber,
                    download,
                    title,
                    lib: pdfjs.lib,
                    worker: pdfjs.worker,
                    cmaps: pdfjs.cmaps,
                    fonts: pdfjs.fonts,
                });
            } catch (_) {
                // PDF.js gagal dimuat: sediakan jalan keluar yang pasti bisa dipakai.
                el.innerHTML =
                    '<div class="qcpdf-fallback">' +
                    '<p class="qcpdf-fallback-title">Preview PDF tidak bisa dimuat</p>' +
                    '<p class="qcpdf-fallback-msg">Buka berkasnya langsung lewat tombol di bawah.</p>' +
                    '<div class="qcpdf-fallback-actions"></div></div>';
                const aksi = el.querySelector('.qcpdf-fallback-actions');
                const buka = document.createElement('a');
                buka.className = 'qcpdf-cta';
                buka.target = '_blank';
                buka.rel = 'noopener';
                buka.href = src;
                buka.textContent = 'Buka PDF';
                aksi.appendChild(buka);
                if (download) {
                    const dl = document.createElement('a');
                    dl.className = 'qcpdf-cta qcpdf-cta-ghost';
                    dl.href = download;
                    dl.download = '';
                    dl.textContent = 'Unduh';
                    aksi.appendChild(dl);
                }
            }
        })();

        return () => {
            hidup = false;
            if (blobUrl) URL.revokeObjectURL(blobUrl);
        };
    }, [terlihat, src, title, download, pdfjs]);

    return <div id={id} ref={root} className="qcpdf-root" style={{ height }} />;
}

function tulisStatus(el, teks) {
    if (!el) return;
    let s = el.querySelector('.qcpdf-status');
    if (!s && !el.firstChild) {
        s = document.createElement('div');
        s.className = 'qcpdf-status';
        el.appendChild(s);
    }
    if (s) s.textContent = teks;
}
