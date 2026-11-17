<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use Illuminate\Http\Request;

/**
 * Saklar departemen di menu akun (khusus developer):
 *  - lihat : menyempitkan tampilan data ke satu departemen (sesi) atau semua;
 *  - akun  : mengganti departemen akun sendiri (entri baru ikut departemen ini).
 */
class DepartemenAkunController extends Controller
{
    public function update(Request $request)
    {
        $aktif = Department::where('is_active', true)->pluck('name');

        $data = $request->validate([
            'lihat' => ['nullable', 'string', 'max:100'],
            'akun'  => ['nullable', 'string', 'max:100'],
        ]);

        if ($request->has('lihat')) {
            $lihat = $data['lihat'] ?? '';
            if ($lihat !== '' && ! $aktif->contains($lihat)) {
                return back()->withErrors(['lihat' => 'Departemen tidak dikenal.']);
            }
            $lihat === '' ? $request->session()->forget('dept_lihat') : $request->session()->put('dept_lihat', $lihat);
        }

        if ($request->has('akun')) {
            $akun = $data['akun'] ?? '';
            if ($akun !== '' && ! $aktif->contains($akun)) {
                return back()->withErrors(['akun' => 'Departemen tidak dikenal.']);
            }
            $user = $request->user();
            $user->department = $akun === '' ? null : $akun;
            $user->save();
            ActivityLog::record('update', 'Ganti departemen akun sendiri menjadi ' . ($akun === '' ? '(kosong)' : $akun));
        }

        return back();
    }
}
