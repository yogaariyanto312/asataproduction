<?php

namespace Tests\Feature;

use Tests\TestCase;

class CsrfTokenRouteTest extends TestCase
{
    public function test_mengembalikan_token_sesi_tanpa_cache(): void
    {
        $res = $this->get('/csrf-token')->assertOk();

        $this->assertSame(session()->token(), $res->json('token'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }
}
