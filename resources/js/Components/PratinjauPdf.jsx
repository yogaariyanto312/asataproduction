import { useEffect, useRef, useState } from 'react';

/*
 * Pratinjau halaman 1 sebuah PDF untuk kartu berkas (Gambar Kerja). PDF.js dimuat sekali, halaman 1 digambar ke
 * <canvas> kecil hanya saat kartu mendekati layar, dan paling banyak 2 berkas diunduh bersamaan.
 * Berkas diambil utuh lewat fetch() biasa (bukan range request) supaya masuk cache service worker: membuka layar
 * penuh dan kunjungan berikutnya tidak mengunduh ulang (jaringan pabrik ~10 Mbps).
 */
let aktifUnduh = 0;
const menunggu = [];
function giliran() {
    return new Promise((res) => {
        const jalan = () => { aktifUnduh++; res(() => { aktifUnduh--; if (menunggu.length) menunggu.shift()(); }); };
        if (aktifUnduh < 2) jalan(); else menunggu.push(jalan);
    });
}

let muatLib = null;
function muatPdfjs(pdfjs) {
    // pdf.min.js adalah modul ES (memakai import.meta), jadi dimuat lewat import(), sama seperti qc-pdf-viewer.js.
    if (!muatLib) {
        muatLib = import(/* @vite-ignore */ pdfjs.lib).then((m) => {
            const lib = m.getDocument ? m : m.default || window.pdfjsLib;
            lib.GlobalWorkerOptions.workerSrc = pdfjs.worker;
            return lib;
        }).catch((e) => {
            muatLib = null;
            throw e;
        });
    }
    return muatLib;
}

export default function PratinjauPdf({ src, pdfjs, lebar = 280 }) {
    const akar = useRef(null);
    const kanvas = useRef(null);
    const [nampak, setNampak] = useState(false);
    const [status, setStatus] = useState('menunggu'); // menunggu | muat | siap | gagal

    useEffect(() => {
        const el = akar.current;
        if (!el) return undefined;
        if (!('IntersectionObserver' in window)) {
            setNampak(true);
            return undefined;
        }
        const io = new IntersectionObserver(
            (e) => {
                if (e.some((x) => x.isIntersecting)) {
                    setNampak(true);
                    io.disconnect();
                }
            },
            { rootMargin: '200px 0px' },
        );
        io.observe(el);
        return () => io.disconnect();
    }, []);

    useEffect(() => {
        if (!nampak || !pdfjs) return undefined;
        let hidup = true;
        let dok = null;
        setStatus('muat');
        (async () => {
            try {
                const lib = await muatPdfjs(pdfjs);
                const lepas = await giliran();
                let data;
                try {
                    const res = await fetch(src, { credentials: 'same-origin' });
                    if (!res.ok) throw new Error('gagal');
                    data = await res.arrayBuffer();
                } finally {
                    lepas();
                }
                if (!hidup) return;
                dok = await lib.getDocument({ data, cMapUrl: pdfjs.cmaps, cMapPacked: true, standardFontDataUrl: pdfjs.fonts }).promise;
                const hal = await dok.getPage(1);
                if (!hidup) return;
                const dasar = hal.getViewport({ scale: 1 });
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                const skala = (lebar * dpr) / dasar.width;
                const vp = hal.getViewport({ scale: skala });
                const c = kanvas.current;
                c.width = Math.round(vp.width);
                c.height = Math.round(vp.height);
                c.style.aspectRatio = dasar.width + ' / ' + dasar.height;
                await hal.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
                if (hidup) setStatus('siap');
            } catch (_) {
                if (hidup) setStatus('gagal');
            }
        })();
        return () => {
            hidup = false;
            if (dok) dok.destroy();
        };
    }, [nampak, src, pdfjs, lebar]);

    return (
        <div className={'au-pratinjau is-' + status} ref={akar}>
            <canvas ref={kanvas} />
            {status !== 'siap' ? <span className="au-pratinjau-teks">{status === 'gagal' ? 'PDF' : status === 'muat' ? 'Memuat…' : ''}</span> : null}
        </div>
    );
}
