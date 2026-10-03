<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementSupervisorTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_supervisor_menampilkan_daftar_supervisor(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $spv = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Uji']);

        $res = $this->actingAs($dev)->get('/management?tab=supervisor');
        $res->assertOk();
        $res->assertSee($spv->name, false);
        $res->assertDontSee('Belum ada supervisor', false);
    }
}
