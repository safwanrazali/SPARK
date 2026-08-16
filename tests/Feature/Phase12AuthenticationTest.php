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
        ])->assertRedirect('/');

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
            ->assertRedirect('/');
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
            route('penugasan.index'),
            route('analisis.index'),
            route('laporan.index'),
            route('status.index'),
            route('audit.index'),
            route('muat-naik.history'),
            route('entiti.show', 'A010101'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
