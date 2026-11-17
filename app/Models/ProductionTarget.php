<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ProductionLog dipakai di actualProduced()

class ProductionTarget extends Model
{
    protected $fillable = [
        'product_id',
        'target_date',
        'target_qty',
        'target_up',
        'target_bt',
        'baseline_qty',
        'baseline_up',
        'baseline_bt',
        'notes',
        'reached_at',
        'created_by',
    ];

    protected $casts = [
        'target_date'  => 'date',
        'target_qty'   => 'integer',
        'baseline_qty' => 'integer',
        'target_up'    => 'integer',
        'target_bt'    => 'integer',
        'baseline_up'  => 'integer',
        'baseline_bt'  => 'integer',
        'reached_at'   => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Progres produksi sejak target dibuat = produksi kumulatif sekarang − baseline.
     * Lewatkan $cumulative (total_qty kumulatif produk) untuk menghindari query N+1.
     */
    public function actualProduced(?int $cumulative = null): int
    {
        // Target org-wide → aktual dihitung lintas departemen.
        $cumulative ??= (int) ProductionLog::withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
            ->where('product_id', $this->product_id)->sum('total_qty');
        return max(0, $cumulative - (int) $this->baseline_qty);
    }

    /** Target channel yang dipisah per deret (UP dan BT). */
    public function isSplit(): bool
    {
        return $this->target_up !== null || $this->target_bt !== null;
    }

    /**
     * Progres per deret untuk target terpisah, atau null untuk target biasa.
     * Tiap deret: target, aktual (kumulatif - baseline), persen (maks 100), done.
     */
    public function splitProgress(int $kumulatifUp, int $kumulatifBt): ?array
    {
        if (! $this->isSplit()) {
            return null;
        }

        $hitung = function (?int $target, ?int $baseline, int $kumulatif): array {
            $target = (int) $target;
            $aktual = max(0, $kumulatif - (int) $baseline);

            return [
                'target' => $target,
                'actual' => $aktual,
                'pct'    => $target > 0 ? min((int) round($aktual / $target * 100), 100) : 100,
                'done'   => $aktual >= $target,
            ];
        };

        $up = $hitung($this->target_up, $this->baseline_up, $kumulatifUp);
        $bt = $hitung($this->target_bt, $this->baseline_bt, $kumulatifBt);
        $jumlah = $up['target'] + $bt['target'];

        return [
            'up'   => $up,
            'bt'   => $bt,
            'pct'  => $jumlah > 0 ? min((int) round((min($up['actual'], $up['target']) + min($bt['actual'], $bt['target'])) / $jumlah * 100), 100) : 100,
            'done' => $up['done'] && $bt['done'],
        ];
    }

    public function isReached(?int $cumulative = null): bool
    {
        return $this->target_qty > 0 && $this->actualProduced($cumulative) >= $this->target_qty;
    }
}
