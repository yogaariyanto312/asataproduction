import { useEffect, useRef } from 'react';

/*
 * Realtime ringan (PRD 14). Satu pemeriksa per tab bertanya ke public/rt.php (tanpa Laravel, ~1-3 ms) tiap 3 detik
 * untuk nomor versi topik yang sedang dilangganan. Bila sebuah nomor berubah, fungsi pelanggannya dipanggil dan
 * halaman mengambil data lewat endpoint biasa (yang sudah menghormati izin dan departemen). Balasan 304 bila
 * tidak ada yang berubah. Berhenti saat tab tersembunyi. Setiap pelanggan juga punya cadangan polling lambat
 * (bawaan 60 detik) sebagai jaring pengaman.
 *
 * Topik: produksi, target, catatan, gambar (global) dan chat, notif (per pengguna).
 */
const JEDA = 3000;
const pelanggan = new Map(); // topik -> Set<fungsi>
const versi = {}; // versi terakhir yang dilihat per topik
let etag = '';
let timer = null;
let konfig = null; // { url, token }
let sedangMeminta = false;

export function aturRealtime(rt) {
    konfig = rt && rt.url ? rt : null;
}

async function periksa() {
    if (sedangMeminta || !konfig || document.hidden || !pelanggan.size) return;
    sedangMeminta = true;
    try {
        const p = [...pelanggan.keys()].sort().join(',');
        const res = await fetch(konfig.url + '?t=' + encodeURIComponent(konfig.token) + '&p=' + p, {
            credentials: 'same-origin',
            headers: etag ? { 'If-None-Match': etag } : {},
        });
        if (res.status === 304) return;
        if (res.status === 403) {
            // Token kedaluwarsa: nanti diperbarui oleh navigasi berikutnya; cadangan polling tetap berjalan.
            return;
        }
        if (!res.ok) return;
        etag = res.headers.get('ETag') || '';
        const d = await res.json();
        const berubah = [];
        Object.keys(d).forEach((k) => {
            if (versi[k] !== undefined && versi[k] !== d[k]) berubah.push(k);
            versi[k] = d[k];
        });
        berubah.forEach((k) => (pelanggan.get(k) || []).forEach((f) => f(k)));
    } catch (_) {
        /* jaringan sementara: coba lagi di putaran berikutnya */
    } finally {
        sedangMeminta = false;
    }
}

function nyalakan() {
    if (timer) return;
    timer = setInterval(periksa, JEDA);
    periksa();
}

function matikan() {
    clearInterval(timer);
    timer = null;
}

if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) matikan();
        else if (pelanggan.size) nyalakan();
    });
}

/** Berlangganan perubahan topik. Mengembalikan fungsi berhenti. */
export function langgan(topikList, fn) {
    const daftar = Array.isArray(topikList) ? topikList : [topikList];
    daftar.forEach((t) => {
        if (!pelanggan.has(t)) pelanggan.set(t, new Set());
        pelanggan.get(t).add(fn);
    });
    etag = ''; // daftar topik berubah: minta ulang tanpa ETag lama
    if (!document.hidden) nyalakan();
    return () => {
        daftar.forEach((t) => {
            const s = pelanggan.get(t);
            if (!s) return;
            s.delete(fn);
            if (!s.size) pelanggan.delete(t);
        });
        etag = '';
        if (!pelanggan.size) matikan();
    };
}

/**
 * Panggil fn saat salah satu topik berubah, dan juga tiap cadanganMs (selama tab terlihat) sebagai jaring pengaman.
 * fn disimpan di ref: tidak perlu memoize, dan jadwal tidak mulai ulang tiap render.
 */
export function useRealtime(topikList, fn, cadanganMs = 60000) {
    const ref = useRef(fn);
    ref.current = fn;
    const kunci = (Array.isArray(topikList) ? topikList : [topikList]).join(',');
    useEffect(() => {
        const jalan = () => ref.current();
        const lepas = langgan(kunci.split(','), jalan);
        const t = cadanganMs
            ? setInterval(() => {
                  if (!document.hidden) ref.current();
              }, cadanganMs)
            : null;
        return () => {
            lepas();
            if (t) clearInterval(t);
        };
    }, [kunci, cadanganMs]);
}
