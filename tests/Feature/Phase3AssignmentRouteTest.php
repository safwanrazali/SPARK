<?php

namespace Tests\Feature;

use App\Models\EntitiAssignment;
use App\Models\User;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASA 3 — route, kebenaran dan UI penugasan entiti.
 */
class Phase3AssignmentRouteTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITI = 'A010101';

    private User $coordinator;

    private User $analystA;

    private User $analystB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras Satu']);
        $this->analystA = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
        $this->analystB = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);
    }

    private function tugaskan(User $analyst): EntitiAssignment
    {
        return app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ENTITI),
            $analyst,
            $this->coordinator,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Akses & kebenaran (gate manage-assignment)
    |--------------------------------------------------------------------------
    */

    public function test_tetamu_dialihkan_ke_log_masuk(): void
    {
        $this->get(route('penugasan.index'))->assertRedirect(route('login'));
    }

    public function test_pegawai_analisis_tidak_boleh_mengakses_modul_penugasan(): void
    {
        $this->actingAs($this->analystA)
            ->get(route('penugasan.index'))
            ->assertForbidden();

        $this->actingAs($this->analystA)
            ->get(route('penugasan.show', self::ENTITI))
            ->assertForbidden();
    }

    public function test_pegawai_analisis_tidak_boleh_membuat_penugasan(): void
    {
        $this->actingAs($this->analystA)
            ->post(route('penugasan.simpan', self::ENTITI), ['assigned_to_user_id' => $this->analystB->id])
            ->assertForbidden();

        $this->assertDatabaseCount('entiti_assignment', 0);
    }

    public function test_pentadbir_dan_penyelaras_boleh_mengakses_modul_penugasan(): void
    {
        $pentadbir = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);

        $this->actingAs($this->coordinator)->get(route('penugasan.index'))->assertOk();
        $this->actingAs($pentadbir)->get(route('penugasan.index'))->assertOk();
    }

    public function test_pautan_penugasan_hanya_dipaparkan_kepada_peranan_yang_dibenarkan(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('workflow.index'))
            ->assertSee('Penetapan Entiti');

        $this->actingAs($this->analystA)
            ->get(route('workflow.index'))
            ->assertDontSee('Penetapan Entiti');
    }

    /*
    |--------------------------------------------------------------------------
    | Pilih sektor → papar entiti
    |--------------------------------------------------------------------------
    */

    public function test_senarai_entiti_dipaparkan_mengikut_sektor(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('penugasan.index', ['sector_code' => '010']));

        $response->assertOk()
            ->assertSee('A100102')
            ->assertSee('A100101')
            ->assertSee('Belum Ditugaskan')
            ->assertSee('Pegawai A');
    }

    public function test_paparan_lalai_menunjukkan_entiti_yang_telah_ditugaskan(): void
    {
        $this->tugaskan($this->analystA);

        $this->actingAs($this->coordinator)
            ->get(route('penugasan.index'))
            ->assertOk()
            ->assertSee('A010101')
            ->assertSee('Pegawai A');
    }

    public function test_entiti_di_luar_senarai_induk_menghasilkan_404(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('penugasan.show', 'ZZZ9999'))
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Tugaskan
    |--------------------------------------------------------------------------
    */

    public function test_entiti_ditugaskan_melalui_http(): void
    {
        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.simpan', self::ENTITI), [
                'assigned_to_user_id' => $this->analystA->id,
                'notes' => 'Penugasan pertama',
            ])
            ->assertRedirect(route('penugasan.show', self::ENTITI))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ENTITI,
            'assigned_to_user_id' => $this->analystA->id,
            'assigned_by_user_id' => $this->coordinator->id,
            'status' => EntitiAssignment::STATUS_ACTIVE,
            'notes' => 'Penugasan pertama',
        ]);
    }

    public function test_penugasan_kepada_bukan_pegawai_analisis_ditolak_melalui_http(): void
    {
        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.simpan', self::ENTITI), [
                'assigned_to_user_id' => $this->coordinator->id,
            ])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseCount('entiti_assignment', 0);
    }

    public function test_penugasan_tanpa_pegawai_ditolak_oleh_validation(): void
    {
        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.simpan', self::ENTITI), [])
            ->assertSessionHasErrors('assigned_to_user_id');
    }

    public function test_penugasan_kepada_pengguna_tidak_wujud_ditolak(): void
    {
        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.simpan', self::ENTITI), ['assigned_to_user_id' => 99999])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseCount('entiti_assignment', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Pendua & tukar ganti
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_pendua_ditolak_melalui_http(): void
    {
        $this->tugaskan($this->analystA);

        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.simpan', self::ENTITI), ['assigned_to_user_id' => $this->analystA->id])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseCount('entiti_assignment', 1);
    }

    public function test_entiti_ditukar_ganti_melalui_http(): void
    {
        $asal = $this->tugaskan($this->analystA);

        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.simpan', self::ENTITI), [
                'assigned_to_user_id' => $this->analystB->id,
                'notes' => 'Pegawai A bertukar bahagian',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entiti_assignment', [
            'id' => $asal->id,
            'status' => EntitiAssignment::STATUS_REASSIGNED,
        ]);

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ENTITI,
            'assigned_to_user_id' => $this->analystB->id,
            'status' => EntitiAssignment::STATUS_ACTIVE,
        ]);

        $this->assertSame(1, EntitiAssignment::query()->forAgency(self::ENTITI)->active()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Tarik balik
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_ditarik_balik_melalui_http(): void
    {
        $this->tugaskan($this->analystA);

        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.tarik', self::ENTITI), ['reason' => 'Entiti ditangguhkan'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ENTITI,
            'status' => EntitiAssignment::STATUS_UNASSIGNED,
            'notes' => 'Entiti ditangguhkan',
        ]);

        $this->assertSame(0, EntitiAssignment::query()->forAgency(self::ENTITI)->active()->count());
    }

    public function test_tarik_balik_tanpa_penugasan_aktif_ditolak_melalui_http(): void
    {
        $this->actingAs($this->coordinator)
            ->from(route('penugasan.show', self::ENTITI))
            ->post(route('penugasan.tarik', self::ENTITI))
            ->assertSessionHasErrors('assigned_to_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Halaman entiti & sejarah
    |--------------------------------------------------------------------------
    */

    public function test_halaman_entiti_memaparkan_penugasan_semasa_dan_sejarah(): void
    {
        $this->tugaskan($this->analystA);
        app(EntityAssignmentService::class)->reassign(
            SektorDirectory::cariEntiti(self::ENTITI),
            $this->analystB,
            $this->coordinator,
        );

        $this->actingAs($this->coordinator)
            ->get(route('penugasan.show', self::ENTITI))
            ->assertOk()
            ->assertSee('Penugasan Semasa')
            ->assertSee('Sejarah Penugasan')
            ->assertSee('Pegawai A')
            ->assertSee('Pegawai B')
            ->assertSee('Ditukar Ganti')
            ->assertSee('Penyelaras Satu');
    }

    public function test_halaman_entiti_belum_ditugaskan(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('penugasan.show', self::ENTITI))
            ->assertOk()
            ->assertSee('belum ditugaskan kepada mana-mana Pegawai Analisis', false)
            ->assertSee('Tugaskan Pegawai')
            ->assertSee('Tiada sejarah penugasan');
    }
}
