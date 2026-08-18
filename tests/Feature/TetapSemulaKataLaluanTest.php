<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pentadbir menetapkan semula kata laluan pengguna atas permintaan.
 *
 * Tetapan semula mesti melakukan ketiga-tiga perkara sekaligus: menggantikan
 * kata laluan, memaksa penukaran pada log masuk berikutnya, dan menamatkan
 * sesi aktif akaun tersebut. Melangkau mana-mana satu meninggalkan akaun
 * dalam keadaan yang masih boleh diakses oleh pemegang kata laluan lama.
 */
class TetapSemulaKataLaluanTest extends TestCase
{
    use RefreshDatabase;

    private const LAMA = 'KataLaluanLama#1';

    private function pentadbir(): User
    {
        return User::factory()->create(['roles' => [User::ROLE_ADMINISTRATOR]]);
    }

    private function sasaran(): User
    {
        return User::factory()->create([
            'password' => Hash::make(self::LAMA),
            'must_change_password' => false,
        ]);
    }

    private function tetapSemula(User $oleh, User $sasaran)
    {
        return $this->actingAs($oleh)
            ->post(route('administration.users.tetap-semula-kata-laluan', $sasaran));
    }

    /*
    |--------------------------------------------------------------------------
    | Kesan tetapan semula
    |--------------------------------------------------------------------------
    */

    public function test_kata_laluan_lama_tidak_lagi_sah(): void
    {
        $sasaran = $this->sasaran();

        $this->tetapSemula($this->pentadbir(), $sasaran)->assertRedirect();

        $this->assertFalse(Hash::check(self::LAMA, $sasaran->fresh()->password));
    }

    public function test_kata_laluan_sementara_dipaparkan_sekali_dan_boleh_digunakan(): void
    {
        $sasaran = $this->sasaran();

        $respons = $this->tetapSemula($this->pentadbir(), $sasaran)
            ->assertSessionHas('kata_laluan_sementara');

        $kelayakan = $respons->getSession()->get('kata_laluan_sementara');

        $this->assertSame($sasaran->username, $kelayakan['username']);
        $this->assertTrue(Hash::check($kelayakan['kata_laluan'], $sasaran->fresh()->password));

        // Kelayakan yang dipapar benar-benar boleh log masuk. Route log masuk
        // berada dalam kumpulan `guest`, jadi sesi ujian dilepaskan dahulu.
        auth()->logout();

        $this->post(route('login.attempt'), [
            'username' => $sasaran->username,
            'password' => $kelayakan['kata_laluan'],
        ]);

        $this->assertAuthenticatedAs($sasaran);
    }

    public function test_kata_laluan_sementara_cukup_kuat(): void
    {
        $sasaran = $this->sasaran();

        $respons = $this->tetapSemula($this->pentadbir(), $sasaran);
        $kata = $respons->getSession()->get('kata_laluan_sementara')['kata_laluan'];

        $this->assertGreaterThanOrEqual(16, strlen($kata));
        $this->assertMatchesRegularExpression('/[a-z]/', $kata);
        $this->assertMatchesRegularExpression('/[A-Z]/', $kata);
        $this->assertMatchesRegularExpression('/[0-9]/', $kata);
    }

    public function test_setiap_tetapan_semula_menjana_kata_laluan_berbeza(): void
    {
        $pentadbir = $this->pentadbir();
        $sasaran = $this->sasaran();

        $satu = $this->tetapSemula($pentadbir, $sasaran)
            ->getSession()->get('kata_laluan_sementara')['kata_laluan'];
        $dua = $this->tetapSemula($pentadbir, $sasaran)
            ->getSession()->get('kata_laluan_sementara')['kata_laluan'];

        $this->assertNotSame($satu, $dua);
    }

    public function test_pengguna_dipaksa_menukar_pada_log_masuk_berikutnya(): void
    {
        $sasaran = $this->sasaran();

        $this->tetapSemula($this->pentadbir(), $sasaran);

        $this->assertTrue($sasaran->fresh()->must_change_password);

        // Dan kuncian itu benar-benar berkuat kuasa.
        $this->actingAs($sasaran->fresh())
            ->get(route('profil.edit'))
            ->assertRedirect(route('kata-laluan.tukar'));
    }

