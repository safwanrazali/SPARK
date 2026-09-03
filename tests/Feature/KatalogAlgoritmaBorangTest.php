<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\User;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Katalog AKSA MySEAL 2.1 dalam Borang Input dan Laporan.
 *
 * Fokus ujian ini ialah kesan katalog terhadap DATA SEDIA ADA. Kunci
 * tersimpan kekal "Kategori|Algoritma"; menambah pengelasan MySEAL dan
 * parameter TIDAK boleh menyentuh kunci itu, kerana setiap rekod Borang
 * Input sedia ada bergantung padanya.
 */
class KatalogAlgoritmaBorangTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 'A010101';

    private User $pa;

    private array $entiti;

    protected function setUp(): void
    {
        parent::setUp();

        $ppa = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $this->pa = User::factory()->create(['role' => User::ROLE_ANALYST]);

        $this->entiti = SektorDirectory::cariEntiti(self::ALPHA);

        app(EntityAssignmentService::class)->assign($this->entiti, $this->pa, $ppa);
    }

    private function bukaBorang()
    {
        return $this->actingAs($this->pa)->get(route('analisis.borang', [
            'sector_code' => $this->entiti['sector_code'],
            'agency_code' => self::ALPHA,
        ]));
    }

    /**
     * @param  array<string, array<string, string>>  $algoritma  kunci penuh => nilai
     */
    private function rekod(array $algoritma, array $lain = []): AnalisisInventori
    {
        return AnalisisInventori::factory()->create($this->entiti + [
            'user_id' => $this->pa->id,
            'data' => array_merge(
                AnalisisInventori::factory()->definition()['data'],
                ['algoritma' => $algoritma, 'algoritma_lain' => $lain],
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Borang Input
    |--------------------------------------------------------------------------
    */

    public function test_borang_memaparkan_algoritma_ketiga_tiga_kategori_myseal(): void
    {
        $this->bukaBorang()
            ->assertOk()
            // Approved
            ->assertSee('AES')
            ->assertSee('ML-KEM')
            // Neutral
            ->assertSee('SHA-256')
            ->assertSee('Brakerski-Fan-Vercauteren (BFV) Encryption')
            // Monitored
            ->assertSee('SKIPJACK Decryption')
            ->assertSee('HMAC Verification')
            ->assertSee('SHA-1');
    }

    public function test_borang_memaparkan_primitif_baharu_bagi_algoritma_monitored(): void
    {
        $this->bukaBorang()
            ->assertOk()
            ->assertSee('Algoritma Simetri')
            ->assertSee('Pengesahan Tandatangan Digital');
    }

    public function test_borang_melencanakan_pengelasan_myseal_bukan_approved(): void
    {
        $html = $this->bukaBorang()->assertOk()->getContent();

        // Neutral dan Monitored dilencanakan; Approved ialah lalai senyap.
        $this->assertStringContainsString('Neutral', $html);
        $this->assertStringContainsString('Monitored', $html);
        $this->assertStringContainsString('Pengelasan AKSA MySEAL 2.1', $html);
    }

    public function test_borang_memaparkan_panjang_kunci_dan_set_parameter_rasmi(): void
    {
        $this->bukaBorang()
            ->assertOk()
            ->assertSee('Panjang kunci: 128, 192, 256')
            ->assertSee('Panjang cerna: 384, 512, 512/224, 512/256')
            ->assertSee('Set parameter: 512, 768, 1024')
            ->assertSee('Varian: 44, 65, 87');
    }

    /*
    |--------------------------------------------------------------------------
    | Data sedia ada
    |--------------------------------------------------------------------------
    */

    public function test_pilihan_sedia_ada_kekal_ditanda_dalam_borang(): void
    {
        $this->rekod(['Sifer Blok|AES' => ['bilangan' => '12']]);

        $html = $this->bukaBorang()->assertOk()->getContent();

        $k = md5('Sifer Blok|AES');

        $this->assertMatchesRegularExpression(
            '/id="algo-'.$k.'"[^>]*checked/',
            $html,
            'Pilihan algoritma sedia ada tidak lagi ditanda selepas katalog dikemas kini.',
        );
        $this->assertStringContainsString('value="12"', $html);
    }

    /**
     * Kunci yang TIADA dalam katalog (rekod sejarah daripada katalog lama)
     * tidak boleh meruntuhkan borang mahupun laporan.
     */
    public function test_kunci_sejarah_di_luar_katalog_tidak_meruntuhkan_borang_atau_laporan(): void
    {
        $analisis = $this->rekod([
            'Sifer Blok|AES' => ['bilangan' => '3'],
            'Katalog Lama|Algoritma Tidak Dikenali' => ['bilangan' => '9'],
        ]);

        $this->bukaBorang()->assertOk();

        $this->actingAs($this->pa)
            ->get(route('laporan.inventori', $analisis))
            ->assertOk()
            ->assertSee('AES');
    }

    public function test_penyimpanan_kunci_kekal_dua_bahagian(): void
    {
        $analisis = $this->rekod([
            'Fungsi Cincang Kriptografi|SHA-256' => ['bilangan' => '4'],
            'Pengesahan Tandatangan Digital|RSA' => ['bilangan' => '2'],
        ]);

        foreach (array_keys($analisis->fresh()->data['algoritma']) as $kunci) {
            $this->assertCount(
                2,
                explode('|', $kunci),
                "Kunci tersimpan mesti kekal dua bahagian: {$kunci}",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Laporan
    |--------------------------------------------------------------------------
    */

    public function test_laporan_memaparkan_algoritma_approved_seperti_sebelum_ini(): void
    {
        $analisis = $this->rekod(['Sifer Blok|AES' => ['bilangan' => '12']]);

        $this->actingAs($this->pa)
            ->get(route('laporan.inventori', $analisis))
            ->assertOk()
            ->assertSee('Sifer Blok')
            ->assertSee('AES');
    }

    public function test_laporan_memaparkan_algoritma_neutral_dan_monitored_yang_dipilih(): void
    {
        $analisis = $this->rekod([
            'Fungsi Cincang Kriptografi|SHA-256' => ['bilangan' => '4'],
            'Algoritma Simetri|SKIPJACK Decryption' => ['bilangan' => '1'],
        ]);

        $this->actingAs($this->pa)
            ->get(route('laporan.inventori', $analisis))
            ->assertOk()
            ->assertSee('SHA-256')
            ->assertSee('Algoritma Simetri')
            ->assertSee('SKIPJACK Decryption');
    }

    /**
     * Laporan menyenaraikan nama dan bilangan sahaja. Pengelasan MySEAL ialah
     * maklumat borang, bukan medan laporan rasmi — ia tidak boleh menyelinap
     * masuk melalui katalog yang dikemas kini.
     */
    public function test_laporan_tidak_menambah_lajur_pengelasan_myseal(): void
    {
        $analisis = $this->rekod(['Fungsi Cincang Kriptografi|SHA-256' => ['bilangan' => '4']]);

        $this->actingAs($this->pa)
            ->get(route('laporan.inventori', $analisis))
            ->assertOk()
            ->assertDontSee('Pengelasan AKSA MySEAL 2.1')
            ->assertDontSee('Panjang cerna:');
    }

    /*
    |--------------------------------------------------------------------------
    | Penanda risiko sedia ada
    |--------------------------------------------------------------------------
    */

    public function test_algoritma_lapuk_dan_berisiko_kuantum_kekal_dikesan(): void
    {
        // MD5 tiada pada mana-mana laman AKSA MySEAL, jadi ia kekal direkodkan
        // melalui medan "Lain-lain".
        $analisis = $this->rekod(
            ['Pengesahan Tandatangan Digital|RSA' => ['bilangan' => '1']],
            [['nama' => 'MD5', 'bilangan' => '2']],
        );

        $this->assertSame(['MD5'], $analisis->algoritmaLapuk());

        // RSA kini boleh ditanda terus daripada katalog Monitored; padanan
        // mengambil bahagian kedua kunci, jadi ia dikesan seperti biasa.
        $this->assertSame(['RSA'], $analisis->algoritmaKuantum());
    }

    public function test_sha1_kini_dikesan_daripada_katalog_bukan_hanya_medan_lain_lain(): void
    {
        $analisis = $this->rekod(['Fungsi Cincang Kriptografi|SHA-1' => ['bilangan' => '1']]);

        $this->assertSame(['SHA-1'], $analisis->algoritmaLapuk());
    }
}
