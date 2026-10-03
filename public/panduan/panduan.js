/* Panduan Asata Production.
 *
 * - Garis penunjuk: tiap <li data-ke="N"> di .catatan disambungkan ke elemen
 *   [data-titik="N"] di layar tiruan dalam .peraga yang sama. Garis hanya
 *   digambar kalau keduanya berdampingan (layar lebar); di HP cukup nomornya.
 * - Tema mengikuti aplikasi induk (kelas "dark" pada <html> induk).
 * - Di dalam iframe, tinggi iframe disamakan dengan isi supaya tidak ada
 *   gulir bersarang, dan tautan daftar isi menggulir halaman induk.
 */
(function () {
    'use strict';

    const root = document.documentElement;
    const bingkai = (() => { try { return window.frameElement || null; } catch (_) { return null; } })();
    const SVG = 'http://www.w3.org/2000/svg';

    /* ── Tema ──────────────────────────────────────────────────────────── */
    function pasangTema() {
        let gelap = null;
        try {
            if (bingkai) {
                const induk = window.parent.document.documentElement;
                const baca = () => induk.classList.contains('dark');
                gelap = baca();
                new MutationObserver(() => root.classList.toggle('gelap', baca()))
                    .observe(induk, { attributes: true, attributeFilter: ['class'] });
            }
        } catch (_) { gelap = null; }

        if (gelap === null) {
            const mq = window.matchMedia('(prefers-color-scheme: dark)');
            gelap = mq.matches;
            mq.addEventListener?.('change', (e) => root.classList.toggle('gelap', e.matches));
        }
        root.classList.toggle('gelap', gelap);
    }

    /* ── Penanda bernomor + garis ──────────────────────────────────────── */
    const peraga = [...document.querySelectorAll('.peraga')];

    function siapkanPeraga(p) {
        p.querySelectorAll('[data-titik]').forEach((el) => {
            if (el.querySelector(':scope > .titik')) return;
            const t = document.createElement('span');
            t.className = 'titik';
            t.textContent = el.dataset.titik;
            t.setAttribute('aria-hidden', 'true');
            el.appendChild(t);
        });

        const svg = document.createElementNS(SVG, 'svg');
        svg.classList.add('garis');
        svg.setAttribute('aria-hidden', 'true');
        p.appendChild(svg);

        // Sorot berpasangan: arahkan ke catatan → sasaran di layar ikut menyala, dan sebaliknya.
        const sorot = (n, nyala) => {
            p.querySelectorAll(`[data-ke="${n}"], [data-titik="${n}"]`).forEach((el) => el.classList.toggle('sorot', nyala));
            svg.querySelectorAll(`path[data-ke="${n}"]`).forEach((el) => el.classList.toggle('sorot', nyala));
        };
        p.querySelectorAll('[data-ke], [data-titik]').forEach((el) => {
            const n = el.dataset.ke || el.dataset.titik;
            el.addEventListener('mouseenter', () => sorot(n, true));
            el.addEventListener('mouseleave', () => sorot(n, false));
            el.addEventListener('focus', () => sorot(n, true));
            el.addEventListener('blur', () => sorot(n, false));
        });
        p.querySelectorAll('.catatan li').forEach((li) => {
            li.tabIndex = 0;
            li.addEventListener('click', () => {
                const n = li.dataset.ke;
                sorot(n, true);
                setTimeout(() => sorot(n, false), 1400);
            });
        });
    }

    function gambarGaris(p) {
        const svg = p.querySelector(':scope > svg.garis');
        const catatan = p.querySelector('.catatan');
        const layar = p.querySelector('.layar');
        if (!svg || !catatan || !layar) return;
        svg.replaceChildren();

        const kotak = p.getBoundingClientRect();
        const rL = layar.getBoundingClientRect();
        const rC = catatan.getBoundingClientRect();

        // Hanya kalau catatan berada DI SAMPING layar. Tersusun atas-bawah = HP.
        if (rC.left < rL.right - 4 || getComputedStyle(svg).display === 'none') return;

        catatan.querySelectorAll('li[data-ke]').forEach((li) => {
            const n = li.dataset.ke;
            const sasaran = layar.querySelector(`[data-titik="${n}"] > .titik`);
            if (!sasaran || li.offsetParent === null) return;

            const a = li.getBoundingClientRect();
            const b = sasaran.getBoundingClientRect();
            const x1 = a.left - kotak.left + 1;
            const y1 = a.top - kotak.top + 21;
            const x2 = b.left - kotak.left + b.width / 2;
            const y2 = b.top - kotak.top + b.height / 2;
            const tekuk = Math.max(24, (x1 - x2) * 0.45);

            const path = document.createElementNS(SVG, 'path');
            path.setAttribute('d', `M${x1},${y1} C${x1 - tekuk},${y1} ${x2 + tekuk},${y2} ${x2},${y2}`);
            path.dataset.ke = n;
            svg.appendChild(path);

            const ujung = document.createElementNS(SVG, 'circle');
            ujung.setAttribute('cx', x1); ujung.setAttribute('cy', y1); ujung.setAttribute('r', 3);
            svg.appendChild(ujung);
        });
    }

    let antre = 0;
    function gambarSemua() {
        cancelAnimationFrame(antre);
        antre = requestAnimationFrame(() => {
            peraga.forEach(gambarGaris);
            sesuaikanTinggi();
        });
    }

    /* ── Tinggi iframe mengikuti isi ───────────────────────────────────── */
    function sesuaikanTinggi() {
        if (!bingkai) return;
        // Tinggi <body>, bukan documentElement.scrollHeight: yang terakhir tidak
        // pernah lebih kecil dari tinggi iframe sendiri, jadi iframe tidak bisa
        // mengecil lagi setelah isinya dipendekkan (mis. lewat pencarian).
        const tinggi = Math.ceil(document.body.getBoundingClientRect().height);
        if (Math.abs((parseInt(bingkai.style.height, 10) || 0) - tinggi) > 1) {
            bingkai.style.height = tinggi + 'px';
        }
    }

    /* ── Daftar isi & gulir ────────────────────────────────────────────── */
    function gulirKe(el) {
        if (!el) return;
        if (bingkai) {
            // Halaman panduan sendiri tidak menggulir di dalam iframe — yang
            // digulir halaman aplikasi di luarnya.
            const atas = bingkai.getBoundingClientRect().top + el.getBoundingClientRect().top;
            window.parent.scrollBy({ top: atas - 80, behavior: 'smooth' });
        } else {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function pasangDaftar() {
        document.querySelectorAll('.daftar a[href^="#"]').forEach((a) => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                gulirKe(document.querySelector(a.getAttribute('href')));
            });
        });

        // Penanda bab yang sedang dibaca (berlaku saat dibuka sendiri, tidak di iframe).
        if (!bingkai && 'IntersectionObserver' in window) {
            const tautan = new Map([...document.querySelectorAll('.daftar a')].map((a) => [a.getAttribute('href').slice(1), a]));
            const io = new IntersectionObserver((isi) => {
                isi.forEach((x) => {
                    if (x.isIntersecting) {
                        tautan.forEach((a) => a.classList.remove('aktif'));
                        tautan.get(x.target.id)?.classList.add('aktif');
                    }
                });
            }, { rootMargin: '-30% 0px -60% 0px' });
            document.querySelectorAll('.bab[id]').forEach((b) => io.observe(b));
        }
    }

    /* ── Cari topik ────────────────────────────────────────────────────── */
    function pasangCari() {
        const input = document.getElementById('cari-topik');
        const kosong = document.getElementById('kosong-cari');
        if (!input) return;
        const bab = [...document.querySelectorAll('.bab')];
        const tautan = [...document.querySelectorAll('.daftar a')];
        const normal = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        const teks = bab.map((b) => normal(b.textContent));

        input.addEventListener('input', () => {
            const kata = normal(input.value.trim()).split(/\s+/).filter(Boolean);
            let ada = 0;
            bab.forEach((b, i) => {
                const cocok = kata.every((k) => teks[i].includes(k));
                b.hidden = !cocok;
                if (cocok) ada++;
            });
            tautan.forEach((a) => {
                const b = document.querySelector(a.getAttribute('href'));
                a.style.display = b && b.hidden ? 'none' : '';
            });
            kosong.style.display = ada ? 'none' : 'block';
            gambarSemua();
        });
    }

    /* ── Mulai ─────────────────────────────────────────────────────────── */
    if (bingkai) root.classList.add('dalam-bingkai');
    pasangTema();
    peraga.forEach(siapkanPeraga);
    pasangDaftar();
    pasangCari();

    gambarSemua();
    window.addEventListener('resize', gambarSemua);
    window.addEventListener('load', gambarSemua);
    if ('ResizeObserver' in window) new ResizeObserver(gambarSemua).observe(document.body);
    document.fonts?.ready?.then(gambarSemua);

    // Dipakai test: pintu masuk untuk menggambar ulang secara sinkron.
    window.__panduan = { gambarGaris, peraga };
})();
