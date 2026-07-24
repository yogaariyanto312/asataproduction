<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GambarKerja extends Model
{
    protected $table = 'gambar_kerja';

    protected $fillable = [
        'product_id',
        'judul',
        'seri',
        'kva',
        'kategori_seri',
        'tahun',
        'file_path',
        'file_type',
        'keterangan',
        'uploaded_by',
        'urutan',
        'is_thumbnail',
        'thumbnail_path',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return $this->file_type === 'image';
    }

    public function isPdf(): bool
    {
        return $this->file_type === 'pdf';
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
