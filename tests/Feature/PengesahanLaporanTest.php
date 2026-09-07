<?php

namespace Tests\Feature;

use App\Http\Controllers\LaporanController;
use App\Models\AnalisisInventori;
use App\Models\LaporanSemakan;
use App\Models\User;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jadual Pengesahan Laporan mesti memaparkan pegawai yang BENAR-BENAR
 * mengesahkan laporan, bukan nama tetap dalam config.
 */
class PengesahanLaporanTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITI = 'A010101';

    public function test_baris_disahkan_menggunakan_pegawai_aliran_kerja(): void
    {
        $entiti = SektorDirectory::cariEntiti(self::ENTITI);

        $kb = User::factory()->create(['name' => 'Pegawai Pengesah Sebenar']);

        LaporanSemakan::create($entiti + [
            'report_type' => 'inventori',
            'status' => LaporanSemakan::SAH,
            'disahkan_oleh_user_id' => $kb->id,
            'disahkan_pada' => '2026-08-21 09:00:00',
        ]);

        $analisis = AnalisisInventori::factory()->create($entiti);

        $pengesahan = $this->pengesahan($analisis);

        // Baris pertama mengambil nama dan tarikh daripada aliran kerja.
        $this->assertSame('Pegawai Pengesah Sebenar', $pengesahan[0]['nama']);
        $this->assertSame('21/08/2026', $pengesahan[0]['tarikh']);

        // Baris lain tiada langkah aliran kerja: tarikh kekal kosong untuk
        // ditulis tangan, dan namanya tidak diambil daripada pengesah.
        $this->assertSame('', $pengesahan[1]['tarikh']);
        $this->assertNotSame('Pegawai Pengesah Sebenar', $pengesahan[1]['nama']);
        $this->assertSame('', $pengesahan[2]['nama']);
    }

    public function test_sebelum_disahkan_nama_sandaran_config_digunakan(): void
    {
        $analisis = AnalisisInventori::factory()->create(SektorDirectory::cariEntiti(self::ENTITI));

        $pengesahan = $this->pengesahan($analisis);

        $this->assertSame(
            config('kriptografi.pengesahan_laporan.0.nama'),
            $pengesahan[0]['nama'],
        );
        $this->assertSame('', $pengesahan[0]['tarikh']);
    }

    public function test_tiga_baris_mengikut_templat(): void
    {
        $analisis = AnalisisInventori::factory()->create(SektorDirectory::cariEntiti(self::ENTITI));

        $pengesahan = $this->pengesahan($analisis);

        // Templat membawa TIGA baris: dua baris bernama daripada config, dan
        // satu baris "Diluluskan oleh:" kosong untuk ditandatangani tangan.
        $this->assertCount(3, $pengesahan);
        $this->assertSame(
            ['Disahkan oleh', 'Diluluskan oleh', 'Diluluskan oleh'],
            array_map(fn ($b) => explode(':', $b['peranan'])[0], $pengesahan),
        );
    }

    /**
     * @return list<array{peranan: string, nama: string, tarikh: string}>
     */
    private function pengesahan(AnalisisInventori $analisis): array
    {
        $kaedah = new \ReflectionMethod(LaporanController::class, 'pengesahan');
        $kaedah->setAccessible(true);

        return $kaedah->invoke(app(LaporanController::class), $analisis);
    }
}
