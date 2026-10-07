<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FASA 12 — ujian integrasi pengesahan pengguna (authentication).
 *
 * Pengesahan ialah pintu masuk kepada setiap modul pemantauan dan
 * pelaporan; ia diuji secara berasingan daripada kebenaran peranan
 * (authorization) yang diuji dalam Phase12AuthorizationMatrixTest.
 */
class Phase12AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengguna = User::factory()->create([
            'username' => 'pegawai.analisis',
            'password' => 'kata-laluan-benar',
            'role' => User::ROLE_COORDINATOR,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Halaman mendarat selepas log masuk
    |--------------------------------------------------------------------------
    | Peraturannya ialah gate `view-dashboard`, bukan senarai peranan:
    | sesiapa yang boleh membuka papan pemuka mendarat di situ, sesiapa yang
    | tidak mendarat pada Kemajuan Analisis Entiti.
    */

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function peranan(): array
    {
        return [
            'Pentadbir Sistem' => [User::ROLE_ADMINISTRATOR, 'dashboard'],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, 'dashboard'],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, 'dashboard'],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, 'dashboard'],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, 'dashboard'],
            'Pegawai Penyelaras Analisis' => [User::ROLE_COORDINATOR, 'dashboard'],

            // Satu-satunya peranan tanpa papan pemuka keseluruhan.
            'Pegawai Analisis' => [User::ROLE_ANALYST, 'workflow.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranan')]
    public function test_halaman_mendarat_mengikut_kebenaran_papan_pemuka(string $peranan, string $laluan): void
    {
        User::factory()->create([
            'username' => 'pegawai.mendarat',
            'password' => 'kata-laluan-benar',
            'role' => $peranan,
        ]);

        $this->post(route('login.attempt'), [
            'username' => 'pegawai.mendarat',
            'password' => 'kata-laluan-benar',
        ])->assertRedirect(route($laluan));

        $this->assertAuthenticated();
    }

    /**
     * Akar tapak ialah PENGALIH, bukan modul: ia menghantar setiap peranan ke
     * halaman mendarat yang boleh dibukanya, dan tidak pernah menolak dengan
     * 403.
     *
     * Regresi yang dilindungi: '/' dahulunya ialah papan pemuka itu sendiri,
     * jadi Pegawai Analisis — satu-satunya peranan tanpa gate `view-dashboard`
     * — menerima 403 apabila membuka alamat tapak. Itu perkara PERTAMA yang
     * dilakukan setiap pengguna.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('peranan')]
    public function test_akar_tapak_mengalihkan_setiap_peranan_ke_halaman_yang_boleh_dibuka(
        string $peranan,
        string $laluan,
    ): void {
        $pengguna = User::factory()->create(['role' => $peranan]);

        $this->actingAs($pengguna)
            ->get('/')
            ->assertRedirect(route($laluan));

        // Penegasan pengalihan hanya menyemak pengepala Location. Destinasi
        // mesti turut dibuktikan boleh dibuka, jika tidak 403 tidak terkesan.
        $this->actingAs($pengguna)
            ->get(route($laluan))
            ->assertOk();
    }

    /**
     * Tetamu di akar tapak dibawa ke log masuk, bukan ke mana-mana modul.
     */
    public function test_akar_tapak_membawa_tetamu_ke_log_masuk(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    /**
     * Halaman yang cuba dibuka sebelum log masuk kekal diutamakan — peraturan
     * halaman mendarat hanya terpakai apabila tiada halaman sedemikian.
     */
    public function test_halaman_yang_diminta_sebelum_log_masuk_kekal_diutamakan(): void
    {
        $pa = User::factory()->create([
            'username' => 'pegawai.pa',
            'password' => 'kata-laluan-benar',
            'role' => User::ROLE_ANALYST,
        ]);

        // Percubaan membuka halaman ini menyimpannya sebagai halaman diminta.
        $this->get(route('analisis.index'))->assertRedirect(route('login'));

        $this->post(route('login.attempt'), [
            'username' => 'pegawai.pa',
            'password' => 'kata-laluan-benar',
        ])->assertRedirect(route('analisis.index'));

        $this->assertAuthenticatedAs($pa);
    }

    public function test_halaman_log_masuk_dipaparkan_kepada_tetamu(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);
    }

    public function test_kelayakan_sah_membenarkan_masuk_ke_sistem(): void
    {
        $this->post(route('login.attempt'), [
            'username' => 'pegawai.analisis',
            'password' => 'kata-laluan-benar',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->pengguna);
    }

    public function test_kelayakan_tidak_sah_ditolak_tanpa_membocorkan_punca(): void
    {
        $this->from(route('login'))
            ->post(route('login.attempt'), [
                'username' => 'pegawai.analisis',
                'password' => 'kata-laluan-salah',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();

        // Mesej yang sama digunakan untuk nama pengguna tidak wujud —
        // sistem tidak mendedahkan sama ada akaun itu wujud.
        $this->from(route('login'))
            ->post(route('login.attempt'), [
                'username' => 'tiada-akaun-ini',
                'password' => 'kata-laluan-benar',
            ])
            ->assertSessionHasErrors(['username' => 'Nama pengguna atau kata laluan tidak sah.']);

        $this->assertGuest();
    }

    public function test_medan_log_masuk_wajib_diisi(): void
    {
        $this->from(route('login'))
            ->post(route('login.attempt'), [])
            ->assertSessionHasErrors(['username', 'password']);

        $this->assertGuest();
    }

    public function test_e_mel_bukan_kelayakan_log_masuk(): void
    {
        $this->post(route('login.attempt'), [
            'username' => $this->pengguna->email,
            'password' => 'kata-laluan-benar',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_sesi_dijana_semula_selepas_log_masuk(): void
    {
        $this->get(route('login'));
        $sebelum = session()->getId();

        $this->post(route('login.attempt'), [
            'username' => 'pegawai.analisis',
            'password' => 'kata-laluan-benar',
        ]);

        $this->assertNotSame($sebelum, session()->getId());
    }

    public function test_pengguna_dikembalikan_ke_halaman_yang_dituju_selepas_log_masuk(): void
    {
        $this->get(route('audit.index'))->assertRedirect(route('login'));

        $this->post(route('login.attempt'), [
            'username' => 'pegawai.analisis',
            'password' => 'kata-laluan-benar',
        ])->assertRedirect(route('audit.index'));
    }

    public function test_pengguna_yang_telah_log_masuk_tidak_melihat_borang_log_masuk(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_log_keluar_menamatkan_sesi(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_log_keluar_memerlukan_sesi_yang_sah(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_kata_laluan_disimpan_dalam_bentuk_cincangan(): void
    {
        $this->assertNotSame('kata-laluan-benar', $this->pengguna->password);
        $this->assertTrue(Hash::check('kata-laluan-benar', $this->pengguna->password));
    }

    public function test_kata_laluan_tidak_didedahkan_dalam_perwakilan_pengguna(): void
    {
        $tersiar = $this->pengguna->toArray();

        $this->assertArrayNotHasKey('password', $tersiar);
        $this->assertArrayNotHasKey('remember_token', $tersiar);
    }

    public function test_tetamu_dialihkan_ke_log_masuk_bagi_setiap_modul_utama(): void
    {
        foreach ([
            route('dashboard'),
            route('workflow.index'),
            route('analisis.index'),
            route('laporan.index'),
            route('status.index'),
            route('audit.index'),
            route('entiti.show', 'A010101'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
