import './bootstrap';

// Register service worker untuk PWA installability
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

document.addEventListener('DOMContentLoaded', () => {

    // ==================
    // Toast notifications
    // ==================
    document.querySelectorAll('.toast-auto').forEach(toast => {
        toast.classList.add('toast-enter');
        setTimeout(() => {
            toast.classList.add('toast-leave');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    });

    // ==================
    // Sidebar toggle
    // ==================
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar        = document.getElementById('sidebar');
    const overlay        = document.getElementById('sidebar-overlay');

    if (sidebarToggle && sidebar) {
        // Hanya relevan di mobile (< lg). Di desktop sidebar selalu tampil.
        const isMobile = () => window.matchMedia('(max-width: 1023px)').matches;
        const isOpen   = () => !sidebar.classList.contains('-translate-x-full');

        let overlayTimer = null;
        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            if (overlay) {
                clearTimeout(overlayTimer);
                overlay.classList.remove('hidden');
                // paksa reflow lalu fade-in agar transisi opacity jalan
                void overlay.offsetWidth;
                overlay.classList.remove('opacity-0');
            }
        }
        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            if (overlay) {
                overlay.classList.add('opacity-0');
                clearTimeout(overlayTimer);
                overlayTimer = setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }
        function toggleSidebar() { isOpen() ? closeSidebar() : openSidebar(); }

        sidebarToggle.addEventListener('click', toggleSidebar);
        overlay?.addEventListener('click', closeSidebar);

        // Tutup otomatis saat pindah halaman (SPA) di mobile
        document.addEventListener('spa:leave', () => { if (isMobile()) closeSidebar(); });

        // ── Gesture swipe: kanan = buka, kiri = tutup ──────────────────────────
        let startX = 0, startY = 0, tracking = false, decided = false, horizontal = false;
        const OPEN_THRESHOLD  = 55;  // jarak minimal swipe untuk membuka/menutup
        const DECIDE_SLOP     = 10;  // jarak untuk menentukan arah (horizontal/vertikal)

        // Cek apakah sentuhan dimulai di dalam elemen yang bisa scroll mendatar
        // (mis. tabel), supaya gesture sidebar tidak membajak scroll tabel.
        function startedInHScroll(el) {
            for (let n = el; n && n !== document.body; n = n.parentElement) {
                if (n.scrollWidth > n.clientWidth + 4) {
                    const ox = getComputedStyle(n).overflowX;
                    if (ox === 'auto' || ox === 'scroll') return true;
                }
            }
            return false;
        }

        window.addEventListener('touchstart', (e) => {
            if (!isMobile() || e.touches.length !== 1) { tracking = false; return; }
            const t = e.touches[0];
            if (startedInHScroll(e.target)) { tracking = false; return; }
            startX = t.clientX; startY = t.clientY;
            tracking = true; decided = false; horizontal = false;
        }, { passive: true });

        window.addEventListener('touchmove', (e) => {
            if (!tracking) return;
            const t  = e.touches[0];
            const dx = t.clientX - startX;
            const dy = t.clientY - startY;
            if (!decided && (Math.abs(dx) > DECIDE_SLOP || Math.abs(dy) > DECIDE_SLOP)) {
                decided = true;
                horizontal = Math.abs(dx) > Math.abs(dy); // dominan mendatar?
            }
        }, { passive: true });

        window.addEventListener('touchend', (e) => {
            if (!tracking) return;
            tracking = false;
            if (!decided || !horizontal) return;
            const t  = (e.changedTouches && e.changedTouches[0]) || null;
            if (!t) return;
            const dx = t.clientX - startX;
            if (dx >=  OPEN_THRESHOLD && !isOpen()) openSidebar();
            if (dx <= -OPEN_THRESHOLD &&  isOpen()) closeSidebar();
        }, { passive: true });

        // Rapikan state saat ganti ke desktop (hindari overlay nyangkut)
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                overlay?.classList.add('hidden', 'opacity-0');
                sidebar.classList.remove('-translate-x-full');
            }
        });
    }

    // data-confirm kini ditangani global oleh custom modal di layouts/app.blade.php

    // ==================
    // Real-time search (AJAX — no page reload)
    // ==================
    const searchInput = document.getElementById('search-realtime');
    const listArea    = document.getElementById('prod-list-area');

    if (searchInput && listArea) {
        const filterForm = searchInput.closest('form');
        const pollUrl    = filterForm?.dataset.pollUrl;
        let debounceTimer = null;
        let isFetching    = false;
        let pending       = false; // ada permintaan fetch baru saat fetch sedang berjalan

        function buildUrl() {
            const params = new URLSearchParams();
            new FormData(filterForm).forEach((val, key) => { if (val) params.set(key, val); });
            const qs = params.toString();
            return window.location.pathname + (qs ? '?' + qs : '');
        }

        async function fetchList() {
            // Jangan buang permintaan: kalau masih ada fetch berjalan, tandai
            // "pending" supaya dijalankan ulang setelah yang sekarang selesai.
            if (isFetching) { pending = true; return; }
            isFetching = true;

            const url = buildUrl();

            try {
                const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) {
                    const html = await res.text();
                    const doc  = new DOMParser().parseFromString(html, 'text/html');
                    const next = doc.getElementById('prod-list-area');
                    const cur  = document.getElementById('prod-list-area');
                    // Search input berada di LUAR #prod-list-area, jadi caret &
                    // fokus terjaga otomatis — tak perlu save/restore selection.
                    if (next && cur) cur.innerHTML = next.innerHTML;
                }
            } catch (_) {}

            isFetching = false;

            if (pending) { pending = false; fetchList(); }
        }

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchList, 350);
        });

        filterForm?.querySelectorAll('select').forEach(el => el.addEventListener('change', fetchList));
        filterForm?.querySelectorAll('input[type="date"]').forEach(el => el.addEventListener('change', fetchList));

        // Tombol Reset: batalkan fetch yang pending, kosongkan semua filter,
        // lalu refresh daftar secara AJAX (tanpa reload) agar selalu berfungsi.
        const resetBtn = filterForm?.querySelector('a[href$="/production"], a[data-reset]')
            || filterForm?.parentElement.querySelector('a[href$="/production"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                clearTimeout(debounceTimer);
                pending = false;
                searchInput.value = '';
                filterForm.querySelectorAll('select').forEach(el => { el.value = ''; });
                filterForm.querySelectorAll('input[type="date"]').forEach(el => { el.value = ''; });
                fetchList();
                searchInput.focus();
            });
        }

        // Poll tiap 15 detik — refresh list diam-diam tanpa ganggu search box
        if (pollUrl) {
            let lastTs = null, lastCount = null;
            async function poll() {
                try {
                    const res  = await fetch(pollUrl, { credentials: 'same-origin' });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (lastTs !== null && (data.ts !== lastTs || data.count !== lastCount)) fetchList();
                    lastTs = data.ts; lastCount = data.count;
                } catch (_) {}
            }
            setTimeout(() => { poll(); setInterval(poll, 15000); }, 2000);
        }

        // Refresh instan bila produksi baru disimpan dari tab lain (via AJAX)
        window.addEventListener('storage', (e) => {
            if (e.key === 'prod:changed') fetchList();
        });

    }

    // ── Hapus produksi via AJAX (tanpa reload) ──────────────────────────────
    // Dipasang di level `document` agar tetap aktif setelah navigasi SPA
    // (yang mengganti isi #main-content, sehingga listener pada elemen di
    // dalamnya akan hilang). Handler ini query ulang elemen tiap kali dipakai.
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest && e.target.closest('form[data-ajax="delete"]');
        if (!form) return;
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const row = form.closest('.flex');
        if (btn) btn.disabled = true;

        const tokenMeta = document.querySelector('meta[name=csrf-token]');

        try {
            const res = await fetch(form.action, {
                method: 'POST', // _method=DELETE menangani spoofing Laravel
                headers: {
                    'X-CSRF-TOKEN': tokenMeta ? tokenMeta.content : '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new FormData(form),
                credentials: 'same-origin'
            });

            if (!res.ok) throw new Error('delete failed');

            // Animasi fade-out + collapse pada baris yang dihapus, supaya baris
            // di bawahnya naik dengan halus sebelum daftar dimuat ulang.
            await animateRowRemoval(row);

            // Muat ulang daftar + ringkasan tanpa reload halaman (fade lembut).
            await refreshProdList();
            try { localStorage.setItem('prod:changed', Date.now().toString()); } catch (_) {}
        } catch (_) {
            if (btn) btn.disabled = false;
            if (row) { row.style.opacity = ''; row.style.maxHeight = ''; }
        }
    });

    // Fade-out + collapse tinggi baris. Mengembalikan Promise yang selesai
    // ketika animasi rampung.
    function animateRowRemoval(row) {
        return new Promise((resolve) => {
            if (!row) return resolve();
            const h = row.offsetHeight;
            row.style.overflow   = 'hidden';
            row.style.maxHeight  = h + 'px';
            row.style.transition = 'max-height .18s ease, opacity .14s ease, padding .18s ease, margin .18s ease';
            // paksa reflow agar nilai awal terkunci sebelum transisi
            void row.offsetHeight;
            row.style.opacity       = '0';
            row.style.maxHeight     = '0px';
            row.style.paddingTop    = '0';
            row.style.paddingBottom = '0';
            row.style.marginTop     = '0';
            row.style.marginBottom  = '0';
            setTimeout(resolve, 180);
        });
    }

    // Refresh daftar + ringkasan tanpa reload. Membaca filter/pencarian dari
    // form yang ADA di DOM saat ini (bukan referensi lama), supaya tetap benar
    // meski halaman dibuka via navigasi SPA.
    // Diekspos supaya bisa dipanggil dari luar (mis. modal Input Baru). Tetap
    // tersedia lintas navigasi SPA karena konteks JS tidak dimuat ulang.
    window.refreshProdList = refreshProdList;
    async function refreshProdList() {
        const cur = document.getElementById('prod-list-area');
        if (!cur) return;

        // Bangun URL dari nilai filter yang sedang aktif di halaman.
        let url = window.location.pathname;
        const form = document.querySelector('form[data-poll-url]');
        if (form) {
            const params = new URLSearchParams();
            new FormData(form).forEach((val, key) => { if (val) params.set(key, val); });
            const qs = params.toString();
            if (qs) url += '?' + qs;
        } else {
            url = window.location.href;
        }

        try {
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const html = await res.text();
            const doc  = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.getElementById('prod-list-area');
            // Swap langsung tanpa fade blanking supaya terasa instan (baris yang
            // dihapus sudah dianimasikan keluar sebelumnya).
            if (next) cur.innerHTML = next.innerHTML;
        } catch (_) {}
    }
});
