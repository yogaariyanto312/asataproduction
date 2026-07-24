<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    use HasFactory, BelongsToDepartment;

    protected $fillable = [
        'product_id',
        'user_id',
        'department',
        'operator_name',
        'production_date',
        'shift1_qty',
        'shift2_qty',
        'shift3_qty',
        'total_qty',
        'notes',
        'manual_series',
        'manual_kva',
        'keterangan',
        'status',
        'reject_qty',
        'reject_category',
        'reject_notes',
    ];

    protected $casts = [
        'production_date' => 'date',
        'shift1_qty'      => 'integer',
        'shift2_qty'      => 'integer',
        'shift3_qty'      => 'integer',
        'total_qty'       => 'float',
        'reject_qty'      => 'integer',
    ];

    public static array $rejectCategories = [
        'material'    => 'Bahan Baku',
        'mesin'       => 'Mesin / Alat',
        'human_error' => 'Human Error',
        'desain'      => 'Desain / Spesifikasi',
        'lainnya'     => 'Lainnya',
    ];

    public function rejectCategoryLabel(): string
    {
        return self::$rejectCategories[$this->reject_category] ?? '-';
    }

    public function rejectRate(): float
    {
        $total = $this->total_qty + $this->reject_qty;
        return $total > 0 ? round(($this->reject_qty / $total) * 100, 1) : 0;
    }

    // Relasi ke produk
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Relasi ke operator yang menginput
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByDate(\Illuminate\Database\Eloquent\Builder $query, string $date): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereDate('production_date', $date);
    }

    public function scopeByMonth(\Illuminate\Database\Eloquent\Builder $query, int $month, int $year): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereMonth('production_date', $month)
                     ->whereYear('production_date', $year);
    }

    public function scopeByProduct(\Illuminate\Database\Eloquent\Builder $query, int $productId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, string $keyword): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('product', function (\Illuminate\Database\Eloquent\Builder $q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('series', 'like', "%{$keyword}%");
        })->orWhere('notes', 'like', "%{$keyword}%");
    }
}
