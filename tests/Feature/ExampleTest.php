<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root '/' mengarahkan tamu ke halaman login.
     */
    public function test_root_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect();
    }
}
