<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kata laluan sementara wajib ditukar pada log masuk pertama.
 *
 * Sifat yang dilindungi: akaun yang dikeluarkan kata laluan oleh pentadbir
 * tidak boleh menggunakan mana-mana bahagian sistem sehingga kata laluan itu
 * diganti — dan tidak boleh terperangkap tanpa jalan keluar.
 */
class WajibTukarKataLaluanTest extends TestCase
{
    use RefreshDatabase;

    private const SEMENTARA = 'Sementara#2026x';

    private const PILIHAN_SENDIRI = 'PilihanSaya#2026';

    private function pentadbir(): User
    {
        return User::factory()->create(['roles' => [User::ROLE_ADMINISTRATOR]]);
    }

    private function penggunaSementara(): User
    {
        return User::factory()->create([
            'password' => Hash::make(self::SEMENTARA),
            'must_change_password' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Penandaan
    |--------------------------------------------------------------------------
    */

    public function test_pengguna_baharu_ditanda_wajib_tukar_kata_laluan(): void
    {
        $this->actingAs($this->pentadbir())
            ->post(route('administration.users.store'), [
                'name' => 'Pegawai Baharu',
                'username' => 'pegawai.baharu',
                'email' => 'baharu@contoh.gov.my',
                'roles' => [User::ROLE_ANALYST],
                'password' => self::SEMENTARA,
                'password_confirmation' => self::SEMENTARA,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            User::where('username', 'pegawai.baharu')->sole()->must_change_password,
        );
    }

    public function test_tetapan_semula_oleh_pentadbir_juga_ditanda(): void
    {
        $sasaran = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($this->pentadbir())
            ->put(route('administration.users.update', $sasaran), [
                'name' => $sasaran->name,
                'username' => $sasaran->username,
                'email' => $sasaran->email,
                'roles' => [User::ROLE_ANALYST],
                'password' => self::SEMENTARA,
                'password_confirmation' => self::SEMENTARA,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($sasaran->fresh()->must_change_password);
    }

    public function test_kemas_kini_tanpa_kata_laluan_tidak_menanda_semula(): void
    {
        $sasaran = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($this->pentadbir())
            ->put(route('administration.users.update', $sasaran), [
                'name' => 'Nama Dikemaskini',
                'username' => $sasaran->username,
                'email' => $sasaran->email,
                'roles' => [User::ROLE_ANALYST],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($sasaran->fresh()->must_change_password);
    }

    public function test_akaun_sedia_ada_tidak_dipaksa_menukar(): void
    {
        // Naik taraf tidak boleh memaksa seluruh organisasi menukar kata laluan.
        $this->assertFalse(User::factory()->create()->must_change_password);
    }

    /*
    |--------------------------------------------------------------------------
    | Kuncian
    |--------------------------------------------------------------------------
    */

    public function test_pengguna_sementara_dialihkan_daripada_setiap_halaman(): void
    {
        $pengguna = $this->penggunaSementara();

        foreach ([
            route('dashboard'),
            route('profil.edit'),
            route('workflow.index'),
            route('laporan.index'),
            route('analisis.index'),
        ] as $url) {
            $this->actingAs($pengguna)
                ->get($url)
                ->assertRedirect(route('kata-laluan.tukar'));
        }
    }

    public function test_pengguna_sementara_tidak_boleh_menulis_data(): void
    {
        $pengguna = $this->penggunaSementara();

        // Kuncian mesti merangkumi permintaan tulis, bukan paparan sahaja.
        $this->actingAs($pengguna)
            ->put(route('profil.update'), [
                'name' => 'Cuba Tukar',
                'username' => $pengguna->username,
                'email' => $pengguna->email,
            ])
            ->assertRedirect(route('kata-laluan.tukar'));

        $this->assertSame($pengguna->name, $pengguna->fresh()->name);
    }

    public function test_skrin_tukar_dan_log_keluar_kekal_boleh_dicapai(): void
    {
        // Tanpa pengecualian ini pengguna terperangkap dalam gelung alihan.
        $pengguna = $this->penggunaSementara();

        $this->actingAs($pengguna)->get(route('kata-laluan.tukar'))->assertOk();

        $this->actingAs($pengguna)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pengguna_biasa_tidak_terjejas(): void
    {
        $pengguna = User::factory()->create([
            'roles' => [User::ROLE_ADMINISTRATOR],
            'must_change_password' => false,
        ]);

        $this->actingAs($pengguna)->get(route('dashboard'))->assertOk();

        // Skrin tukar tiada guna bagi mereka.
        $this->actingAs($pengguna)
            ->get(route('kata-laluan.tukar'))
            ->assertRedirect(route('dashboard'));
    }

    /*
    |--------------------------------------------------------------------------
    | Penukaran
    |--------------------------------------------------------------------------
    */

    public function test_kata_laluan_baharu_membuka_kunci_sistem(): void
    {
        $pengguna = $this->penggunaSementara();

        $this->actingAs($pengguna)
            ->put(route('kata-laluan.simpan'), [
                'password' => self::PILIHAN_SENDIRI,
                'password_confirmation' => self::PILIHAN_SENDIRI,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $pengguna->refresh();

        $this->assertFalse($pengguna->must_change_password);
        $this->assertTrue(Hash::check(self::PILIHAN_SENDIRI, $pengguna->password));

        // Sistem kini terbuka.
        $this->actingAs($pengguna)->get(route('profil.edit'))->assertOk();
    }

    public function test_kata_laluan_sementara_tidak_boleh_dikitar_semula(): void
    {
        $pengguna = $this->penggunaSementara();

        $this->actingAs($pengguna)
            ->put(route('kata-laluan.simpan'), [
                'password' => self::SEMENTARA,
                'password_confirmation' => self::SEMENTARA,
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($pengguna->fresh()->must_change_password);
    }

    public function test_kata_laluan_lemah_atau_tidak_sepadan_ditolak(): void
    {
        $pengguna = $this->penggunaSementara();

        $this->actingAs($pengguna)
            ->put(route('kata-laluan.simpan'), [
                'password' => 'lemah',
                'password_confirmation' => 'lemah',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($pengguna)
            ->put(route('kata-laluan.simpan'), [
                'password' => self::PILIHAN_SENDIRI,
                'password_confirmation' => 'SesuatuLain#2026',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($pengguna->fresh()->must_change_password);
    }

    public function test_menukar_melalui_profil_turut_melepaskan_tanda(): void
    {
        // Jalan kedua yang menukar kata laluan mesti melepaskan tanda juga,
        // jika tidak pengguna dikunci walaupun kata laluannya sudah sendiri.
        $pengguna = User::factory()->create(['must_change_password' => true]);
        $pengguna->forceFill(['must_change_password' => false])->save();

        $this->actingAs($pengguna)
            ->put(route('profil.update'), [
                'name' => $pengguna->name,
                'username' => $pengguna->username,
                'email' => $pengguna->email,
                'password' => self::PILIHAN_SENDIRI,
                'password_confirmation' => self::PILIHAN_SENDIRI,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($pengguna->fresh()->must_change_password);
    }

    public function test_borang_menghantar_kaedah_yang_sepadan_dengan_route(): void
    {
        // Route ialah PUT; tanpa medan _method borang menghantar POST dan
        // pengguna menerima 405 dan bukan skrin ralat pengesahan.
        $this->actingAs($this->penggunaSementara())
            ->get(route('kata-laluan.tukar'))
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee(route('kata-laluan.simpan'), false);
    }

    public function test_tetamu_tidak_boleh_membuka_skrin_tukar(): void
    {
        $this->get(route('kata-laluan.tukar'))->assertRedirect(route('login'));
        $this->put(route('kata-laluan.simpan'), [])->assertRedirect(route('login'));
    }
}
