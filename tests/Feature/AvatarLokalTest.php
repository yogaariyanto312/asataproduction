<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/** Avatar DiceBear dilayani dari salinan lokal; selain itu tidak diubah. */
class AvatarLokalTest extends TestCase
{
    private const DASAR = 'https://api.dicebear.com/9.x/adventurer/svg?backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf&seed=';

    public function test_avatar_dicebear_dengan_salinan_lokal_dialihkan(): void
    {
        $u = new User(['avatar' => self::DASAR . 'Felix']);

        $this->assertStringEndsWith('/avatars-dasar/Felix.svg', $u->avatarUrl());
    }

    public function test_seed_tanpa_salinan_dan_url_lain_tetap_apa_adanya(): void
    {
        $this->assertSame(self::DASAR . 'TidakAda', (new User(['avatar' => self::DASAR . 'TidakAda']))->avatarUrl());
        $this->assertSame('https://i.ibb.co.com/x/y.webp', (new User(['avatar' => 'https://i.ibb.co.com/x/y.webp']))->avatarUrl());
        $this->assertNull((new User(['avatar' => null]))->avatarUrl());
    }

    public function test_seluruh_pilihan_avatar_punya_salinan_lokal(): void
    {
        $js = file_get_contents(base_path('resources/js/Pages/Profile/Edit.jsx'));
        preg_match("/const AVATAR_SEED = \[(.*?)\];/s", $js, $blok);
        preg_match_all("/'([^']+)'/", $blok[1], $seed);

        $this->assertCount(80, $seed[1]);
        foreach ($seed[1] as $s) {
            $this->assertFileExists(public_path("avatars-dasar/{$s}.svg"));
        }
    }
}
