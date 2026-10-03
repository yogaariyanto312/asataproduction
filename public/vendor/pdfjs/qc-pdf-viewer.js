/*
 * Viewer PDF untuk menu Gambar Kerja.
 *
 * Kenapa ada: <iframe src="file.pdf"> tidak bisa diandalkan di HP. Chrome
 * Android menampilkan kotak kosong / memaksa unduh, dan Safari iOS hanya
 * menggambar halaman pertama tanpa bisa digulir. Di desktop iframe tetap
 * dipakai (viewer bawaan browser lebih lengkap); di HP halaman digambar
 * sendiri ke <canvas> lewat PDF.js.
 *
 * Cubit-untuk-zoom ditangani sendiri di sini. Kalau diserahkan ke browser,
 * yang membesar adalah seluruh halaman web (toolbar, header, semuanya) —
 * bukan gambarnya saja. Karena itu area baca diberi touch-action: pan-x pan-y
 * supaya browser hanya mengurus geser, lalu cubitan dihitung manual.
 *
 * Semua berkas PDF.js di-host lokal (public/vendor/pdfjs) supaya tetap jalan
 * di jaringan pabrik yang tidak punya akses internet.
 */

const MAX_DPR = 2;          // batas ketajaman render — di atas ini boros memori HP
const MIN_SCALE = 0.25;
const MAX_SCALE = 6;

/* Halaman yang jauh dari layar kanvasnya dikosongkan lagi.
 *
 * Satu halaman A3 pada perbesaran pas-lebar dengan dpr 2 memakan belasan MB di
 * memori. Gambar kerja belasan halaman berarti ratusan MB kalau semua kanvas
 * disimpan — HP kelas menengah bisa berhenti atau menutup tab sendiri. Kanvas
 * yang dilepas akan digambar ulang saat halamannya didekati lagi.
 *
 * Batas simpan dibuat lebih longgar daripada batas render supaya menggulir
 * bolak-balik sedikit tidak memicu gambar-ulang terus-menerus. */
const LAYAR_RENDER = 1;   // render sejauh 1 layar di atas & 2 layar di bawah
const LAYAR_SIMPAN = 3;   // di luar 3 layar, kanvas dibebaskan

let pdfLibPromise = null;

function loadPdfLib(opts) {
    if (!pdfLibPromise) {
        pdfLibPromise = import(opts.lib).then((lib) => {
            lib.GlobalWorkerOptions.workerSrc = opts.worker;
            return lib;
        });
    }
    return pdfLibPromise;
}

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
}

function iconButton(label, svgPath, onClick) {
    const btn = el('button', 'qcpdf-btn');
    btn.type = 'button';
    btn.title = label;
    btn.setAttribute('aria-label', label);
    btn.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
        'stroke-linecap="round" stroke-linejoin="round"><path d="' + svgPath + '"/></svg>';
    btn.addEventListener('click', onClick);
    return btn;
}

function clamp(v, lo, hi) {
    return Math.min(hi, Math.max(lo, v));
}

/** Jarak dan titik tengah antara dua jari. */
function touchSpan(a, b) {
    const dx = a.clientX - b.clientX;
    const dy = a.clientY - b.clientY;
    return {
        dist: Math.hypot(dx, dy) || 1,
        x: (a.clientX + b.clientX) / 2,
        y: (a.clientY + b.clientY) / 2,
    };
}

/**
 * Pasang viewer ke dalam sebuah elemen.
 *
 * @param {HTMLElement} root  wadah viewer (sudah punya tinggi dari CSS)
 * @param {{src:string, lib:string, worker:string, cmaps:string, fonts:string,
 *          title?:string, download?:string}} opts
 */
