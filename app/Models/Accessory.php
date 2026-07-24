<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accessory extends Model
{
    use BelongsToDepartment;

    protected $fillable = [
        'product_id',
        'user_id',
        'department',
        'operator_name',
        'accessory_date',
        'name',
        'serial_number',
        'qty',
        'unit',
        'recipient',
        'purpose',
        'keterangan',
    ];

    protected $casts = [
        'accessory_date' => 'date',
        'qty'            => 'integer',
    ];

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
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('serial_number', 'like', "%{$keyword}%")
              ->orWhere('recipient', 'like', "%{$keyword}%")
              ->orWhere('purpose', 'like', "%{$keyword}%")
              ->orWhereHas('product', function (\Illuminate\Database\Eloquent\Builder $p) use ($keyword) {
                  $p->where('name', 'like', "%{$keyword}%")
                    ->orWhere('series', 'like', "%{$keyword}%");
              });
        });
    }
}
