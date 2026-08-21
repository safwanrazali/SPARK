<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Halaman;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Peraturan sepunya: setiap jadual senarai memaparkan paling banyak
 * Halaman::SETIAP_MUKA baris dan selebihnya dinomborkan.
 *
 * Ujian ini menyemak setiap skrin senarai menyerahkan paginator (bukan
 * koleksi penuh) kepada paparannya, supaya jadual baharu tidak terlepas
 * daripada peraturan yang sama.
 */
class PenomboranHalamanTest extends TestCase
{
    use RefreshDatabase;

    public function test_had_baris_sepunya_ialah_sepuluh(): void
    {
        $this->assertSame(10, Halaman::SETIAP_MUKA);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function skrinSenarai(): array
    {
        // [nama route, kunci data paparan, peranan pelakon]
        return [
            'senarai pengguna' => ['administration.users.index', 'users', User::ROLE_ADMINISTRATOR],
            'Kemajuan Analisis' => ['workflow.index', 'entiti', User::ROLE_COORDINATOR],
            'penetapan entiti — pendaftaran' => ['penugasan.index', 'pendaftaran', User::ROLE_PENYELARAS_REKOD],
            'penetapan entiti — penugasan' => ['penugasan.index', 'entiti', User::ROLE_COORDINATOR],
            'Analisis Inventori Kriptografi' => ['analisis.index', 'rekod', User::ROLE_COORDINATOR],
            'jejak audit' => ['audit.index', 'rekod', User::ROLE_COORDINATOR],
            'status tiga laporan' => ['status.index', 'entiti', User::ROLE_COORDINATOR],
            'penjanaan laporan' => ['laporan.index', 'rekod', User::ROLE_COORDINATOR],
            'sejarah muat naik' => ['muat-naik.history', 'rekod', User::ROLE_COORDINATOR],
        ];
    }

    #[DataProvider('skrinSenarai')]
    public function test_skrin_senarai_dinomborkan_sepuluh_baris(
        string $route,
        string $kunci,
        string $peranan,
    ): void {
        $response = $this->actingAs(User::factory()->create(['role' => $peranan]))
            ->get(route($route));

        $response->assertOk();

        $data = $response->viewData($kunci);

        $this->assertInstanceOf(Paginator::class, $data, "{$route}: {$kunci} bukan paginator");
        $this->assertSame(Halaman::SETIAP_MUKA, $data->perPage(), "{$route}: had baris tersasar");
    }

    /**
     * Jadual sejarah pada halaman butiran tertakluk kepada peraturan yang
     * sama — dahulunya ia dipotong pada 20/50 rekod tanpa jalan ke rekod
     * yang lebih lama.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function skrinSejarah(): array
    {
        return [
            'pusat maklumat entiti' => ['entiti.show', User::ROLE_COORDINATOR],
            'Kemajuan Analisis Entiti' => ['workflow.show', User::ROLE_COORDINATOR],
            'sejarah penugasan' => ['penugasan.show', User::ROLE_COORDINATOR],
        ];
    }

    #[DataProvider('skrinSejarah')]
    public function test_jadual_sejarah_dinomborkan_sepuluh_baris(string $route, string $peranan): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => $peranan]))
            ->get(route($route, 'A010101'));

        $response->assertOk();

        $sejarah = $response->viewData('sejarah');

        $this->assertInstanceOf(Paginator::class, $sejarah, "{$route}: sejarah bukan paginator");
        $this->assertSame(Halaman::SETIAP_MUKA, $sejarah->perPage(), "{$route}: had baris tersasar");
    }
}
