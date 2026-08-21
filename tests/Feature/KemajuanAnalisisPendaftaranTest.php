<?php

namespace Tests\Feature;

use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use App\Services\KemajuanAnalisisService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carta aliran Kemajuan Analisis Entiti — peringkat 1 dan Pemantauan Entiti.
 *
 * Senario A: PPR melengkapkan pendaftaran → entiti dikunci → muncul kepada PPA.
 * Senario B: PPA menugaskan entiti kepada PA → entiti muncul kepada PA itu.
 */
class KemajuanAnalisisPendaftaranTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    private User $ppr;

    private User $ppa;

    private User $pa;

    private User $paLain;

    private User $kb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD, 'name' => 'Rekod Satu']);
        $this->ppa = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras Satu']);
        $this->pa = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
        $this->paLain = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);
        $this->kb = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN, 'name' => 'Ketua Satu']);
    }

    private function daftarkan(string $agencyCode): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPendaftaran(
            SektorDirectory::cariEntiti($agencyCode),
            $this->ppr,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Senario A — PPR melengkapkan Penerimaan & Pendaftaran Data
    |--------------------------------------------------------------------------
    */

    public function test_ppr_boleh_membuka_skrin_penetapan_entiti(): void
    {
        $this->actingAs($this->ppr)
            ->get(route('penugasan.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee('Penerimaan &amp; Pendaftaran Data', false)
            ->assertSee('Kemas Kini');
    }

    /**
     * Tanpa penapis sektor, senarai hanya memaparkan entiti yang telah dikunci.
     * Tiada apa yang boleh ditanda di situ, jadi borangnya tidak dipaparkan
     * langsung — bukan sekadar dilumpuhkan.
     */
    public function test_senarai_entiti_berdaftar_tiada_borang_kemas_kini(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppr)
            ->get(route('penugasan.index'))
            ->assertOk()
            ->assertSee(self::ALPHA)
            ->assertDontSee('Kemas Kini')
            ->assertDontSee('name="agency_codes[]"', false)
            ->assertSee('bi-lock-fill', false);
    }

    /**
     * Kotak semak ialah alat Pegawai Penyelaras Rekod. Ketua Bahagian hadir
     * pada panel ini untuk "Set Semula" sahaja, jadi ia melihat ikon keadaan
     * dan bukan kawalan yang tidak boleh digunakannya.
     */
    public function test_hanya_ppr_melihat_kotak_semak(): void
    {
        $this->actingAs($this->ppr)
            ->get(route('penugasan.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee('name="agency_codes[]"', false);

        $this->actingAs($this->kb)
            ->get(route('penugasan.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee(self::ALPHA)
            ->assertDontSee('name="agency_codes[]"', false)
            ->assertSee('bi-unlock', false);
    }

    public function test_kemas_kini_menandakan_peringkat_satu_selesai_dan_mengunci_entiti(): void
    {
        $this->actingAs($this->ppr)
            ->post(route('penugasan.pendaftaran.kemas-kini'), [
                'agency_codes' => [self::ALPHA],
            ])
            ->assertRedirect();

        $peringkat = app(KemajuanAnalisisService::class)->peringkat(self::ALPHA);

        // Kesemua tujuh peringkat dicipta, bukan hanya yang pertama.
        $this->assertCount(count(WorkflowStatus::WORKFLOW_STAGES), $peringkat);

        $this->assertSame(
            WorkflowStageStatus::SELESAI,
            $peringkat[WorkflowStatus::STAGE_PENDAFTARAN]->status,
        );

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $peringkat[WorkflowStatus::STAGE_SEMAKAN_AWAL]->status,
        );

        $this->assertTrue(app(KemajuanAnalisisService::class)->pendaftaranSelesai(self::ALPHA));
    }

    public function test_entiti_yang_dikunci_tidak_boleh_ditanda_semula_oleh_ppr(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppr)
            ->post(route('penugasan.pendaftaran.kemas-kini'), [
                'agency_codes' => [self::ALPHA],
            ])
            ->assertSessionHasErrors('agency_codes');
    }

    public function test_peranan_lain_tidak_boleh_melengkapkan_pendaftaran(): void
    {
        foreach ([$this->ppa, $this->pa, $this->kb] as $pengguna) {
            $this->actingAs($pengguna)
                ->post(route('penugasan.pendaftaran.kemas-kini'), [
                    'agency_codes' => [self::ALPHA],
                ])
                ->assertForbidden();
        }

        $this->assertFalse(app(KemajuanAnalisisService::class)->pendaftaranSelesai(self::ALPHA));
    }

    public function test_entiti_yang_didaftarkan_muncul_kepada_ppa(): void
    {
        $this->actingAs($this->ppa)
            ->get(route('penugasan.index'))
            ->assertOk()
            ->assertDontSee(self::ALPHA);

        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppa)
            ->get(route('penugasan.index'))
            ->assertOk()
            ->assertSee(self::ALPHA);
    }

    public function test_entiti_belum_didaftarkan_tidak_boleh_ditugaskan(): void
    {
        $this->actingAs($this->ppa)
            ->post(route('penugasan.simpan', self::BETA), [
                'assigned_to_user_id' => $this->pa->id,
            ])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseCount('entiti_assignment', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Set Semula — Ketua Bahagian sahaja
    |--------------------------------------------------------------------------
    */

    public function test_ketua_bahagian_boleh_menetapkan_semula_entiti_yang_dikunci(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->kb)
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), [
                'reason' => 'Data diterima tidak lengkap.',
            ])
            ->assertRedirect();

        $this->assertFalse(app(KemajuanAnalisisService::class)->pendaftaranSelesai(self::ALPHA));

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            app(KemajuanAnalisisService::class)->peringkat(self::ALPHA)[WorkflowStatus::STAGE_PENDAFTARAN]->status,
        );
    }

    /**
     * Entiti yang ditetapkan semula telah keluar daripada aliran kerja, jadi
     * ia tidak boleh kekal dalam senarai "Kedudukan Semasa Entiti".
     *
     * setSemula() mengekalkan ketujuh-tujuh baris peringkat (supaya jejak
     * auditnya kekal bermakna) dan hanya mengembalikan statusnya kepada
     * Belum Mula. Senarai yang menguji "ada baris peringkat" akan terus
     * memaparkan entiti itu; peringkat 01 Selesai ialah ujian yang betul.
     */
    public function test_entiti_yang_ditetapkan_semula_tidak_disenaraikan_dalam_kemajuan_analisis(): void
    {
        $this->daftarkan(self::ALPHA);
        $this->daftarkan(self::BETA);

        $this->actingAs($this->ppa)
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertSee(route('workflow.show', self::ALPHA), false)
            ->assertSee(route('workflow.show', self::BETA), false)
            ->assertSee('2 entiti telah didaftarkan');

        $this->actingAs($this->kb)
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Data perlu dihantar semula.'])
            ->assertSessionHasNoErrors();

        // Pautan baris disemak, bukan kod entiti: mesej kejayaan Set Semula
        // turut menyebut kod itu.
        $this->actingAs($this->ppa)
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertDontSee(route('workflow.show', self::ALPHA), false)
            ->assertSee(route('workflow.show', self::BETA), false)
            ->assertSee('1 entiti telah didaftarkan');
    }

    /**
     * Punca pepijat, diuji secara langsung: setSemula() mengekalkan
     * ketujuh-tujuh baris peringkat, jadi "ada baris peringkat" tidak boleh
     * digunakan sebagai ujian "entiti berdaftar".
     */
    public function test_baris_peringkat_kekal_selepas_set_semula_tetapi_entiti_tidak_lagi_berdaftar(): void
    {
        $this->daftarkan(self::ALPHA);

        $kemajuan = app(KemajuanAnalisisService::class);

        $this->assertTrue($kemajuan->didaftarkanDaripada($kemajuan->peringkat(self::ALPHA)));
        $this->assertContains(self::ALPHA, $kemajuan->kodPendaftaranSelesai());

        $this->actingAs($this->kb)
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Data perlu dihantar semula.']);

        $peringkat = $kemajuan->peringkat(self::ALPHA);

        // Baris kekal — jejak auditnya masih bermakna...
        $this->assertCount(count(WorkflowStatus::WORKFLOW_STAGES), $peringkat);

        // ...tetapi entiti itu telah keluar daripada aliran kerja.
        $this->assertFalse($kemajuan->didaftarkanDaripada($peringkat));
        $this->assertNotContains(self::ALPHA, $kemajuan->kodPendaftaranSelesai());
    }

    /**
     * Halaman kemajuan bagi entiti yang ditetapkan semula mesti kembali
     * kepada notis "belum memasuki aliran kerja".
     */
    public function test_halaman_kemajuan_entiti_yang_ditetapkan_semula_menunjukkan_belum_masuk_aliran(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee('Belum Memasuki Aliran Kerja');

        $this->actingAs($this->kb)
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Data perlu dihantar semula.']);

        $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Belum Memasuki Aliran Kerja');
    }

    public function test_ppr_tidak_boleh_menetapkan_semula_entiti(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppr)
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Cuba buka semula.'])
            ->assertForbidden();

        $this->assertTrue(app(KemajuanAnalisisService::class)->pendaftaranSelesai(self::ALPHA));
    }

    public function test_set_semula_menarik_balik_penugasan_dan_menyembunyikan_entiti_daripada_ppa(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppa)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->pa->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'status' => EntitiAssignment::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->kb)
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Data perlu dihantar semula.']);

        $this->assertDatabaseMissing('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'status' => EntitiAssignment::STATUS_ACTIVE,
        ]);

        // Mesej kejayaan Set Semula menyebut kod entiti, jadi kehadiran kod
        // itu pada halaman berikutnya bukan bukti ia masih tersenarai —
        // keadaan jadual disemak terus.
        $this->actingAs($this->ppa)
            ->get(route('penugasan.index'))
            ->assertOk()
            ->assertSee('Tiada entiti tersedia');
    }

    /*
    |--------------------------------------------------------------------------
    | Senario B — PPA menugaskan entiti kepada PA
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_menjadikan_entiti_kelihatan_kepada_pa_yang_ditugaskan(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppa)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->pa->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->pa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        $this->actingAs($this->paLain)
            ->get(route('workflow.show', self::ALPHA))
            ->assertForbidden();
    }

    public function test_tukar_pa_menggantikan_penugasan_terdahulu(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppa)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->pa->id]);

        $this->actingAs($this->ppa)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->paLain->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'assigned_to_user_id' => $this->paLain->id,
            'status' => EntitiAssignment::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'assigned_to_user_id' => $this->pa->id,
            'status' => EntitiAssignment::STATUS_REASSIGNED,
        ]);

        $this->actingAs($this->pa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertForbidden();
    }
}