    public function test_sesi_aktif_pengguna_ditamatkan(): void
    {
        // Suite ujian menggunakan pemacu `array`; pengeluaran menggunakan
        // `database`, iaitu keadaan yang perlu diuji di sini.
        config(['session.driver' => 'database']);

        $sasaran = $this->sasaran();

        // Sesi yang seolah-olah masih hidup milik akaun tersebut.
        DB::table('sessions')->insert([
            'id' => 'sesi-sasaran',
            'user_id' => $sasaran->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'ujian',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $pentadbir = $this->pentadbir();

        DB::table('sessions')->insert([
            'id' => 'sesi-pentadbir',
            'user_id' => $pentadbir->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'ujian',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->tetapSemula($pentadbir, $sasaran);

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-sasaran']);
        // Sesi orang lain tidak boleh terjejas.
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-pentadbir']);
    }

    public function test_pemacu_sesi_bukan_pangkalan_data_tidak_menyebabkan_ralat(): void
    {
        config(['session.driver' => 'file']);

        $sasaran = $this->sasaran();

        // Tiada jadual sesi untuk dibersihkan; tetapan semula tetap berjaya.
        $this->tetapSemula($this->pentadbir(), $sasaran)
            ->assertSessionHas('kata_laluan_sementara');

        $this->assertTrue($sasaran->fresh()->must_change_password);
    }

    /*
    |--------------------------------------------------------------------------
    | Kawalan akses
    |--------------------------------------------------------------------------
    */

    public function test_hanya_pentadbir_boleh_menetapkan_semula(): void
    {
        $sasaran = $this->sasaran();

        foreach ([User::ROLE_COORDINATOR, User::ROLE_ANALYST, User::ROLE_KETUA_BAHAGIAN] as $peranan) {
            $this->tetapSemula(User::factory()->create(['roles' => [$peranan]]), $sasaran)
                ->assertForbidden();
        }

        $this->assertTrue(Hash::check(self::LAMA, $sasaran->fresh()->password));
    }

    public function test_tetamu_tidak_boleh_menetapkan_semula(): void
    {
        $sasaran = $this->sasaran();

        $this->post(route('administration.users.tetap-semula-kata-laluan', $sasaran))
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::LAMA, $sasaran->fresh()->password));
    }

    public function test_pentadbir_tidak_boleh_menetapkan_semula_akaunnya_sendiri(): void
    {
        // Ia hanya akan mengunci dirinya ke skrin tukar kata laluan;
        // Profil Saya ialah laluan yang betul.
        $pentadbir = User::factory()->create([
            'roles' => [User::ROLE_ADMINISTRATOR],
            'password' => Hash::make(self::LAMA),
        ]);

        $this->tetapSemula($pentadbir, $pentadbir)
            ->assertSessionHasErrors('user');

        $pentadbir->refresh();

        $this->assertTrue(Hash::check(self::LAMA, $pentadbir->password));
        $this->assertFalse($pentadbir->must_change_password);
    }

    /*
    |--------------------------------------------------------------------------
    | Antara muka
    |--------------------------------------------------------------------------
    */

    public function test_butang_tetap_semula_dipapar_untuk_pengguna_lain_sahaja(): void
    {
        $pentadbir = $this->pentadbir();
        $sasaran = $this->sasaran();

        $this->actingAs($pentadbir)
            ->get(route('administration.users.index'))
            ->assertOk()
            ->assertSee(route('administration.users.tetap-semula-kata-laluan', $sasaran), false)
            ->assertDontSee(route('administration.users.tetap-semula-kata-laluan', $pentadbir), false);
    }

    public function test_kelayakan_sementara_dipapar_pada_senarai_pengguna(): void
    {
        $sasaran = $this->sasaran();

        $this->tetapSemula($this->pentadbir(), $sasaran);

        $kelayakan = session('kata_laluan_sementara');

        $this->actingAs($this->pentadbir())
            ->get(route('administration.users.index'))
            ->assertOk()
            ->assertSee($kelayakan['kata_laluan'])
            ->assertSee($sasaran->username);
    }
}
