<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Replacement extends Model
{
    use BelongsToDepartment;

    protected $fillable = [
        'product_id',
        'user_id',
        'department',
        'operator_name',
        'replacement_date',
        'qty',
        'recipient',
        'reason',
        'original_serial',
        'keterangan',
        'completed_at',
    ];

    protected $casts = [
        'replacement_date' => 'date',
        'qty'              => 'integer',
        'completed_at'     => 'datetime',
    ];

    public function getIsCompletedAttribute(): bool
    {
        return ! \is_null($this->completed_at);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSearch(\Illuminate\Database\Eloquent\Builder $query, string $keyword): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($keyword) {
            $q->whereHas('product', function (\Illuminate\Database\Eloquent\Builder $p) use ($keyword) {
                $p->where('name', 'like', "%{$keyword}%")
                  ->orWhere('series', 'like', "%{$keyword}%");
            })
            ->orWhere('recipient', 'like', "%{$keyword}%")
            ->orWhere('reason', 'like', "%{$keyword}%")
            ->orWhere('original_serial', 'like', "%{$keyword}%");
        });
    }
}
