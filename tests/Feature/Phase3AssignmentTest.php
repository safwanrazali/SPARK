<?php

namespace Tests\Feature;

use App\Exceptions\InvalidAssignmentException;
use App\Models\ActivityLog;
use App\Models\EntitiAssignment;
use App\Models\User;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FASA 3 — penugasan entiti kepada Pegawai Analisis.
 *
 * Meliputi penugasan, penukaran ganti, penugasan tidak sah, penugasan pendua
 * dan kekalnya rekod penugasan dalam pangkalan data.
 */
class Phase3AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITI = 'A010101';

    private EntityAssignmentService $service;

    private User $coordinator;

    private User $analystA;

    private User $analystB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EntityAssignmentService::class);
        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras Satu']);
        $this->analystA = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
        $this->analystB = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);
    }

    /**
     * @return array<string, string>
     */
    private function entiti(string $agencyCode = self::ENTITI): array
    {
        return SektorDirectory::cariEntiti($agencyCode);
    }

    /*
    |--------------------------------------------------------------------------
    | Penugasan
    |--------------------------------------------------------------------------
    */

    public function test_entiti_boleh_ditugaskan_kepada_pegawai_analisis(): void
    {
        Carbon::setTestNow('2026-08-14 10:00:00');

        $penugasan = $this->service->assign($this->entiti(), $this->analystA, $this->coordinator, 'Penugasan awal');

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ENTITI,
            'agency_name' => 'Suruhanjaya Pilihan Raya (SPR)',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => $this->analystA->id,
            'assigned_by_user_id' => $this->coordinator->id,
            'status' => EntitiAssignment::STATUS_ACTIVE,
            'notes' => 'Penugasan awal',
        ]);

        $this->assertSame('2026-08-14 10:00:00', $penugasan->assigned_at->format('Y-m-d H:i:s'));
        $this->assertTrue($penugasan->isActive());

        Carbon::setTestNow();
    }

    public function test_penugasan_dikaitkan_dengan_entiti_dan_pegawai(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);

        $penugasan = EntitiAssignment::where('agency_code', self::ENTITI)->firstOrFail();

        $this->assertSame($this->analystA->id, $penugasan->assignedTo->id);
        $this->assertSame($this->coordinator->id, $penugasan->assignedBy->id);
        $this->assertCount(1, $this->analystA->assignedEntities);
        $this->assertSame(self::ENTITI, $this->analystA->assignedEntities->first()->agency_code);
    }

    public function test_beberapa_entiti_boleh_ditugaskan_kepada_pegawai_yang_sama(): void
    {
        $this->service->assign($this->entiti('A010101'), $this->analystA, $this->coordinator);
        $this->service->assign($this->entiti('A010102'), $this->analystA, $this->coordinator);
        $this->service->assign($this->entiti('A010103'), $this->analystB, $this->coordinator);

        $this->assertCount(2, $this->analystA->fresh()->assignedEntities);
        $this->assertCount(1, $this->analystB->fresh()->assignedEntities);
    }

    /*
    |--------------------------------------------------------------------------
    | Penugasan tidak sah
    |--------------------------------------------------------------------------
    */

    public function test_entiti_tidak_boleh_ditugaskan_kepada_bukan_pegawai_analisis(): void
    {
        $this->expectException(InvalidAssignmentException::class);

        $this->service->assign($this->entiti(), $this->coordinator, $this->coordinator);
    }

    public function test_penugasan_kepada_pentadbir_ditolak_dan_tiada_rekod_dicipta(): void
    {
        $pentadbir = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);

        try {
            $this->service->assign($this->entiti(), $pentadbir, $this->coordinator);
            $this->fail('Penugasan kepada pentadbir sepatutnya ditolak.');
        } catch (InvalidAssignmentException) {
            // dijangka
        }

        $this->assertDatabaseCount('entiti_assignment', 0);
    }

    public function test_tukar_ganti_tanpa_penugasan_aktif_ditolak(): void
    {
        $this->expectException(InvalidAssignmentException::class);

        $this->service->reassign($this->entiti(), $this->analystA, $this->coordinator);
    }

    public function test_tarik_balik_tanpa_penugasan_aktif_ditolak(): void
    {
        $this->expectException(InvalidAssignmentException::class);

        $this->service->unassign(self::ENTITI, $this->coordinator);
    }

    /*
    |--------------------------------------------------------------------------
    | Penugasan pendua
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_pendua_kepada_pegawai_yang_sama_ditolak(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);

        $this->expectException(InvalidAssignmentException::class);

        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
    }

    public function test_penugasan_pendua_tidak_menambah_rekod(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);

        try {
            $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        } catch (InvalidAssignmentException) {
            // dijangka
        }

        $this->assertDatabaseCount('entiti_assignment', 1);
        $this->assertSame(1, EntitiAssignment::query()->forAgency(self::ENTITI)->active()->count());
    }

    public function test_dua_penugasan_aktif_pada_entiti_sama_dihalang_pada_peringkat_database(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);

        // Memintas service — kekangan unik pangkalan data mesti tetap menghalang konflik.
        $this->expectException(QueryException::class);

        EntitiAssignment::create([
            'agency_code' => self::ENTITI,
            'agency_name' => 'Suruhanjaya Pilihan Raya (SPR)',
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => $this->analystB->id,
            'assigned_by_user_id' => $this->coordinator->id,
            'status' => EntitiAssignment::STATUS_ACTIVE,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tukar ganti (reassign)
    |--------------------------------------------------------------------------
    */

    public function test_entiti_boleh_ditukar_ganti_kepada_pegawai_lain(): void
    {
        $asal = $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $baharu = $this->service->reassign($this->entiti(), $this->analystB, $this->coordinator, 'Pegawai A bercuti');

        $this->assertSame(EntitiAssignment::STATUS_REASSIGNED, $asal->fresh()->status);
        $this->assertSame(EntitiAssignment::STATUS_ACTIVE, $baharu->status);
        $this->assertSame($this->analystB->id, $baharu->assigned_to_user_id);
        $this->assertSame('Pegawai A bercuti', $baharu->notes);

        $this->assertSame(1, EntitiAssignment::query()->forAgency(self::ENTITI)->active()->count());
        $this->assertDatabaseCount('entiti_assignment', 2);
    }

    public function test_tukar_ganti_juga_boleh_dilakukan_terus_melalui_assign(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->assign($this->entiti(), $this->analystB, $this->coordinator);

        $aktif = $this->service->activeFor(self::ENTITI);

        $this->assertSame($this->analystB->id, $aktif->assigned_to_user_id);
        $this->assertSame(1, EntitiAssignment::query()->forAgency(self::ENTITI)->active()->count());
    }

    public function test_pegawai_lama_tidak_lagi_melihat_entiti_selepas_ditukar_ganti(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->reassign($this->entiti(), $this->analystB, $this->coordinator);

        $this->assertNotContains(self::ENTITI, $this->analystA->fresh()->getAccessibleEntities());
        $this->assertContains(self::ENTITI, $this->analystB->fresh()->getAccessibleEntities());
    }

    public function test_penugasan_berulang_kepada_pegawai_sama_mengekalkan_sejarah_penuh(): void
    {
        // A → B → A → B menguji kekangan unik yang dibetulkan dalam Fasa 3.
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->assign($this->entiti(), $this->analystB, $this->coordinator);
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->assign($this->entiti(), $this->analystB, $this->coordinator);

        $sejarah = $this->service->history(self::ENTITI);

        $this->assertCount(4, $sejarah);
        $this->assertSame(1, EntitiAssignment::query()->forAgency(self::ENTITI)->active()->count());
        $this->assertSame($this->analystB->id, $this->service->activeFor(self::ENTITI)->assigned_to_user_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Tarik balik penugasan
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_boleh_ditarik_balik(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);

        $ditarik = $this->service->unassign(self::ENTITI, $this->coordinator, 'Entiti ditangguhkan');

        $this->assertSame(EntitiAssignment::STATUS_UNASSIGNED, $ditarik->fresh()->status);
        $this->assertNull($this->service->activeFor(self::ENTITI));
        $this->assertDatabaseCount('entiti_assignment', 1);
        $this->assertEmpty($this->analystA->fresh()->getAccessibleEntities());
    }

    public function test_entiti_boleh_ditugaskan_semula_selepas_ditarik_balik(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->unassign(self::ENTITI, $this->coordinator);
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);

        $this->assertSame($this->analystA->id, $this->service->activeFor(self::ENTITI)->assigned_to_user_id);
        $this->assertDatabaseCount('entiti_assignment', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Kekalnya rekod penugasan
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_kekal_selepas_dibaca_semula_daripada_database(): void
    {
        Carbon::setTestNow('2026-08-14 09:30:00');

        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator, 'Catatan penugasan');

        Carbon::setTestNow();

        $dariDatabase = EntitiAssignment::query()
            ->forAgency(self::ENTITI)
            ->active()
            ->firstOrFail();

        $this->assertSame($this->analystA->id, $dariDatabase->assigned_to_user_id);
        $this->assertSame($this->coordinator->id, $dariDatabase->assigned_by_user_id);
        $this->assertSame('2026-08-14 09:30:00', $dariDatabase->assigned_at->format('Y-m-d H:i:s'));
        $this->assertSame(EntitiAssignment::STATUS_ACTIVE, $dariDatabase->status);
        $this->assertSame('Catatan penugasan', $dariDatabase->notes);
        $this->assertSame('Pegawai A', $dariDatabase->assignedTo->name);
    }

    public function test_sejarah_penugasan_disusun_terbaharu_di_atas(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->assign($this->entiti(), $this->analystB, $this->coordinator);

        $sejarah = $this->service->history(self::ENTITI);

        $this->assertSame($this->analystB->id, $sejarah->first()->assigned_to_user_id);
        $this->assertSame($this->analystA->id, $sejarah->last()->assigned_to_user_id);
    }

    public function test_penugasan_aktif_boleh_dicari_untuk_banyak_entiti(): void
    {
        $this->service->assign($this->entiti('A010101'), $this->analystA, $this->coordinator);
        $this->service->assign($this->entiti('A010102'), $this->analystB, $this->coordinator);

        $aktif = $this->service->activeForMany(['A010101', 'A010102', 'A010103']);

        $this->assertCount(2, $aktif);
        $this->assertSame($this->analystA->id, $aktif->get('A010101')->assigned_to_user_id);
        $this->assertNull($aktif->get('A010103'));
    }

    public function test_senarai_pegawai_analisis_tidak_memasukkan_peranan_lain(): void
    {
        $senarai = $this->service->analystsAvailable();

        $this->assertCount(2, $senarai);
        $this->assertTrue($senarai->every(fn (User $u) => $u->isAnalyst()));
    }

    /*
    |--------------------------------------------------------------------------
    | Rekod untuk jejak audit (Fasa 8)
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_dicatat_untuk_jejak_audit(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);

        $log = ActivityLog::where('agency_code', self::ENTITI)
            ->where('action', EntityAssignmentService::ACTION_CREATED)
            ->firstOrFail();

        $this->assertNull($log->old_value);
        $this->assertSame('Pegawai A', $log->new_value);
        $this->assertSame($this->coordinator->id, $log->changed_by_user_id);
        $this->assertSame($this->analystA->id, $log->metadata['assigned_to_user_id']);
        $this->assertSame('Penugasan Dibuat', $log->getActionLabel());
    }

    public function test_penukaran_ganti_dicatat_untuk_jejak_audit(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->reassign($this->entiti(), $this->analystB, $this->coordinator);

        $log = ActivityLog::where('agency_code', self::ENTITI)
            ->where('action', EntityAssignmentService::ACTION_UPDATED)
            ->firstOrFail();

        $this->assertSame('Pegawai A', $log->old_value);
        $this->assertSame('Pegawai B', $log->new_value);
        $this->assertSame($this->analystA->id, $log->metadata['previous_user_id']);
    }

    public function test_penarikan_balik_dicatat_untuk_jejak_audit(): void
    {
        $this->service->assign($this->entiti(), $this->analystA, $this->coordinator);
        $this->service->unassign(self::ENTITI, $this->coordinator, 'Entiti ditangguhkan');

        $log = ActivityLog::where('agency_code', self::ENTITI)
            ->where('action', EntityAssignmentService::ACTION_REMOVED)
            ->firstOrFail();

        $this->assertSame('Pegawai A', $log->old_value);
        $this->assertNull($log->new_value);
        $this->assertSame('Entiti ditangguhkan', $log->metadata['reason']);
    }
}
