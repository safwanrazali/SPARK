<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Profil sendiri — kemas kini nama, nama pengguna, emel dan kata laluan.
 *
 * Dua sifat yang paling penting diuji di sini ialah: kata laluan kosong
 * bermaksud "jangan tukar", dan peranan tidak boleh ditukar melalui borang
 * ini walaupun medannya diselitkan ke dalam permintaan.
 */
class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private const KATA_LALUAN_SAH = 'Rahsia#Kuat2026';

    private function pengguna(array $atribut = []): User
    {
        return User::factory()->create($atribut + [
            'name' => 'Nama Asal',
            'username' => 'nama.asal',
            'email' => 'asal@contoh.gov.my',
        ]);
    }

    /**
     * Muatan borang yang sah dan lengkap, supaya setiap ujian hanya perlu
     * menyatakan medan yang diubahnya.
     *
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function borang(array $ubah = []): array
    {
        return $ubah + [
            'name' => 'Nama Baharu',
            'username' => 'nama.baharu',
            'email' => 'baharu@contoh.gov.my',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Akses
    |--------------------------------------------------------------------------
    */

    public function test_tetamu_tidak_boleh_membuka_profil(): void
    {
        $this->get(route('profil.edit'))->assertRedirect(route('login'));
        $this->put(route('profil.update'), $this->borang())->assertRedirect(route('login'));
    }

    public function test_setiap_peranan_boleh_membuka_profilnya_sendiri(): void
    {
        foreach (User::roles() as $role) {
            $pengguna = User::factory()->create(['role' => $role]);

            $this->actingAs($pengguna)
                ->get(route('profil.edit'))
                ->assertOk()
                ->assertSee($pengguna->name)
                ->assertSee($pengguna->username)
                ->assertSee($pengguna->email)
                ->assertSee($pengguna->roleLabel());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Kemas kini butiran
    |--------------------------------------------------------------------------
    */

    public function test_pengguna_boleh_mengemas_kini_nama_nama_pengguna_dan_emel(): void
    {
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)
            ->put(route('profil.update'), $this->borang())
            ->assertRedirect(route('profil.edit'))
            ->assertSessionHas('success');

        $pengguna->refresh();

        $this->assertSame('Nama Baharu', $pengguna->name);
        $this->assertSame('nama.baharu', $pengguna->username);
        $this->assertSame('baharu@contoh.gov.my', $pengguna->email);
    }

    public function test_nama_pengguna_dan_emel_sendiri_boleh_dikekalkan(): void
    {
        $pengguna = $this->pengguna();

        // Peraturan unique mesti mengabaikan rekod pengguna itu sendiri.
        $this->actingAs($pengguna)
            ->put(route('profil.update'), [
                'name' => 'Nama Dikemaskini',
                'username' => 'nama.asal',
                'email' => 'asal@contoh.gov.my',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Dikemaskini', $pengguna->fresh()->name);
    }

    public function test_nama_pengguna_dan_emel_milik_orang_lain_ditolak(): void
    {
        $this->pengguna(['username' => 'sudah.wujud', 'email' => 'wujud@contoh.gov.my']);
        $pengguna = User::factory()->create();

        $this->actingAs($pengguna)
            ->put(route('profil.update'), $this->borang([
                'username' => 'sudah.wujud',
                'email' => 'wujud@contoh.gov.my',
            ]))
            ->assertSessionHasErrors(['username', 'email']);
    }

    public function test_medan_wajib_disahkan(): void
    {
        $this->actingAs($this->pengguna())
            ->put(route('profil.update'), ['name' => '', 'username' => '', 'email' => 'bukan-emel'])
            ->assertSessionHasErrors(['name', 'username', 'email']);
    }

    /*
    |--------------------------------------------------------------------------
    | Kata laluan
    |--------------------------------------------------------------------------
    */

    public function test_kata_laluan_kosong_mengekalkan_kata_laluan_sedia_ada(): void
    {
        $pengguna = $this->pengguna(['password' => Hash::make('KataLaluan#Lama1')]);
        $cincangAsal = $pengguna->password;

        $this->actingAs($pengguna)
            ->put(route('profil.update'), $this->borang([
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($cincangAsal, $pengguna->fresh()->password);
    }

    public function test_kata_laluan_boleh_ditukar(): void
    {
        $pengguna = $this->pengguna(['password' => Hash::make('KataLaluan#Lama1')]);

        $this->actingAs($pengguna)
            ->put(route('profil.update'), $this->borang([
                'password' => self::KATA_LALUAN_SAH,
                'password_confirmation' => self::KATA_LALUAN_SAH,
            ]))
            ->assertSessionHasNoErrors();

        // Disimpan sebagai cincangan, bukan teks biasa, dan boleh disemak.
        $baharu = $pengguna->fresh()->password;

        $this->assertNotSame(self::KATA_LALUAN_SAH, $baharu);
        $this->assertTrue(Hash::check(self::KATA_LALUAN_SAH, $baharu));
    }

    public function test_pengesahan_kata_laluan_yang_tidak_sepadan_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->put(route('profil.update'), $this->borang([
                'password' => self::KATA_LALUAN_SAH,
                'password_confirmation' => 'SesuatuYangLain#9',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_kata_laluan_lemah_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->put(route('profil.update'), $this->borang([
                'password' => 'lemah',
                'password_confirmation' => 'lemah',
            ]))
            ->assertSessionHasErrors('password');
    }

    /*
    |--------------------------------------------------------------------------
    | Had kuasa
    |--------------------------------------------------------------------------
    */

    public function test_peranan_tidak_boleh_ditukar_melalui_borang_profil(): void
    {
        $pengguna = $this->pengguna(['role' => User::ROLE_ANALYST]);

        $this->actingAs($pengguna)
            ->put(route('profil.update'), $this->borang([
                'role' => User::ROLE_ADMINISTRATOR,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(User::ROLE_ANALYST, $pengguna->fresh()->role);
    }

    public function test_pengguna_hanya_mengemas_kini_akaunnya_sendiri(): void
    {
        $lain = $this->pengguna(['username' => 'orang.lain', 'email' => 'lain@contoh.gov.my']);
        $pengguna = User::factory()->create(['name' => 'Saya']);

        // Tiada parameter pengguna pada route; `id` yang diselitkan diabaikan.
        $this->actingAs($pengguna)
            ->put(route('profil.update'), $this->borang(['id' => $lain->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Baharu', $pengguna->fresh()->name);
        $this->assertSame('Nama Asal', $lain->fresh()->name);
    }
}
