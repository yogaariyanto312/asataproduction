<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Jaring regresi selama migrasi ke React/Filament: menembak SEMUA route GET
 * tanpa parameter untuk tiap role dan menolak setiap respons 5xx.
 *
 * Butuh MySQL: sejumlah query memakai fungsi khusus MySQL (YEAR, MONTH, FIELD)
 * yang tidak ada di SQLite, jadi tes ini melewati diri sendiri saat dijalankan
 * dengan koneksi SQLite (mis. di CI).
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Butuh MySQL (query memakai YEAR/MONTH/FIELD).');
        }
    }

    private function user(string $role): User
    {
        return User::create([
            'name'      => ucfirst($role) . ' Smoke',
            'username'  => $role . 'smoke',
            'email'     => $role . '@smoke.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    public function test_tidak_ada_route_get_yang_error(): void
    {
        $failures = [];
        $summary  = [];

        // Route ekspor PDF memakai dompdf, yang membuka output buffer sendiri.
        // Dicatat di sini agar sisa buffer bisa ditutup lagi setelah sweep,
        // supaya PHPUnit tidak menandai tes ini "risky".
        $baseObLevel = ob_get_level();

        foreach (['developer', 'admin', 'supervisor', 'mandor', 'operator'] as $role) {
            $actor = $this->user($role);

            foreach (Route::getRoutes() as $route) {
                if (! in_array('GET', $route->methods(), true)) {
                    continue;
                }

                $uri = $route->uri();
                if (str_contains($uri, '{')) {
                    continue;
                }

                try {
                    $status = $this->actingAs($actor)->get('/' . ltrim($uri, '/'))->getStatusCode();
                } catch (\Throwable $e) {
                    $failures[] = sprintf('[%s] %s => %s: %s', $role, $uri, class_basename($e), $e->getMessage());

                    continue;
                }

                $summary[$status] = ($summary[$status] ?? 0) + 1;

                if ($status >= 500) {
                    $failures[] = sprintf('[%s] %s => HTTP %d', $role, $uri, $status);
                }
            }
        }

        while (ob_get_level() > $baseObLevel) {
            ob_end_clean();
        }

        ksort($summary);
        $line = [];
        foreach ($summary as $code => $count) {
            $line[] = "{$code}:{$count}";
        }
        fwrite(STDERR, "\n  Distribusi status: " . implode('  ', $line) . "\n");

        $this->assertSame([], $failures, "Route bermasalah:\n" . implode("\n", $failures));
    }
}
