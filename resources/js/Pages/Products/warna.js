/** Palet warna kartu produk (dipakai form & kartu Master Produk). */
export const PALET = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7', '#ec4899', '#14b8a6', '#f97316', '#eab308', '#94a3b8'];

/** Ikon kartu: latar transparan + ikon berwarna; null = warna bawaan. */
export function gayaIkon(warna) {
    return warna ? { background: warna + '26', color: warna, borderColor: warna + '55' } : undefined;
}
