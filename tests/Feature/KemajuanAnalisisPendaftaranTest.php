<?php

namespace Tests\Feature;

use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kemasukan entiti ke dalam aliran kerja, dan keluarnya semula.
 *
 * NOTA MODUL DIBUANG: skrin "Penetapan Entiti" — yang dahulu menghoskan
 * penandaan peringkat 1.1, "Set Semula" dan penugasan Pegawai Analisis —
 * telah dibuang sepenuhnya bersama laluan dan controllernya.
 *
 * Yang KEKAL ialah operasi domainnya:
 *
 *   KemajuanAnalisisService::lengkapkanPenerimaan()  masuk ke aliran kerja
 *   KemajuanAnalisisService::setSemula()             keluar semula
 *   EntityAssignmentService::assign() / unassign()   penugasan PA
 *
 * Fail ini menguji operasi tersebut secara terus. Ujian yang dahulu memandu
 * skrin melalui HTTP telah digugurkan bersama skrin itu; apabila pengganti
 * modul ditetapkan, ujian antara mukanya ditulis semula pada masa itu.
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
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $this->ppa,
            ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1'],
        );
    }

    private function tugaskan(string $agencyCode, User $pa): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti($agencyCode),
            $pa,
            $this->ppa,
        );
    }

    private function kemajuan(): KemajuanAnalisisService
    {
        return app(KemajuanAnalisisService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Masuk ke aliran kerja
    |--------------------------------------------------------------------------
    */

    public function test_melengkapkan_penerimaan_menyiapkan_peringkat_satu_dan_mengunci_entiti(): void
    {
        $this->daftarkan(self::ALPHA);

        $peringkat = $this->kemajuan()->peringkat(self::ALPHA);

        // Baris dicipta bagi SETIAP peringkat yang ditakrifkan — termasuk
        // yang belum dibina — bukan hanya yang pertama.
        $this->assertCount(count(AliranKerja::kekunci()), $peringkat);

        $this->assertSame(
            WorkflowStageStatus::SELESAI,
            $peringkat[AliranKerja::PENERIMAAN_DATA]->status,
        );

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $peringkat[AliranKerja::PENDAFTARAN_DATA]->status,
        );

        $this->assertTrue($this->kemajuan()->penerimaanSelesai(self::ALPHA));
    }

    /**
     * Modul Penetapan Entiti telah dibuang: tiada laluan HTTP yang boleh
     * memasukkan entiti ke dalam aliran kerja, menetapkannya semula, atau
     * membuat penugasan baharu.
     *
     * Ujian ini yang menghalang mana-mana daripadanya kembali tanpa
     * spesifikasi pengganti.
     */
    public function test_modul_penetapan_entiti_tiada_laluan(): void
    {
        foreach ([
            'penugasan.index',
            'penugasan.show',
            'penugasan.simpan',
            'penugasan.tarik',
            'penugasan.pendaftaran.kemas-kini',
            'penugasan.pendaftaran.set-semula',
        ] as $nama) {
            $this->assertNull(
                app('router')->getRoutes()->getByName($nama),
                "Laluan {$nama} sepatutnya telah dibuang.",
            );
        }
    }

    /**
     * JURANG YANG DIKETAHUI, direkodkan dengan sengaja.
     *
     * Peraturan "entiti hanya boleh ditugaskan setelah peringkat 1.1 Selesai"
     * dikuatkuasakan di dalam EntitiAssignmentController — controller yang
     * telah dibuang bersama modul Penetapan Entiti. Ia TIDAK PERNAH wujud
     * dalam EntityAssignmentService, jadi ia hilang bersama controller itu.
     *
     * Kesannya terhad buat masa ini: tiada antara muka membuat penugasan,
     * jadi satu-satunya pemanggil ialah kod dan seeder. Ujian ini merakam
     * keadaan sebenar supaya jurang itu kelihatan dan bukan senyap — dan
     * mesti ditukar kepada pengecualian apabila peraturan itu dikembalikan
     * ke lapisan domain.
     */
    public function test_servis_penugasan_tiada_lagi_semakan_peringkat_satu(): void
    {
        $this->tugaskan(self::ALPHA, $this->pa);

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'assigned_to_user_id' => $this->pa->id,
        ]);

        $this->assertFalse($this->kemajuan()->penerimaanSelesai(self::ALPHA));
    }

    public function test_entiti_yang_didaftarkan_boleh_ditugaskan(): void
    {
        $this->daftarkan(self::ALPHA);
        $this->tugaskan(self::ALPHA, $this->pa);

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'assigned_to_user_id' => $this->pa->id,
            'status' => EntitiAssignment::STATUS_ACTIVE,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Penugasan menentukan akses Pegawai Analisis
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_menjadikan_entiti_kelihatan_kepada_pa_yang_ditugaskan(): void
    {
        $this->daftarkan(self::ALPHA);
        $this->tugaskan(self::ALPHA, $this->pa);

        $this->actingAs($this->pa->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        // Pegawai lain tetap tertutup daripadanya.
        $this->actingAs($this->paLain->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertForbidden();
    }

    public function test_tukar_pa_memindahkan_akses(): void
    {
        $this->daftarkan(self::ALPHA);
        $this->tugaskan(self::ALPHA, $this->pa);
        $this->tugaskan(self::ALPHA, $this->paLain);

        $this->actingAs($this->paLain->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        // Akses pegawai terdahulu ditarik serta-merta.
        $this->actingAs($this->pa->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertForbidden();

        $this->assertSame(
            1,
            EntitiAssignment::where('agency_code', self::ALPHA)->active()->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Keluar semula daripada aliran kerja — "Set Semula"
    |--------------------------------------------------------------------------
    */

    public function test_set_semula_mengembalikan_entiti_kepada_belum_mula(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->kemajuan()->setSemula(self::ALPHA, $this->kb, 'Data perlu dihantar semula.');

        $this->assertFalse($this->kemajuan()->penerimaanSelesai(self::ALPHA));

        foreach ($this->kemajuan()->peringkat(self::ALPHA) as $peringkat) {
            $this->assertSame(WorkflowStageStatus::BELUM_MULA, $peringkat->status);
        }
    }

    /**
     * Punca pepijat, diuji secara langsung: setSemula() mengekalkan SEMUA
     * baris peringkat (supaya jejak auditnya kekal bermakna), jadi "ada baris
     * peringkat" tidak boleh digunakan sebagai ujian "entiti berdaftar".
     * Peringkat 1.1 Selesai ialah ujian yang betul.
     */
    public function test_baris_peringkat_kekal_selepas_set_semula(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->assertTrue($this->kemajuan()->dalamAliranKerja($this->kemajuan()->peringkat(self::ALPHA)));
        $this->assertContains(self::ALPHA, $this->kemajuan()->kodPenerimaanSelesai());

        $this->kemajuan()->setSemula(self::ALPHA, $this->kb, 'Data perlu dihantar semula.');

        $peringkat = $this->kemajuan()->peringkat(self::ALPHA);

        // Baris kekal — jejak auditnya masih bermakna...
        $this->assertCount(count(AliranKerja::kekunci()), $peringkat);

        // ...tetapi entiti itu telah keluar daripada aliran kerja.
        $this->assertFalse($this->kemajuan()->dalamAliranKerja($peringkat));
        $this->assertNotContains(self::ALPHA, $this->kemajuan()->kodPenerimaanSelesai());
    }

    /**
     * Halaman kemajuan bagi entiti yang ditetapkan semula mesti kembali
     * kepada notis "belum memasuki aliran kerja".
     */
    public function test_halaman_kemajuan_entiti_yang_ditetapkan_semula_menunjukkan_belum_masuk_aliran(): void
    {
        $this->daftarkan(self::ALPHA);
        $this->kemajuan()->setSemula(self::ALPHA, $this->kb, 'Data perlu dihantar semula.');

        $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Belum Memasuki Aliran Kerja');
    }

    public function test_set_semula_menarik_balik_penugasan_aktif(): void
    {
        $this->daftarkan(self::ALPHA);
        $this->tugaskan(self::ALPHA, $this->pa);

        $this->kemajuan()->setSemula(self::ALPHA, $this->kb, 'Data perlu dihantar semula.');

        app(EntityAssignmentService::class)->unassign(
            self::ALPHA,
            $this->kb,
            'Pendaftaran entiti ditetapkan semula.',
        );

        $this->assertNull(app(EntityAssignmentService::class)->activeFor(self::ALPHA));

        // Entiti tidak lagi boleh dicapai oleh pegawai yang ditugaskan.
        $this->actingAs($this->pa->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertForbidden();
    }

    /**
     * Entiti yang ditetapkan semula kekal disenaraikan dalam Kemajuan Analisis
     * (senarai itu disusun mengikut sektor dan memaparkan setiap entiti), tetapi
     * tidak lagi dikira sebagai berada dalam aliran kerja.
     */
    public function test_entiti_yang_ditetapkan_semula_tidak_lagi_dikira_berdaftar(): void
    {
        $this->daftarkan(self::ALPHA);
        $this->daftarkan(self::BETA);

        $this->actingAs($this->ppa)
            ->get(route('workflow.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee('2 entiti telah memasuki aliran kerja');

        $this->kemajuan()->setSemula(self::ALPHA, $this->kb, 'Data perlu dihantar semula.');

        $this->actingAs($this->ppa)
            ->get(route('workflow.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee('Belum Didaftarkan')
            ->assertSee('1 entiti telah memasuki aliran kerja');
    }

    /**
     * Tiada sektor dipilih bermakna tiada senarai — bukan senarai kosong,
     * yang akan terbaca sebagai "tiada entiti dalam sistem".
     */
    public function test_senarai_kemajuan_menuntut_sektor_dipilih(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->ppa)
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertSee('Pilih sektor untuk memaparkan entiti')
            ->assertDontSee(route('workflow.show', self::ALPHA), false);
    }
}
