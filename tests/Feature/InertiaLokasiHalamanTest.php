<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Tests\TestCase;

class InertiaLokasiHalamanTest extends TestCase
{
    public function test_lokasi_halaman_mengikuti_kapital_direktori_sebenarnya(): void
    {
        $direktori = array_map(fn ($p) => str_replace('\\', '/', $p), glob(resource_path('js') . '/*', GLOB_ONLYDIR));
        foreach (config('inertia.pages.paths') as $path) {
            $this->assertContains(str_replace('\\', '/', $path), $direktori, 'Kapital path harus cocok di Linux.');
        }
        foreach (['Dashboard', 'Auth/Login', 'Maintenance', 'SystemCheck', 'Management/Index'] as $page) {
            $this->assertFileExists(app('inertia.view-finder')->find($page));
        }
    }

    public function test_komponen_yang_tidak_ada_tetap_ditolak(): void
    {
        $this->assertTrue(config('inertia.testing.ensure_pages_exist'));
        $this->expectException(InvalidArgumentException::class);
        app('inertia.view-finder')->find('KomponenYangTidakAdaUntukUji');
    }
}
