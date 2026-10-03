<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ekspor laporan tetap memakai Blade + dompdf/maatwebsite, bukan React.
 * Tes ini menjaga agar migrasi halaman ke Inertia tidak diam-diam merusaknya.
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Butuh MySQL (query laporan memakai fungsi khusus MySQL).');
        }
    }

    private function admin(): User
    {
        return User::create([
            'name'      => 'Admin Ekspor',
            'username'  => 'adminekspor',
            'email'     => 'admin@ekspor.test',
            'role'      => 'admin',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function bodyOf($response): string
    {
        try {
            $body = $response->streamedContent();
        } catch (\Throwable $e) {
            $body = '';
        }

        return $body !== '' ? $body : (string) $response->getContent();
    }

    public function test_export_pdf_bulanan_masih_pdf_valid(): void
    {
        $res = $this->actingAs($this->admin())->get('/reports/export-pdf');
        $res->assertOk();

        $this->assertStringStartsWith('%PDF-', $this->bodyOf($res));
    }

    public function test_export_excel_masih_xlsx_valid(): void
    {
        $res = $this->actingAs($this->admin())->get('/reports/export-excel');
        $res->assertOk();

        // xlsx adalah arsip ZIP — magic bytes "PK".
        $this->assertSame('504b', bin2hex(substr($this->bodyOf($res), 0, 2)));
    }

    public function test_export_pdf_harian_masih_pdf_valid(): void
    {
        $res = $this->actingAs($this->admin())->get('/reports/daily-pdf');
        $res->assertOk();

        $this->assertStringStartsWith('%PDF-', $this->bodyOf($res));
    }
}
