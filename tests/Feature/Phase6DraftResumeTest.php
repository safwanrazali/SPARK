<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalisDraftHistory;
use App\Models\AnalisisInventori;
use App\Models\User;
use App\Services\AnalisisDraftService;
use App\Services\EntityAssignmentService;
use App\Support\SeksyenAnalisis;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FASA 6 — draf, simpan dan sambung semula borang analisis.
 *
 * Meliputi aliran penuh spesifikasi bahagian 18:
 * Start → Fill → Save Draft → Exit → Return → Resume → Continue → Complete
 *
 * Tiada muat naik dokumen terlibat; semua dapatan dimasukkan melalui
 * komponen borang berstruktur.
 */
class Phase6DraftResumeTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITI = 'A010101';

    private const ENTITI_LAIN = 'A010102';

    private User $analyst;

    private User $analystLain;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras']);
        $this->analyst = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
        $this->analystLain = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);

        $assignments = app(EntityAssignmentService::class);
        $assignments->assign(SektorDirectory::cariEntiti(self::ENTITI), $this->analyst, $this->coordinator);
        $assignments->assign(SektorDirectory::cariEntiti(self::ENTITI_LAIN), $this->analystLain, $this->coordinator);
    }

    /**
     * Muatan borang separa — hanya sebahagian seksyen diisi.
     *
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    private function borangSepara(array $tambahan = []): array
    {
        return array_merge([
            'sector_code' => '001',
            'agency_code' => self::ENTITI,
            'kod_rujukan' => 'PTPKM/INV/2026/001',
            'tarikh_laporan' => '2026-08-14',
        ], $tambahan);
    }

    /**
     * Muatan borang lengkap untuk simpanan muktamad.
     *
     * @return array<string, mixed>
     */
    private function borangLengkap(array $tambahan = []): array
    {
        return array_merge([
            'sector_code' => '001',
            'agency_code' => self::ENTITI,
            'kod_rujukan' => 'PTPKM/INV/2026/001',
            'tarikh_laporan' => '2026-08-14',
            'status_laporan' => 'Muktamad',
            'ringkasan_data' => 'lengkap',
            'kesimpulan_lain' => 'Kesimpulan akhir.',
        ], $tambahan);
    }

    /*
    |--------------------------------------------------------------------------
    | Start → Save Draft
    |--------------------------------------------------------------------------
    */

    public function test_draf_boleh_dimulakan_untuk_entiti_tanpa_rekod_analisis(): void
    {
        $this->assertDatabaseCount('analisis_inventori', 0);

        $this->actingAs($this->analyst)
            ->from(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]))
            ->post(route('analisis.draf'), $this->borangSepara())
            ->assertRedirect()
            ->assertSessionHas('success');

        // Rekod induk dicipta sebagai cangkerang, belum selesai.
        $this->assertDatabaseHas('analisis_inventori', [
            'agency_code' => self::ENTITI,
            'selesai' => false,
        ]);

        $this->assertDatabaseHas('analisis_draft_history', [
            'section_name' => 'maklumat',
            'version' => 1,
            'is_current' => true,
            'saved_by_user_id' => $this->analyst->id,
        ]);
    }

    public function test_draf_menyimpan_data_separa_tanpa_pengesahan_penuh(): void
    {
        // Tiada status_laporan mahupun ringkasan_data — simpanan muktamad
        // akan menolaknya, tetapi draf mesti diterima.
        $this->actingAs($this->analyst)
            ->post(route('analisis.draf'), $this->borangSepara())
            ->assertSessionHasNoErrors();

        $analisis = AnalisisInventori::where('agency_code', self::ENTITI)->firstOrFail();
        $borang = app(AnalisisDraftService::class)->borangDipulihkan($analisis);

        $this->assertSame('PTPKM/INV/2026/001', $borang['kod_rujukan']);
        $this->assertSame('2026-08-14', $borang['tarikh_laporan']);
    }

    public function test_simpanan_muktamad_menolak_data_separa_yang_sama(): void
    {
        $this->actingAs($this->analyst)
            ->from(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]))
            ->post(route('analisis.simpan'), $this->borangSepara())
            ->assertSessionHasErrors();
    }

    public function test_masa_simpanan_terakhir_direkodkan(): void
    {
        Carbon::setTestNow('2026-08-14 09:15:00');

        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        Carbon::setTestNow();

        $draf = AnalisDraftHistory::where('section_name', 'maklumat')->firstOrFail();

        $this->assertSame('2026-08-14 09:15:00', $draf->saved_at->format('Y-m-d H:i:s'));
    }

    public function test_hubungan_entiti_laporan_dan_pengguna_dijejaki(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $analisis = AnalisisInventori::where('agency_code', self::ENTITI)->firstOrFail();
        $draf = AnalisDraftHistory::where('section_name', 'maklumat')->firstOrFail();

        $this->assertSame($analisis->id, $draf->analisis_inventori_id);
        $this->assertSame(self::ENTITI, $draf->analisisInventori->agency_code);
        $this->assertSame($this->analyst->id, $draf->savedBy->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Exit → Return → Resume
    |--------------------------------------------------------------------------
    */

    public function test_aliran_penuh_simpan_keluar_kembali_sambung_teruskan_muktamad(): void
    {
        $borangUrl = route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]);

        // 1. Start + Fill + Save Draft
        Carbon::setTestNow('2026-08-14 09:00:00');
        $this->actingAs($this->analyst)
            ->post(route('analisis.draf'), $this->borangSepara([
                'kesimpulan_lain' => 'Draf awal kesimpulan.',
            ]))
            ->assertSessionHasNoErrors();

        // 2. Exit — sesi tamat.
        $this->flushSession();

        // 3. Return + Resume — borang memaparkan semula kerja yang disimpan.
        Carbon::setTestNow('2026-08-15 10:00:00');
        $this->actingAs($this->analyst)
            ->get($borangUrl)
            ->assertOk()
            ->assertSee('PTPKM/INV/2026/001')
            ->assertSee('Draf awal kesimpulan.')
            ->assertSee('Draf versi 1 disambung semula');

        // 4. Continue + Save again — tambah maklumat pada seksyen lain.
        $this->actingAs($this->analyst)
            ->post(route('analisis.draf'), $this->borangSepara([
                'kesimpulan_lain' => 'Draf awal kesimpulan.',
                'protokol' => [['nama' => 'TLS', 'versi' => '1.2', 'bilangan' => '4', 'nota' => '']],
            ]))
            ->assertSessionHasNoErrors();

        $analisis = AnalisisInventori::where('agency_code', self::ENTITI)->firstOrFail();
        $borang = app(AnalisisDraftService::class)->borangDipulihkan($analisis);

        $this->assertSame('Draf awal kesimpulan.', $borang['kesimpulan_lain']);
        $this->assertSame('TLS', $borang['protokol'][0]['nama']);

        // 5. Complete — simpanan muktamad dengan medan wajib.
        $this->actingAs($this->analyst)
            ->post(route('analisis.simpan'), $this->borangLengkap([
                'kesimpulan_lain' => 'Draf awal kesimpulan.',
                'protokol' => [['nama' => 'TLS', 'versi' => '1.2', 'bilangan' => '4', 'nota' => '']],
                'selesai' => '1',
            ]))
            ->assertRedirect(route('analisis.index'))
            ->assertSessionHas('success');

        Carbon::setTestNow();

        $analisis->refresh();

        $this->assertTrue($analisis->selesai);
        $this->assertSame('PTPKM/INV/2026/001', $analisis->kod_rujukan);
        $this->assertSame('TLS', $analisis->data['protokol'][0]['nama']);
        $this->assertSame('Draf awal kesimpulan.', $analisis->data['kesimpulan_lain']);

        // Draf tidak lagi menjadi sumber pemulihan, tetapi sejarahnya kekal.
        $this->assertFalse(app(AnalisisDraftService::class)->adaDrafBelumSelesai($analisis->fresh()));
        $this->assertGreaterThan(0, AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)->count());
    }

    public function test_borang_memaparkan_semula_nilai_draf_bukan_nilai_rekod_tersimpan(): void
    {
        // Rekod tersimpan mempunyai kod rujukan lama.
        AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::ENTITI) + [
                'kod_rujukan' => 'KOD-LAMA',
                'user_id' => $this->analyst->id,
            ]
        );

        $this->actingAs($this->analyst)
            ->post(route('analisis.draf'), $this->borangSepara(['kod_rujukan' => 'KOD-DRAF-BAHARU']));

        $this->actingAs($this->analyst)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]))
            ->assertOk()
            ->assertSee('KOD-DRAF-BAHARU')
            ->assertDontSee('KOD-LAMA');
    }

    public function test_borang_tanpa_draf_memaparkan_notis_belum_ada_draf(): void
    {
        $this->actingAs($this->analyst)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]))
            ->assertOk()
            ->assertSee('Belum ada draf disimpan.')
            ->assertSee('Simpan Draf');
    }

    /*
    |--------------------------------------------------------------------------
    | Versi draf
    |--------------------------------------------------------------------------
    */

    public function test_setiap_simpanan_menaikkan_versi_draf(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara([
            'kod_rujukan' => 'PTPKM/INV/2026/002',
        ]));

        $analisis = AnalisisInventori::where('agency_code', self::ENTITI)->firstOrFail();

        $versi = AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)
            ->where('section_name', 'maklumat')
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $versi);
        $this->assertSame([1, 2], $versi->pluck('version')->all());

        // Hanya versi terbaharu yang menjadi sumber pemulihan.
        $this->assertFalse($versi->first()->is_current);
        $this->assertTrue($versi->last()->is_current);

        $borang = app(AnalisisDraftService::class)->borangDipulihkan($analisis);
        $this->assertSame('PTPKM/INV/2026/002', $borang['kod_rujukan']);
    }

    public function test_seksyen_yang_tidak_berubah_tidak_ditulis_semula(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $selepasPertama = AnalisDraftHistory::count();

        // Simpanan kedua dengan data yang sama persis.
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $this->assertSame($selepasPertama, AnalisDraftHistory::count());
    }

    public function test_hanya_seksyen_yang_berubah_ditulis_pada_simpanan_berikutnya(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $sebelum = AnalisDraftHistory::count();

        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara([
            'tindakan_lain' => 'Tindakan tambahan.',
        ]));

        // Satu seksyen sahaja berubah (tindakan).
        $this->assertSame($sebelum + 1, AnalisDraftHistory::count());
        $this->assertDatabaseHas('analisis_draft_history', [
            'section_name' => 'tindakan',
            'version' => 2,
            'is_current' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Keadaan seksyen
    |--------------------------------------------------------------------------
    */

    public function test_keadaan_seksyen_menunjukkan_seksyen_yang_telah_diisi(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara([
            'protokol' => [['nama' => 'TLS', 'versi' => '1.3', 'bilangan' => '2', 'nota' => '']],
        ]));

        $analisis = AnalisisInventori::where('agency_code', self::ENTITI)->firstOrFail();
        $ringkasan = app(AnalisisDraftService::class)->ringkasan($analisis);

        $this->assertTrue($ringkasan['ada_draf']);
        $this->assertSame(count(SeksyenAnalisis::SEKSYEN), $ringkasan['jumlah_seksyen']);
        $this->assertTrue($ringkasan['seksyen']['maklumat']['selesai']);
        $this->assertTrue($ringkasan['seksyen']['protokol']['selesai']);
        $this->assertFalse($ringkasan['seksyen']['vendor']['selesai']);
        $this->assertSame(2, $ringkasan['seksyen_selesai']);
    }

    public function test_kemajuan_seksyen_dipaparkan_pada_borang(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $this->actingAs($this->analyst)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]))
            ->assertOk()
            ->assertSee('1 / 9 seksyen diisi');
    }

    /*
    |--------------------------------------------------------------------------
    | Autosimpan (JSON) & perlindungan kehilangan data
    |--------------------------------------------------------------------------
    */

    public function test_autosimpan_json_menyimpan_draf(): void
    {
        $this->actingAs($this->analyst)
            ->postJson(route('analisis.draf'), $this->borangSepara())
            ->assertOk()
            ->assertJson(['berjaya' => true]);

        $this->assertDatabaseHas('analisis_draft_history', [
            'section_name' => 'maklumat',
            'is_current' => true,
        ]);
    }

    public function test_borang_memuatkan_perlindungan_kehilangan_data(): void
    {
        $this->actingAs($this->analyst)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]))
            ->assertOk()
            ->assertSee('beforeunload', false)
            ->assertSee('seksyen-semasa', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Kebenaran (Fasa 4 dikekalkan)
    |--------------------------------------------------------------------------
    */

    public function test_pegawai_lain_tidak_boleh_menyimpan_draf_entiti_tidak_ditugaskan(): void
    {
        $this->actingAs($this->analystLain)
            ->post(route('analisis.draf'), $this->borangSepara())
            ->assertForbidden();

        $this->assertDatabaseCount('analisis_draft_history', 0);
        $this->assertDatabaseCount('analisis_inventori', 0);
    }

    public function test_pegawai_lain_tidak_boleh_menyambung_draf_entiti_tidak_ditugaskan(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $this->actingAs($this->analystLain)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ENTITI]))
            ->assertForbidden();
    }

    public function test_penyelaras_tiada_kebenaran_menyimpan_draf(): void
    {
        // Gate manage-analysis sedia ada tidak berubah dalam fasa ini.
        $this->actingAs($this->coordinator)
            ->post(route('analisis.draf'), $this->borangSepara())
            ->assertForbidden();
    }

    public function test_tetamu_tidak_boleh_menyimpan_draf(): void
    {
        $this->post(route('analisis.draf'), $this->borangSepara())
            ->assertRedirect(route('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | Jejak audit
    |--------------------------------------------------------------------------
    */

    public function test_simpanan_draf_dicatat_untuk_jejak_audit(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $log = ActivityLog::where('agency_code', self::ENTITI)
            ->where('action', AnalisisDraftService::ACTION_DRAFT_CREATED)
            ->firstOrFail();

        $this->assertSame($this->analyst->id, $log->changed_by_user_id);
        $this->assertSame(1, $log->metadata['version']);
        $this->assertSame('Draf Dimulakan', $log->getActionLabel());

        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara([
            'kod_rujukan' => 'PTPKM/INV/2026/009',
        ]));

        $this->assertDatabaseHas('activity_log', [
            'agency_code' => self::ENTITI,
            'action' => AnalisisDraftService::ACTION_DRAFT_UPDATED,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Templat laporan tidak terjejas
    |--------------------------------------------------------------------------
    */

    public function test_laporan_masih_dijana_daripada_dapatan_muktamad(): void
    {
        $this->actingAs($this->analyst)->post(route('analisis.draf'), $this->borangSepara());

        $this->actingAs($this->analyst)->post(route('analisis.simpan'), $this->borangLengkap([
            'selesai' => '1',
        ]));

        $analisis = AnalisisInventori::where('agency_code', self::ENTITI)->firstOrFail();

        $this->actingAs($this->analyst)
            ->get(route('laporan.inventori', $analisis))
            ->assertOk()
            ->assertSee('PTPKM/INV/2026/001');
    }
}
