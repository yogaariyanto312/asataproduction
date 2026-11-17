<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kategori (nama/jenis) aksesoris, mis. "BOX", "Radiator". Data aksesoris menyimpan namanya di kolom `name`. */
class AccessoryCategory extends Model
{
    protected $fillable = ['name'];

    /** Cari kategori dengan nama sama (tanpa peduli huruf besar/kecil) atau buat baru; mengembalikan nama bakunya. */
    public static function pastikan(string $nama): string
    {
        $nama = trim($nama);
        $ada = static::whereRaw('LOWER(name) = ?', [mb_strtolower($nama)])->first();

        return ($ada ?? static::create(['name' => $nama]))->name;
    }
}
