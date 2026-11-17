<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Urutan baku data produksi.
 *
 * Riwayat Produksi sudah lama memakai urutan ini: kategori Channel dulu, lalu
 * Cover, lalu Tangki, dan di dalam tiap kategori diurutkan dari KVA terkecil,
 * seri jadi penentu kalau KVA-nya sama. Laporan dulu memakai urutan lain
 * (terbanyak di atas), jadi orang harus membaca dua cara berbeda untuk data
 * yang sama — dan ekspor Excel/PDF-nya berbeda lagi dari layarnya.
 *
 * Aturannya dikumpulkan di sini supaya layar, PDF, dan Excel tidak bisa
 * berselisih urutan lagi.
 */
class UrutanProduksi
{
    /** Kategori yang punya urutan tetap. Sisanya menyusul di belakang. */
    private const PRIORITAS = ['Channel' => 0, 'Cover' => 1, 'Tangki' => 2];

    /**
     * Nama kategori dipadatkan jadi satu label.
     *
     * Di basis data nama kategorinya beragam ("Channel PLN", "Cover Swasta",
     * "Tangki Type"), padahal yang dipakai mengelompokkan cuma kata kuncinya.
     */
    public static function label(?string $namaKategori): string
    {
        $nama = strtolower((string) $namaKategori);

        foreach (['channel' => 'Channel', 'cover' => 'Cover', 'tangki' => 'Tangki'] as $kunci => $label) {
            if (str_contains($nama, $kunci)) {
                return $label;
            }
        }

        return $namaKategori !== null && $namaKategori !== '' ? $namaKategori : 'Lainnya';
    }

    public static function prioritas(string $label): int
    {
        return self::PRIORITAS[$label] ?? 99;
    }

    /**
     * Urutkan baris: kategori → KVA → seri.
     *
     * @param  callable|null  $produk  cara mengambil produk dari satu baris
     */
    public static function urutkan(Collection $baris, ?callable $produk = null): Collection
    {
        $produk ??= fn ($b) => $b->product ?? null;

        return $baris->sortBy([
            fn ($a, $b) => self::prioritas(self::label($produk($a)?->category?->name))
                       <=> self::prioritas(self::label($produk($b)?->category?->name)),
            fn ($a, $b) => self::label($produk($a)?->category?->name)
                       <=> self::label($produk($b)?->category?->name),
            fn ($a, $b) => (float) ($produk($a)?->kva ?? 0) <=> (float) ($produk($b)?->kva ?? 0),
            fn ($a, $b) => strcmp((string) ($produk($a)?->series ?? ''), (string) ($produk($b)?->series ?? '')),
        ])->values();
    }

    /**
     * Kelompokkan per nama kategori aslinya, bukan label gabungannya.
     *
     * Dipakai ekspor Excel yang memisah tabel per kategori: "Channel-PLN" dan
     * "Channel Swasta" harus jadi dua tabel sendiri, tapi urutan besarnya tetap
     * Channel dulu baru Cover lalu Tangki.
     *
     * @return Collection<string, Collection>
     */
    public static function kelompokkanPerKategori(Collection $baris, ?callable $produk = null): Collection
    {
        $produk ??= fn ($b) => $b->product ?? null;

        $nama = fn ($b) => $produk($b)?->category?->name ?: 'Lainnya';

        return $baris
            ->groupBy($nama)
            ->sortBy(function ($isi, $namaKategori) {
                // Prioritas label dulu ("00|"), lalu abjad nama kategorinya —
                // jadi Channel-PLN & Channel Swasta tetap berdampingan.
                return sprintf('%02d|%s', self::prioritas(self::label($namaKategori)), $namaKategori);
            })
            ->map(fn ($isi) => $isi->sortBy([
                fn ($a, $b) => (float) ($produk($a)?->kva ?? 0) <=> (float) ($produk($b)?->kva ?? 0),
                fn ($a, $b) => strcmp((string) ($produk($a)?->series ?? ''), (string) ($produk($b)?->series ?? '')),
            ])->values());
    }

    /**
     * Kelompokkan per kategori, kelompoknya urut, isinya juga urut.
     *
     * @return Collection<string, Collection>
     */
    public static function kelompokkan(Collection $baris, ?callable $produk = null): Collection
    {
        $produk ??= fn ($b) => $b->product ?? null;

        return $baris
            ->groupBy(fn ($b) => self::label($produk($b)?->category?->name))
            // Kunci berupa teks: "00|Channel". Angka prioritas di depan supaya
            // kategori baku tetap di urutan yang sama, sisanya menyusul abjad.
            ->sortBy(fn ($isi, $label) => sprintf('%02d|%s', self::prioritas($label), $label))
            ->map(fn ($isi) => self::urutkan($isi, $produk));
    }
}
