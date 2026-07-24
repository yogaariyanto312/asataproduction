<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'type',
        'name',
        'series',
        'kva',
        'tahun',
        'panjang',
        'lebar',
        'unit',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isChannel(): bool
    {
        return $this->type === 'channel';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function gambarKerja(): HasMany
    {
        return $this->hasMany(GambarKerja::class);
    }

    // Total produksi bulan ini
    public function monthlyTotal(): int
    {
        return $this->productionLogs()
            ->whereMonth('production_date', now()->month)
            ->whereYear('production_date', now()->year)
            ->sum('total_qty');
    }

    // Total produksi hari ini
    public function todayTotal(): int
    {
        return $this->productionLogs()
            ->whereDate('production_date', today())
            ->sum('total_qty');
    }

    public function getSeriesWithKvaAttribute(): string
    {
        if (!$this->series) return '';
        return $this->kva ? "{$this->series} · {$this->kva} KVA" : $this->series;
    }

    public function getFullNameAttribute(): string
    {
        $s = $this->series_with_kva;
        return $s ? "{$this->name} - {$s}" : $this->name;
    }
}
