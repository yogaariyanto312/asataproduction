<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengaman: test yang mengosongkan database (RefreshDatabase) hanya boleh
     * jalan di SQLite atau database uji MySQL yang namanya berakhiran
     * "_verify" (phpunit.verify.xml) — tidak pernah di database aplikasi.
     *
     * Harus di sini, bukan di setUp(): parent::setUp() menjalankan trait
     * (termasuk migrate:fresh milik RefreshDatabase) sebelum setUp() kita jalan.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $koneksi = $app['config']->get('database.default');
            $driver  = $app['config']->get("database.connections.{$koneksi}.driver");
            $nama    = (string) $app['config']->get("database.connections.{$koneksi}.database");

            if ($driver !== 'sqlite' && ! str_ends_with($nama, '_verify')) {
                throw new \RuntimeException(sprintf(
                    'DIBATALKAN: %s memakai RefreshDatabase (mengosongkan seluruh database), ' .
                    'tapi database aktifnya "%s" (%s) — bukan sqlite atau *_verify.',
                    static::class, $nama, $driver,
                ));
            }
        }

        return $app;
    }

    /**
     * Beberapa kelas mengingat nilainya di properti statis demi menghemat query
     * (BotSetting, MenuAccess). Properti statis hidup terus dalam satu proses
     * PHP, jadi tanpa dibersihkan test berikutnya membaca sisa test sebelumnya.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\BotSetting::lupakan();
        \App\Support\MenuAccess::flush();
    }
}
