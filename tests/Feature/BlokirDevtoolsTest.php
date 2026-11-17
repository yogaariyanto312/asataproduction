<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlokirDevtoolsTest extends TestCase
{
    use RefreshDatabase;

    private function prop(): bool
    {
        return (bool) $this->get('/login')->assertOk()->viewData('page')['props']['disableDevtools'];
    }

    public function test_halaman_login_ikut_diblokir_saat_fitur_aktif(): void
    {
        BotSetting::instance()->update(['disable_devtools' => true]);
        BotSetting::lupakan();
        $this->assertTrue($this->prop());

        BotSetting::instance()->update(['disable_devtools' => false]);
        BotSetting::lupakan();
        $this->assertFalse($this->prop());
    }

    public function test_developer_tetap_bebas_setelah_login(): void
    {
        BotSetting::instance()->update(['disable_devtools' => true]);
        BotSetting::lupakan();
        $dev = new User(['name' => 'Dev', 'username' => 'dev', 'email' => 'dev@uji.test', 'role' => 'developer', 'password' => Hash::make('x12345678')]);
        $dev->forceFill(['is_active' => true])->save();

        $props = $this->actingAs($dev)->get('/profile')->assertOk()->viewData('page')['props'];
        $this->assertFalse((bool) $props['disableDevtools']);
    }
}