export function mount(root, opts) {
    if (root.dataset.qcpdfMounted === '1') return;
    root.dataset.qcpdfMounted = '1';

    const toolbar  = el('div', 'qcpdf-toolbar');
    const scroller = el('div', 'qcpdf-pages');
    const area     = el('div', 'qcpdf-area');
    /* Panel kemajuan unduhan.
       Berkas gambar kerja belasan MB, dan di jaringan pabrik unduhannya bisa
       lama. Tulisan "Memuat dokumen…" saja membuat layar terlihat menggantung,
       jadi ditampilkan persen & ukuran seperti pada layar unggah. */
    const status   = el('div', 'qcpdf-status');
    const statusJudul = el('p', 'qcpdf-status-judul', 'Memuat gambar…');
    const statusBarLuar = el('div', 'qcpdf-status-bar');
    const statusBar = el('div', 'qcpdf-status-isi');
    const statusInfo = el('p', 'qcpdf-status-info', 'Menyiapkan…');
    const statusPersen = el('span', 'qcpdf-status-persen', '');

    statusJudul.appendChild(statusPersen);
    statusBarLuar.appendChild(statusBar);
    status.appendChild(statusJudul);
    status.appendChild(statusBarLuar);
    status.appendChild(statusInfo);

    const mbTeks = (b) => (b / 1048576).toFixed(1) + ' MB';
    let mulaiUnduh = 0;

    function setKemajuan(loaded, total) {
        const detik = mulaiUnduh ? (Date.now() - mulaiUnduh) / 1000 : 0;
        const laju  = detik > 0 ? loaded / detik : 0;

        if (!total) {
            // Ukuran tidak diberitahu server: tampilkan yang sudah terunduh saja,
            // dengan bar berdenyut supaya jelas prosesnya berjalan.
            statusBar.style.width = '100%';
            statusBar.classList.add('is-berdenyut');
            statusPersen.textContent = '';
            statusInfo.textContent = 'Terunduh ' + mbTeks(loaded) +
                (laju > 0 ? ' · ' + mbTeks(laju) + '/dtk' : '');
            return;
        }

        const persen = Math.max(0, Math.min(100, (loaded / total) * 100));
        statusBar.classList.remove('is-berdenyut');
        statusBar.style.width = persen.toFixed(1) + '%';
        statusPersen.textContent = Math.round(persen) + '%';

        let teks = mbTeks(loaded) + ' / ' + mbTeks(total);
        if (laju > 0) teks += ' · ' + mbTeks(laju) + '/dtk';

        const sisa = laju > 0 ? Math.round((total - loaded) / laju) : null;
        if (sisa !== null && sisa > 0 && loaded < total) {
            teks += ' · sisa ' + (sisa >= 60 ? Math.ceil(sisa / 60) + ' mnt' : sisa + ' dtk');
        }
        statusInfo.textContent = teks;
    }

    const pageLabel = el('span', 'qcpdf-pagelabel', '–/–');
    const zoomLabel = el('span', 'qcpdf-zoomlabel', '100%');

    root.textContent = '';
    root.appendChild(toolbar);
    root.appendChild(scroller);
    scroller.appendChild(area);
    area.appendChild(status);

    const state = {
        doc: null,
        loadingTask: null,
        scale: 1,        // skala efektif yang dipakai render
        fitScale: 1,     // skala saat halaman pas selebar layar
        fitWidth: true,  // selama true, skala mengikuti lebar layar
        pages: [],       // { number, wrap, canvas, ratio, rendered, task }
    };

    /* ---------- toolbar ---------- */

    const left = el('div', 'qcpdf-group');
    left.appendChild(iconButton('Perkecil', 'M5 12h14', () => zoomBy(1 / 1.3)));
    left.appendChild(zoomLabel);
    left.appendChild(iconButton('Perbesar', 'M12 5v14M5 12h14', () => zoomBy(1.3)));

    const fitBtn = el('button', 'qcpdf-btn qcpdf-btn-text', 'Pas Lebar');
    fitBtn.type = 'button';
    fitBtn.addEventListener('click', () => {
        state.fitWidth = true;
        relayout({ scrollTo: { left: 0, top: scroller.scrollTop } });
    });
    left.appendChild(fitBtn);

    const right = el('div', 'qcpdf-group');
    right.appendChild(pageLabel);

    if (document.fullscreenEnabled) {
        right.appendChild(iconButton(
            'Layar penuh',
            'M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4',
            () => {
                if (document.fullscreenElement) document.exitFullscreen();
                else root.requestFullscreen();
            }
        ));
    }

    const openLink = el('a', 'qcpdf-btn', null);
    openLink.href = opts.src;
    openLink.target = '_blank';
    openLink.rel = 'noopener';
    openLink.title = 'Buka di tab baru';
    openLink.setAttribute('aria-label', 'Buka di tab baru');
    openLink.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
        'stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/></svg>';
    right.appendChild(openLink);

    toolbar.appendChild(left);
    toolbar.appendChild(right);

    /* ---------- ukuran & tata letak ---------- */

    function baseWidth() {
        // Lebar area baca dikurangi padding kiri-kanan .qcpdf-area.
        return Math.max(240, scroller.clientWidth - 16);
    }

    function updateZoomLabel() {
        zoomLabel.textContent = Math.round((state.scale / state.fitScale) * 100) + '%';
        fitBtn.classList.toggle('is-active', state.fitWidth);
    }

    let relayoutToken = 0;

    /**
     * Hitung ulang ukuran semua halaman lalu gambar yang terlihat.
     *
     * @param {{scrollTo?: {left:number, top:number}}} [o]
     */
    async function relayout(o) {
        if (!state.doc) return;

        const token = ++relayoutToken;
        const first = await state.doc.getPage(1);
        if (token !== relayoutToken) return;

        state.fitScale = baseWidth() / first.getViewport({ scale: 1 }).width;
        if (state.fitWidth) state.scale = state.fitScale;
        state.scale = clamp(state.scale, state.fitScale * MIN_SCALE, state.fitScale * MAX_SCALE);
        updateZoomLabel();

        for (const p of state.pages) {
            p.rendered = false;
            if (p.task) { p.task.cancel(); p.task = null; }
            p.canvas.width = 0;
            p.canvas.height = 0;
            p.wrap.classList.remove('is-ready');

            // Ukuran ditetapkan lebih dulu dari ukuran asli tiap halaman supaya
            // posisi gulir tidak melompat saat halaman digambar satu per satu.
            // Dihitung per halaman: satu dokumen bisa memuat halaman dengan
            // ukuran kertas berbeda (mis. A4 diselingi A3 melintang).
            const w = Math.round(p.baseWidth * state.scale);
            p.wrap.style.width  = w + 'px';
            p.wrap.style.height = Math.round(w * p.ratio) + 'px';
        }

        if (o && o.scrollTo) {
            scroller.scrollLeft = Math.max(0, o.scrollTo.left);
            scroller.scrollTop  = Math.max(0, o.scrollTo.top);
        }

        renderVisible();
    }

    async function renderPage(entry) {
        if (entry.rendered || entry.task) return;

        const page = await state.doc.getPage(entry.number);
        const viewport = page.getViewport({ scale: state.scale });
        const dpr = Math.min(MAX_DPR, window.devicePixelRatio || 1);

        const canvas = entry.canvas;
        canvas.width  = Math.floor(viewport.width  * dpr);
        canvas.height = Math.floor(viewport.height * dpr);
        canvas.style.width  = Math.floor(viewport.width) + 'px';
        canvas.style.height = Math.floor(viewport.height) + 'px';

        entry.wrap.style.width  = Math.floor(viewport.width) + 'px';
        entry.wrap.style.height = Math.floor(viewport.height) + 'px';

        // DPR diterapkan lewat parameter transform (cara yang didukung PDF.js v6);
        // menyetel transform sendiri di context akan ditimpa saat render.
        entry.task = page.render({
            canvas,
            viewport,
            transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null,
            background: '#ffffff',
        });

        try {
            await entry.task.promise;
            entry.rendered = true;
            entry.wrap.classList.add('is-ready');
        } catch (e) {
            if (e && e.name !== 'RenderingCancelledException') throw e;
        } finally {
            entry.task = null;
        }
    }

    /** Kosongkan kanvas sebuah halaman, tapi jangan ganggu yang sedang digambar. */
    function lepasKanvas(p) {
        if (!p.rendered || p.task) return;

        // Ukuran wrap dipertahankan supaya tinggi gulir tidak melompat.
        p.canvas.width = 0;
        p.canvas.height = 0;
        p.rendered = false;
        p.wrap.classList.remove('is-ready');
    }

    function renderVisible() {
        const layar = scroller.clientHeight;
        const top = scroller.scrollTop - layar * LAYAR_RENDER;
        const bottom = scroller.scrollTop + layar * (LAYAR_RENDER + 1);

        const simpanAtas = scroller.scrollTop - layar * LAYAR_SIMPAN;
        const simpanBawah = scroller.scrollTop + layar * (LAYAR_SIMPAN + 1);

        state.pages.forEach((p) => {
            const y = p.wrap.offsetTop;
            const akhir = y + p.wrap.offsetHeight;

            if (akhir >= top && y <= bottom) {
                renderPage(p).catch(() => {});
            } else if (akhir < simpanAtas || y > simpanBawah) {
                lepasKanvas(p);
            }
        });

        const mid = scroller.scrollTop + scroller.clientHeight / 2;
        let current = 1;
        for (const p of state.pages) {
            if (p.wrap.offsetTop <= mid) current = p.number;
        }
        pageLabel.textContent = current + '/' + state.pages.length;
    }

    /* ---------- zoom ---------- */

    /**
     * Ubah perbesaran dengan titik jangkar tetap di tempatnya.
     *
     * @param {number} factor  pengali skala
     * @param {number} [ax]    titik jangkar (koordinat layar); default tengah area
     * @param {number} [ay]
     */
    function zoomBy(factor, ax, ay) {
        const box = scroller.getBoundingClientRect();
        const cx = (ax == null ? box.width  / 2 : ax - box.left);
        const cy = (ay == null ? box.height / 2 : ay - box.top);

        const before = state.scale;
        const next = clamp(before * factor, state.fitScale * MIN_SCALE, state.fitScale * MAX_SCALE);
        const k = next / before;
        if (k === 1) return;

        state.fitWidth = false;
        state.scale = next;

        // Titik dokumen yang berada di bawah jangkar harus tetap di sana
        // setelah skala berubah.
        relayout({
            scrollTo: {
                left: (scroller.scrollLeft + cx) * k - cx,
                top:  (scroller.scrollTop  + cy) * k - cy,
            },
        });
    }

    /* Cubit dua jari: selama jari masih menempel, area digambar-ulang secara
       murah pakai CSS transform (instan, tidak membebani HP). Begitu jari
       diangkat, skala baru dikunci dan halaman dirender ulang agar tajam. */
    const pinch = { active: false, startDist: 0, startLeft: 0, startTop: 0, cx: 0, cy: 0, k: 1 };

    scroller.addEventListener('touchstart', (e) => {
        if (e.touches.length !== 2) return;

        const span = touchSpan(e.touches[0], e.touches[1]);
        const box = scroller.getBoundingClientRect();

        pinch.active = true;
        pinch.startDist = span.dist;
        pinch.startLeft = scroller.scrollLeft;
        pinch.startTop = scroller.scrollTop;
        pinch.cx = span.x - box.left;
        pinch.cy = span.y - box.top;
        pinch.k = 1;

        area.style.transformOrigin = '0 0';
        area.classList.add('is-pinching');
    }, { passive: true });

    scroller.addEventListener('touchmove', (e) => {
        if (!pinch.active || e.touches.length !== 2) return;
        e.preventDefault();   // cegah browser ikut men-zoom seluruh halaman

        const span = touchSpan(e.touches[0], e.touches[1]);
        const limitLo = (state.fitScale * MIN_SCALE) / state.scale;
        const limitHi = (state.fitScale * MAX_SCALE) / state.scale;

        pinch.k = clamp(span.dist / pinch.startDist, limitLo, limitHi);

        area.style.transform = 'scale(' + pinch.k + ')';
        scroller.scrollLeft = Math.max(0, (pinch.startLeft + pinch.cx) * pinch.k - pinch.cx);
        scroller.scrollTop  = Math.max(0, (pinch.startTop  + pinch.cy) * pinch.k - pinch.cy);
    }, { passive: false });

    function endPinch() {
        if (!pinch.active) return;
        pinch.active = false;

        area.classList.remove('is-pinching');
        area.style.transform = '';

        if (Math.abs(pinch.k - 1) < 0.01) return;

        state.fitWidth = false;
        state.scale = clamp(
            state.scale * pinch.k,
            state.fitScale * MIN_SCALE,
            state.fitScale * MAX_SCALE
        );

        relayout({
            scrollTo: {
                left: (pinch.startLeft + pinch.cx) * pinch.k - pinch.cx,
                top:  (pinch.startTop  + pinch.cy) * pinch.k - pinch.cy,
            },
        });
    }

    scroller.addEventListener('touchend', endPinch, { passive: true });
    scroller.addEventListener('touchcancel', endPinch, { passive: true });

    /* Safari iOS tetap mencoba men-zoom halaman lewat gesture event tersendiri,
       terlepas dari touch-action. Ditolak di sini. */
    ['gesturestart', 'gesturechange', 'gestureend'].forEach((name) => {
        scroller.addEventListener(name, (e) => e.preventDefault());
    });

    /* Ketuk dua kali: bolak-balik antara pas lebar dan perbesaran 2,5x, dengan
       titik ketukan sebagai jangkar. */
    let lastTap = 0;
    scroller.addEventListener('touchend', (e) => {
        if (pinch.active || e.touches.length > 0 || !e.changedTouches.length) return;

        const now = Date.now();
        const t = e.changedTouches[0];

        if (now - lastTap < 300) {
            lastTap = 0;
            if (state.scale > state.fitScale * 1.05) {
                state.fitWidth = true;
                relayout({ scrollTo: { left: 0, top: scroller.scrollTop } });
            } else {
                zoomBy(2.5, t.clientX, t.clientY);
            }
        } else {
            lastTap = now;
        }
    }, { passive: true });

    /* Zoom pakai roda mouse + Ctrl (trackpad cubit di laptop layar sentuh). */
    scroller.addEventListener('wheel', (e) => {
        if (!e.ctrlKey) return;
        e.preventDefault();
        zoomBy(e.deltaY < 0 ? 1.12 : 1 / 1.12, e.clientX, e.clientY);
    }, { passive: false });

    /* ---------- muat dokumen ---------- */

    function fail(message) {
        root.dataset.qcpdfMounted = '';
        root.innerHTML = '';

        const box = el('div', 'qcpdf-fallback');
        box.appendChild(el('p', 'qcpdf-fallback-title', 'Preview PDF tidak bisa ditampilkan'));
        box.appendChild(el('p', 'qcpdf-fallback-msg', message));

        const actions = el('div', 'qcpdf-fallback-actions');
        const open = el('a', 'qcpdf-cta', 'Buka PDF');
        open.href = opts.src;
        open.target = '_blank';
        open.rel = 'noopener';
        actions.appendChild(open);

        if (opts.download) {
            const dl = el('a', 'qcpdf-cta qcpdf-cta-ghost', 'Unduh');
            dl.href = opts.download;
            dl.setAttribute('download', '');
            actions.appendChild(dl);
        }

        box.appendChild(actions);
        root.appendChild(box);
    }

    loadPdfLib(opts)
        .then((lib) => {
            // Loading task disimpan: destroy() ada di sana, bukan di dokumennya.
            mulaiUnduh = Date.now();

            state.loadingTask = lib.getDocument({
                url: opts.src,
                withCredentials: true,    // file dilayani route ber-middleware auth
                cMapUrl: opts.cmaps,
                cMapPacked: true,
                standardFontDataUrl: opts.fonts,
            });

            // PDF.js melaporkan kemajuan unduhan lewat callback ini.
            state.loadingTask.onProgress = function (kabar) {
                setKemajuan(kabar.loaded || 0, kabar.total || 0);
            };

            return state.loadingTask.promise;
        })
        .then(async (doc) => {
            state.doc = doc;

            // Unduhan selesai; sisa waktu dipakai menyiapkan halaman.
            setKemajuan(1, 1);
            statusJudul.firstChild.textContent = 'Menyiapkan halaman…';
            statusInfo.textContent = '';

            status.remove();

            for (let n = 1; n <= doc.numPages; n++) {
                const page = await doc.getPage(n);
                const v = page.getViewport({ scale: 1 });

                const wrap = el('div', 'qcpdf-page');
                const canvas = el('canvas');
                wrap.appendChild(canvas);
                area.appendChild(wrap);

                state.pages.push({
                    number: n, wrap, canvas,
                    baseWidth: v.width,
                    ratio: v.height / v.width,
                    rendered: false, task: null,
                });
            }

            pageLabel.textContent = '1/' + doc.numPages;
            await relayout();
        })
        .catch((e) => {
            fail(e && e.name === 'PasswordException'
                ? 'Dokumen ini terkunci dengan kata sandi.'
                : 'Coba buka langsung lewat tombol di bawah.');
        });

    /* ---------- event lain ---------- */

    let scrollTimer = null;
    scroller.addEventListener('scroll', () => {
        if (pinch.active || scrollTimer) return;
        scrollTimer = setTimeout(() => { scrollTimer = null; renderVisible(); }, 90);
    }, { passive: true });

    let resizeTimer = null;
    const onResize = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (state.fitWidth) relayout(); }, 150);
    };
    window.addEventListener('resize', onResize);
    window.addEventListener('orientationchange', onResize);

    // Halaman di-swap oleh navigasi SPA: hentikan render agar tidak bocor.
    document.addEventListener('spa:leave', function cleanup() {
        if (document.body.contains(root)) return;
        window.removeEventListener('resize', onResize);
        window.removeEventListener('orientationchange', onResize);
        document.removeEventListener('spa:leave', cleanup);
        state.pages.forEach((p) => p.task && p.task.cancel());
        if (state.loadingTask) state.loadingTask.destroy();
    });
}
