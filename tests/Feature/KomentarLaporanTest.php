<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalisisInventori;
use App\Models\LaporanKomentar;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\AliranKerja;
use App\Support\SeksyenAnalisis;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MelaluiAliranKerja;
use Tests\TestCase;

/**
 * Komentar KB/PPA pada Laporan Analisis Inventori Kriptografi.
 *
 * Ia mekanisme MAKLUM BALAS + PENGAKUAN, bukan kitaran kelulusan:
 *
 *     KB / PPA menulis komentar
 *       -> PA mengambil tindakan
 *       -> PA menanda "Tindakan Diambil"
 *       -> KB / PPA melihat tanda itu
 *
 * TIGA kebenaran yang mesti kekal berasingan diuji di sini:
 *
 *   PENGLIHATAN — PA, KB dan PPA melihat KESEMUA komentar KB/PPA. Pemilikan
 *                 TIDAK PERNAH menjadi ujian penglihatan.
 *   PEMILIKAN   — hanya pengarang boleh menyunting/memadam.
 *   TINDAKAN    — hanya PA boleh menanda "Tindakan Diambil".
 *
 * Setiap kebenaran diuji melalui laluan HTTP sebenar, bukan sekadar dengan
 * memeriksa butang pada paparan.
 */
class KomentarLaporanTest extends TestCase
{
    use MelaluiAliranKerja, RefreshDatabase;

    /** Entiti yang ditugaskan kepada $pa. */
    private const ALPHA = 'A010101';

    /** Entiti yang ditugaskan kepada $paLain — TIDAK boleh dicapai $pa. */
    private const BETA = 'A010102';

    private User $kb1;

    private User $kb2;

    private User $ppa1;

    private User $ppa2;

    private User $pa;

    private User $paLain;

    private User $ps;

    private User $tpii;

    private User $ppr;

    private User $pkd;

    private AnalisisInventori $analisis;

