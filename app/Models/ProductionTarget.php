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
        'baseline_qty',
        'notes',
        'reached_at',
        'created_by',
    ];

    protected $casts = [
        'target_date'  => 'date',
        'target_qty'   => 'integer',
        'baseline_qty' => 'integer',
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

    public function isReached(?int $cumulative = null): bool
    {
        return $this->target_qty > 0 && $this->actualProduced($cumulative) >= $this->target_qty;
    }
}