    private AnalisisInventori $analisisBeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kb1 = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN, 'name' => 'KB Satu']);
        $this->kb2 = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN, 'name' => 'KB Dua']);
        $this->ppa1 = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'PPA Satu']);
        $this->ppa2 = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'PPA Dua']);
        $this->pa = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'PA Ahmad']);
        $this->paLain = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'PA Beta']);
        $this->ps = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR, 'name' => 'Pentadbir']);
        $this->tpii = User::factory()->create(['role' => User::ROLE_TIMBALAN_PENGARAH_II, 'name' => 'TPII']);
        $this->ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD, 'name' => 'PPR']);
        $this->pkd = User::factory()->create(['role' => User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, 'name' => 'PKD']);

        $penugasan = app(EntityAssignmentService::class);
        $penugasan->assign(SektorDirectory::cariEntiti(self::ALPHA), $this->pa, $this->ppa1);
        $penugasan->assign(SektorDirectory::cariEntiti(self::BETA), $this->paLain, $this->ppa1);

        $this->analisis = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::ALPHA) + ['user_id' => $this->pa->id],
        );

        $this->analisisBeta = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::BETA) + ['user_id' => $this->paLain->id],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    private function komentar(User $penulis, string $teks, string $agencyCode = self::ALPHA, string $seksyen = 'profil'): LaporanKomentar
    {
        return LaporanKomentar::create([
            'agency_code' => $agencyCode,
            'agency_name' => SektorDirectory::cariEntiti($agencyCode)['agency_name'],
            'section' => $seksyen,
            'content' => $teks,
            'status' => LaporanKomentar::STATUS_TERBUKA,
            'user_id' => $penulis->id,
        ]);
    }

    private function bukaLaporan(User $pengguna, ?AnalisisInventori $analisis = null)
    {
        return $this->actingAs($pengguna)->get(route('laporan.inventori', $analisis ?? $this->analisis));
    }

    /*
    |--------------------------------------------------------------------------
    | PENGLIHATAN — KB/PPA melihat KESEMUA komentar KB/PPA
    |--------------------------------------------------------------------------
    | Pemilikan BUKAN penapis penglihatan. KB-1 melihat komentar KB-2, KB
    | melihat komentar PPA, dan sebaliknya.
    */

    public function test_pa_melihat_kesemua_komentar_kb_dan_ppa(): void
    {
        $this->komentar($this->kb1, 'Ulasan daripada KB Satu');
        $this->komentar($this->ppa1, 'Ulasan daripada PPA Satu');

        $this->bukaLaporan($this->pa)
            ->assertOk()
            ->assertSee('Ulasan daripada KB Satu')
            ->assertSee('Ulasan daripada PPA Satu');
    }

    public function test_kb_melihat_komentar_kb_lain_dan_komentar_ppa(): void
    {
        $this->komentar($this->kb1, 'Ulasan KB Satu');
        $this->komentar($this->kb2, 'Ulasan KB Dua');
        $this->komentar($this->ppa1, 'Ulasan PPA Satu');

        $this->bukaLaporan($this->kb1)
            ->assertOk()
            ->assertSee('Ulasan KB Satu')
            ->assertSee('Ulasan KB Dua')
            ->assertSee('Ulasan PPA Satu');
    }

    public function test_ppa_melihat_komentar_ppa_lain_dan_komentar_kb(): void
    {
        $this->komentar($this->ppa1, 'Ulasan PPA Satu');
        $this->komentar($this->ppa2, 'Ulasan PPA Dua');
        $this->komentar($this->kb1, 'Ulasan KB Satu');

        $this->bukaLaporan($this->ppa1)
            ->assertOk()
            ->assertSee('Ulasan PPA Satu')
            ->assertSee('Ulasan PPA Dua')
            ->assertSee('Ulasan KB Satu');
    }

    /**
     * PS, TPII, PPR dan PKD tiada akses kepada modul komentar walaupun
     * sebahagiannya boleh membuka laporan itu sendiri.
     */
    public function test_peranan_tanpa_akses_tidak_melihat_komentar(): void
    {
        $this->komentar($this->kb1, 'Rahsia maklum balas KB');

        foreach ([$this->ps, $this->tpii, $this->ppr, $this->pkd] as $pengguna) {
            $this->bukaLaporan($pengguna)->assertDontSee('Rahsia maklum balas KB');

            $this->assertFalse(
                Gate::forUser($pengguna)->allows('viewAny', LaporanKomentar::class),
                $pengguna->name.' tidak sepatutnya mempunyai akses modul komentar.',
            );
        }
    }

    public function test_peranan_tanpa_akses_tidak_boleh_menulis_komentar(): void
    {
        foreach ([$this->ps, $this->tpii, $this->ppr, $this->pkd, $this->pa] as $pengguna) {
            $this->actingAs($pengguna)
                ->post(route('laporan.komentar.store', $this->analisis), [
                    'section' => 'profil',
                    'content' => 'Cubaan menulis',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('laporan_komentar', 0);
    }

    public function test_kb_dan_ppa_boleh_menulis_komentar(): void
    {
        foreach ([$this->kb1, $this->ppa1] as $pengguna) {
            $this->actingAs($pengguna)
                ->post(route('laporan.komentar.store', $this->analisis), [
                    'section' => 'algoritma',
                    'content' => 'Sila semak semula maklumat pemilik sistem.',
                ])
                ->assertRedirect(route('laporan.inventori', $this->analisis));
        }

        $this->assertDatabaseCount('laporan_komentar', 2);
        $this->assertDatabaseHas('laporan_komentar', [
            'agency_code' => self::ALPHA,
            'section' => 'algoritma',
            'status' => LaporanKomentar::STATUS_TERBUKA,
            'user_id' => $this->kb1->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PEMILIKAN — sunting
    |--------------------------------------------------------------------------
    */

    public function test_pengarang_boleh_menyunting_komentarnya_sendiri(): void
    {
        foreach ([$this->kb1, $this->ppa1] as $pengarang) {
            $komentar = $this->komentar($pengarang, 'Teks asal');

            $this->actingAs($pengarang)
                ->patch(route('laporan.komentar.update', $komentar), ['content' => 'Teks disunting'])
                ->assertRedirect(route('laporan.inventori', $this->analisis));

            $this->assertSame('Teks disunting', $komentar->fresh()->content);
        }
    }

    public function test_komentar_tidak_boleh_disunting_pengguna_lain(): void
    {
        $milikKb1 = $this->komentar($this->kb1, 'Teks KB Satu');
        $milikPpa1 = $this->komentar($this->ppa1, 'Teks PPA Satu');

        $percubaan = [
            // [pengguna, komentar]
            [$this->kb2, $milikKb1],   // KB lain
            [$this->ppa1, $milikKb1],  // PPA menyunting komentar KB
            [$this->ppa2, $milikPpa1], // PPA lain
            [$this->kb1, $milikPpa1],  // KB menyunting komentar PPA
            [$this->pa, $milikKb1],    // PA tidak boleh menyunting langsung
            [$this->ps, $milikKb1],    // Pentadbir Sistem pun tidak
        ];

        foreach ($percubaan as [$pengguna, $komentar]) {
            $this->actingAs($pengguna)
                ->patch(route('laporan.komentar.update', $komentar), ['content' => 'Diubah tanpa hak'])
                ->assertForbidden();
        }

        $this->assertSame('Teks KB Satu', $milikKb1->fresh()->content);
        $this->assertSame('Teks PPA Satu', $milikPpa1->fresh()->content);
    }

    /*
    |--------------------------------------------------------------------------
    | PEMILIKAN — padam
    |--------------------------------------------------------------------------
    */

    public function test_pengarang_boleh_memadam_komentarnya_sendiri(): void
    {
        foreach ([$this->kb1, $this->ppa1] as $pengarang) {
            $komentar = $this->komentar($pengarang, 'Untuk dipadam');

            $this->actingAs($pengarang)
                ->delete(route('laporan.komentar.destroy', $komentar))
                ->assertRedirect(route('laporan.inventori', $this->analisis));

            // Pemadaman lembut: baris kekal untuk jejak audit.
            $this->assertSoftDeleted('laporan_komentar', ['id' => $komentar->id]);
        }
    }

    public function test_komentar_tidak_boleh_dipadam_pengguna_lain(): void
    {
        $milikKb1 = $this->komentar($this->kb1, 'Teks KB Satu');
        $milikPpa1 = $this->komentar($this->ppa1, 'Teks PPA Satu');

        $percubaan = [
            [$this->kb2, $milikKb1],   // KB memadam komentar KB lain
            [$this->ppa1, $milikKb1],  // PPA memadam komentar KB
            [$this->ppa2, $milikPpa1], // PPA memadam komentar PPA lain
            [$this->kb1, $milikPpa1],  // KB memadam komentar PPA
            [$this->pa, $milikKb1],    // PA memadam komentar KB
            [$this->pa, $milikPpa1],   // PA memadam komentar PPA
            [$this->ps, $milikKb1],    // Pentadbir Sistem
        ];

        foreach ($percubaan as [$pengguna, $komentar]) {
            $this->actingAs($pengguna)
                ->delete(route('laporan.komentar.destroy', $komentar))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('laporan_komentar', ['id' => $milikKb1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('laporan_komentar', ['id' => $milikPpa1->id, 'deleted_at' => null]);
    }

    /*
    |--------------------------------------------------------------------------
    | TINDAKAN DIAMBIL — PA sahaja
    |--------------------------------------------------------------------------
    */

    public function test_pa_menanda_tindakan_diambil_dan_identitinya_direkod(): void
    {
        $komentar = $this->komentar($this->kb1, 'Sila semak semula maklumat pemilik sistem.');

        $this->travelTo(now()->setDate(2026, 9, 3)->setTime(10, 32));

        $this->actingAs($this->pa)
            ->post(route('laporan.komentar.tindakan', $komentar))
            ->assertRedirect(route('laporan.inventori', $this->analisis));

        $segar = $komentar->fresh();

        $this->assertSame(LaporanKomentar::STATUS_TINDAKAN_DIAMBIL, $segar->status);
        $this->assertSame($this->pa->id, $segar->tindakan_oleh_user_id);
        $this->assertNotNull($segar->tindakan_pada);
        $this->assertSame('03/09/2026 10:32', $segar->tindakan_pada->format('d/m/Y H:i'));

        // Teks asal kekal seperti yang ditulis pengomen, dan komentar tidak
        // dipadam.
        $this->assertSame('Sila semak semula maklumat pemilik sistem.', $segar->content);
        $this->assertNull($segar->deleted_at);
    }

    public function test_kb_dan_ppa_tidak_boleh_menanda_tindakan_diambil(): void
    {
        $komentar = $this->komentar($this->kb1, 'Maklum balas');

        foreach ([$this->kb1, $this->kb2, $this->ppa1, $this->ppa2, $this->ps, $this->tpii] as $pengguna) {
            $this->actingAs($pengguna)
                ->post(route('laporan.komentar.tindakan', $komentar))
                ->assertForbidden();
        }

        $this->assertSame(LaporanKomentar::STATUS_TERBUKA, $komentar->fresh()->status);
    }

    public function test_kb_dan_ppa_melihat_bahawa_pa_telah_mengambil_tindakan(): void
    {
        $komentar = $this->komentar($this->kb1, 'Sila semak semula maklumat pemilik sistem.');

        $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $komentar));

        foreach ([$this->kb1, $this->kb2, $this->ppa1, $this->ppa2, $this->pa] as $pengguna) {
            $this->bukaLaporan($pengguna)
                ->assertOk()
                // Komentar asal kekal kelihatan selepas ditindak.
                ->assertSee('Sila semak semula maklumat pemilik sistem.')
                ->assertSee('Tindakan Diambil')
                ->assertSee('PA Ahmad');
        }
    }

    public function test_hanya_pa_boleh_membatalkan_tanda_tindakan_diambil(): void
    {
        $komentar = $this->komentar($this->kb1, 'Maklum balas');
        $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $komentar));

        foreach ([$this->kb1, $this->ppa1, $this->ps] as $pengguna) {
            $this->actingAs($pengguna)
                ->delete(route('laporan.komentar.tindakan.batal', $komentar))
                ->assertForbidden();
        }

        $this->assertTrue($komentar->fresh()->sudahDitindak());

        $this->actingAs($this->pa)
            ->delete(route('laporan.komentar.tindakan.batal', $komentar))
            ->assertRedirect(route('laporan.inventori', $this->analisis));

        $segar = $komentar->fresh();
        $this->assertSame(LaporanKomentar::STATUS_TERBUKA, $segar->status);
        $this->assertNull($segar->tindakan_oleh_user_id);
        $this->assertNull($segar->tindakan_pada);
    }

    /**
     * "Tindakan Diambil" ialah STATUS, bukan notifikasi. Tiada modul
     * notifikasi wujud dan tiada notifikasi dihantar.
     */
    public function test_tindakan_diambil_tidak_mencetuskan_sebarang_notifikasi(): void
    {
        Notification::fake();

        $komentar = $this->komentar($this->kb1, 'Maklum balas');

        $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $komentar));

        Notification::assertNothingSent();
        $this->assertFalse(Schema::hasTable('notifications'));
    }

    /*
    |--------------------------------------------------------------------------
    | PERINGKAT 3.1 — komentar tiada kesan langsung ke atasnya
    |--------------------------------------------------------------------------
    */

    public function test_komentar_terbuka_tidak_menyekat_peringkat_3_1_daripada_selesai(): void
    {
        $this->lengkapkanHingga(self::ALPHA, AliranKerja::ANALISIS_INVENTORI, $this->ppa1);

        // Lima terbuka, dua ditindak — peringkat 3.1 tetap boleh disiapkan.
        foreach (range(1, 5) as $i) {
            $this->komentar($this->kb1, 'Terbuka '.$i);
        }

        foreach (range(1, 2) as $i) {
            $ditindak = $this->komentar($this->ppa1, 'Ditindak '.$i);
            $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $ditindak));
        }

        $this->siapkanPeringkat(self::ALPHA, AliranKerja::ANALISIS_INVENTORI, $this->pa);

        $this->assertSame(
            WorkflowStageStatus::SELESAI,
            $this->status31(),
            'Komentar yang masih terbuka tidak boleh menyekat peringkat 3.1.',
        );
    }

    public function test_menanda_tindakan_diambil_tidak_mengubah_status_peringkat_3_1(): void
    {
        $this->lengkapkanHingga(self::ALPHA, AliranKerja::ANALISIS_INVENTORI, $this->ppa1);

        $sebelum = $this->status31();

        $komentar = $this->komentar($this->kb1, 'Maklum balas');
        $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $komentar));

        $this->assertSame($sebelum, $this->status31());
    }

    private function status31(): ?string
    {
        return WorkflowStageStatus::query()
            ->where('agency_code', self::ALPHA)
            ->atStage(AliranKerja::ANALISIS_INVENTORI)
            ->value('status');
    }

    /*
    |--------------------------------------------------------------------------
    | KESELAMATAN ENTITI — IDOR
    |--------------------------------------------------------------------------
    */

    public function test_pa_tidak_boleh_membuka_komentar_entiti_yang_tidak_ditugaskan(): void
    {
        $this->komentar($this->kb1, 'Maklum balas Beta', self::BETA);

        // $pa hanya ditugaskan ALPHA.
        $this->bukaLaporan($this->pa, $this->analisisBeta)->assertForbidden();
    }

    public function test_menukar_id_komentar_tidak_mendedahkan_komentar_entiti_lain(): void
    {
        $komentarBeta = $this->komentar($this->kb1, 'Maklum balas Beta', self::BETA);

        // $pa boleh menanda tindakan — tetapi bukan pada entiti Beta.
        $this->actingAs($this->pa)
            ->post(route('laporan.komentar.tindakan', $komentarBeta))
            ->assertForbidden();

        $this->assertSame(LaporanKomentar::STATUS_TERBUKA, $komentarBeta->fresh()->status);
    }

    public function test_agency_code_dalam_permintaan_diabaikan(): void
    {
        // Kod entiti diambil daripada rekod analisis pada laluan, BUKAN
        // daripada badan permintaan — menghantarnya tidak mengubah apa-apa.
        $this->actingAs($this->kb1)
            ->post(route('laporan.komentar.store', $this->analisis), [
                'section' => 'vendor',
                'content' => 'Cubaan menyuntik entiti lain',
                'agency_code' => self::BETA,
                'agency_name' => 'Entiti Beta',
                'user_id' => $this->ppa1->id,
                'status' => LaporanKomentar::STATUS_TINDAKAN_DIAMBIL,
            ])
            ->assertRedirect();

        $komentar = LaporanKomentar::firstOrFail();

        $this->assertSame(self::ALPHA, $komentar->agency_code);
        $this->assertSame($this->kb1->id, $komentar->user_id);
        $this->assertSame(LaporanKomentar::STATUS_TERBUKA, $komentar->status);
    }

    /*
    |--------------------------------------------------------------------------
    | PENAMBATAN SEKSYEN — sembilan seksyen Borang Input sahaja
    |--------------------------------------------------------------------------
    */

    public function test_setiap_seksyen_borang_diterima(): void
    {
        foreach (SeksyenAnalisis::kunci() as $seksyen) {
            $this->actingAs($this->kb1)
                ->post(route('laporan.komentar.store', $this->analisis), [
                    'section' => $seksyen,
                    'content' => 'Komentar bagi '.$seksyen,
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('laporan_komentar', count(SeksyenAnalisis::kunci()));
    }

    public function test_seksyen_sewenang_wenangnya_ditolak_di_pelayan(): void
    {
        // Termasuk kunci lama modul asal, yang bukan seksyen Borang Input.
        foreach (['pengenalan', 'kerangka_kerja', 'lampiran', 'seksyen-rekaan', ''] as $seksyen) {
            $this->actingAs($this->kb1)
                ->post(route('laporan.komentar.store', $this->analisis), [
                    'section' => $seksyen,
                    'content' => 'Komentar',
                ])
                ->assertSessionHasErrors('section');
        }

        $this->assertDatabaseCount('laporan_komentar', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | PENUNJUK — maklumat sahaja
    |--------------------------------------------------------------------------
    */

    public function test_penunjuk_mengira_kesemua_komentar_kb_dan_ppa(): void
    {
        $this->komentar($this->kb1, 'Satu', self::ALPHA, 'profil');
        $this->komentar($this->kb2, 'Dua', self::ALPHA, 'profil');
        $ditindak = $this->komentar($this->ppa1, 'Tiga', self::ALPHA, 'profil');

        $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $ditindak));

        // Kiraan sama bagi PA, KB dan PPA kerana ketiga-tiganya melihat set
        // komentar yang SAMA.
        foreach ([$this->pa, $this->kb1, $this->ppa2] as $pengguna) {
            $this->bukaLaporan($pengguna)
                ->assertOk()
                ->assertSee('3 komentar')
                ->assertSee('2 terbuka')
                ->assertSee('1 tindakan diambil');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PDF — komentar TIDAK PERNAH disertakan
    |--------------------------------------------------------------------------
    */

    public function test_komentar_tidak_muncul_dalam_kandungan_pdf(): void
    {
        $komentar = $this->komentar($this->kb1, 'Maklum balas sulit KB Satu');
        $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $komentar));

        $html = $this->htmlPdf();

        foreach ([
            'Maklum balas sulit KB Satu', // teks komentar
            'KB Satu',                    // pengarang
            'Tindakan Diambil',           // status
            'PA Ahmad',                   // PA yang mengambil tindakan
            'section-comments-widget',    // antara muka komentar
        ] as $tidakBoleh) {
            $this->assertStringNotContainsString($tidakBoleh, $html);
        }
    }

    public function test_data_paparan_pdf_tidak_memuatkan_komentar(): void
    {
        $this->komentar($this->kb1, 'Maklum balas sulit KB Satu');

        $this->assertTrue($this->dataLaporan(includeComments: false)['komentar']->isEmpty());
        $this->assertTrue($this->dataLaporan(includeComments: true)['komentar']->isNotEmpty());
    }

    /**
     * Badan PDF dirender terus daripada paparannya supaya ujian ini tidak
     * memerlukan Chrome. Ia HTML yang SAMA yang diserahkan kepada Browsershot
     * oleh LaporanController@unduh.
     */
    private function htmlPdf(): string
    {
        return view('laporan.pdf.body', $this->dataLaporan(includeComments: false))->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataLaporan(bool $includeComments): array
    {
        $controller = app(\App\Http\Controllers\LaporanController::class);

        $siapkanData = new \ReflectionMethod($controller, 'siapkanData');

        return $siapkanData->invoke($controller, $this->analisis, $includeComments);
    }

    /*
    |--------------------------------------------------------------------------
    | JEJAK AUDIT
    |--------------------------------------------------------------------------
    */

    public function test_tindakan_komentar_direkod_dalam_jejak_audit_tanpa_teks_komentar(): void
    {
        $rahsia = 'Teks komentar yang tidak boleh masuk jejak audit';

        $this->actingAs($this->kb1)->post(route('laporan.komentar.store', $this->analisis), [
            'section' => 'protokol',
            'content' => $rahsia,
        ]);

        $komentar = LaporanKomentar::firstOrFail();

        $this->actingAs($this->kb1)->patch(route('laporan.komentar.update', $komentar), ['content' => $rahsia.' (disunting)']);
        $this->actingAs($this->pa)->post(route('laporan.komentar.tindakan', $komentar));
        $this->actingAs($this->pa)->delete(route('laporan.komentar.tindakan.batal', $komentar));
        $this->actingAs($this->kb1)->delete(route('laporan.komentar.destroy', $komentar));

        $log = ActivityLog::where('agency_code', self::ALPHA)->pluck('action')->all();

        foreach ([
            'comment_created',
            'comment_updated',
            'comment_action_taken',
            'comment_reopened',
            'comment_deleted',
        ] as $action) {
            $this->assertContains($action, $log);
        }

        // Jejak audit merekod PERUBAHAN, bukan KANDUNGAN.
        $semua = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString($rahsia, $semua);

        $rekod = ActivityLog::where('action', 'comment_action_taken')->firstOrFail();
        $this->assertSame($this->pa->id, $rekod->changed_by_user_id);
        $this->assertSame('protokol', $rekod->metadata['seksyen']);
        $this->assertSame($this->kb1->id, $rekod->metadata['pengarang_user_id']);
        $this->assertSame('Terbuka', $rekod->old_value);
        $this->assertSame('Tindakan Diambil', $rekod->new_value);
    }

    /*
    |--------------------------------------------------------------------------
    | ISTILAH — status komentar berasingan daripada status peringkat 3.1
    |--------------------------------------------------------------------------
    */

    public function test_status_komentar_menggunakan_terbuka_dan_tindakan_diambil(): void
    {
        $this->assertSame(
            ['terbuka' => 'Terbuka', 'tindakan_diambil' => 'Tindakan Diambil'],
            LaporanKomentar::STATUS,
        );

        // "Selesai" ialah istilah peringkat 3.1 dan tidak boleh dipakai
        // semula sebagai status komentar.
        $this->assertNotContains(WorkflowStageStatus::SELESAI, LaporanKomentar::STATUS);
    }

    public function test_seksyen_komentar_ialah_sembilan_seksyen_borang_input(): void
    {
        $this->assertSame(
            ['maklumat', 'data_status', 'profil', 'algoritma', 'protokol', 'pustaka', 'vendor', 'tindakan', 'kesimpulan'],
            array_keys(LaporanKomentar::seksyenLaporan()),
        );
    }
}
