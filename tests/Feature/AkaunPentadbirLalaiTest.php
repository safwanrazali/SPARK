<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Akaun pentadbir lalai.
 *
 * Invarian yang dilindungi di sini: sistem mesti sentiasa mempunyai
 * sekurang-kurangnya satu Pentadbir Sistem, kerana hanya peranan itu boleh
 * menambah pengguna. Jika ia hilang, sistem terkunci selama-lamanya.
 */
class AkaunPentadbirLalaiTest extends TestCase
{
    use RefreshDatabase;

    private const KATA_LALUAN = 'KataLaluan#Pemasangan1';

    private function pentadbirLalai(): User
    {
        config()->set('pentadbir.password', self::KATA_LALUAN);
        $this->seed(AdminUserSeeder::class);

        return User::where('username', config('pentadbir.username'))->sole();
    }

    /*
    |--------------------------------------------------------------------------
    | Pemasangan
    |--------------------------------------------------------------------------
    */

    public function test_pemasangan_menghasilkan_satu_pentadbir_yang_boleh_log_masuk(): void
    {
        $pentadbir = $this->pentadbirLalai();

        $this->assertTrue($pentadbir->isAdministrator());
        $this->assertTrue(Hash::check(self::KATA_LALUAN, $pentadbir->password));

        // Kelayakan itu benar-benar berfungsi sebagai log masuk.
        $this->post(route('login.attempt'), [
            'username' => $pentadbir->username,
            'password' => self::KATA_LALUAN,
        ])->assertRedirect();

        $this->assertAuthenticatedAs($pentadbir);
    }

    public function test_pentadbir_lalai_boleh_menambah_pengguna(): void
    {
        // Sebab akaun ini wujud: ia mesti mampu mendaftarkan pengguna lain.
        $this->actingAs($this->pentadbirLalai())
            ->post(route('administration.users.store'), [
                'name' => 'Pegawai Baharu',
                'username' => 'pegawai.baharu',
                'email' => 'baharu@contoh.gov.my',
                'roles' => [User::ROLE_ANALYST],
                'password' => 'KataLaluan#2026x',
                'password_confirmation' => 'KataLaluan#2026x',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'pegawai.baharu']);
    }

    /*
    |--------------------------------------------------------------------------
    | Arahan pemulihan
    |--------------------------------------------------------------------------
    */

    public function test_arahan_mencipta_pentadbir_apabila_tiada(): void
    {
        config()->set('pentadbir.password', self::KATA_LALUAN);

        $this->assertSame(0, User::count());

        $this->artisan('pentadbir:sedia')->assertSuccessful();

        $pentadbir = User::sole();

        $this->assertTrue($pentadbir->isAdministrator());
        $this->assertTrue(Hash::check(self::KATA_LALUAN, $pentadbir->password));
    }

    public function test_arahan_tidak_menukar_kata_laluan_tanpa_diminta(): void
    {
        $pentadbir = $this->pentadbirLalai();

        $this->artisan('pentadbir:sedia')->assertSuccessful();

        $this->assertTrue(Hash::check(self::KATA_LALUAN, $pentadbir->fresh()->password));
    }

    public function test_arahan_memulihkan_kata_laluan_yang_hilang(): void
    {
        $pentadbir = $this->pentadbirLalai();

        $this->artisan('pentadbir:sedia', ['--kata-laluan' => 'KataLaluan#Pulih9'])
            ->assertSuccessful();

        $pentadbir->refresh();

        $this->assertTrue(Hash::check('KataLaluan#Pulih9', $pentadbir->password));
        $this->assertFalse(Hash::check(self::KATA_LALUAN, $pentadbir->password));

        // Kelayakan yang dipulihkan benar-benar boleh log masuk.
        $this->post(route('login.attempt'), [
            'username' => $pentadbir->username,
            'password' => 'KataLaluan#Pulih9',
        ]);

        $this->assertAuthenticatedAs($pentadbir);
    }

    public function test_arahan_memulangkan_peranan_pentadbir_yang_hilang(): void
    {
        $pentadbir = $this->pentadbirLalai();
        $pentadbir->forceFill(['roles' => [User::ROLE_ANALYST]])->save();

        $this->artisan('pentadbir:sedia')->assertSuccessful();

        $pentadbir->refresh();

        $this->assertTrue($pentadbir->isAdministrator());
        // Peranan lain yang sedia ada tidak dibuang.
        $this->assertTrue($pentadbir->isAnalyst());
    }

    /*
    |--------------------------------------------------------------------------
    | Invarian: sekurang-kurangnya satu pentadbir
    |--------------------------------------------------------------------------
    */

    public function test_peranan_pentadbir_terakhir_tidak_boleh_dibuang(): void
    {
        $pentadbir = $this->pentadbirLalai();

        $this->actingAs($pentadbir)
            ->put(route('administration.users.update', $pentadbir), [
                'name' => $pentadbir->name,
                'username' => $pentadbir->username,
                'email' => $pentadbir->email,
                'roles' => [User::ROLE_ANALYST],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($pentadbir->fresh()->isAdministrator());
    }

    public function test_peranan_pentadbir_boleh_dibuang_jika_ada_pentadbir_lain(): void
    {
        $pentadbir = $this->pentadbirLalai();
        $lain = User::factory()->create(['roles' => [User::ROLE_ADMINISTRATOR]]);

        $this->actingAs($lain)
            ->put(route('administration.users.update', $pentadbir), [
                'name' => $pentadbir->name,
                'username' => $pentadbir->username,
                'email' => $pentadbir->email,
                'roles' => [User::ROLE_ANALYST],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($pentadbir->fresh()->isAdministrator());
        $this->assertTrue($lain->fresh()->isAdministrator());
    }

    public function test_pentadbir_boleh_menambah_peranan_lain_kepada_dirinya(): void
    {
        // Penjaga hanya menghalang pembuangan peranan pentadbir, bukan
        // penambahan peranan lain.
        $pentadbir = $this->pentadbirLalai();

        $this->actingAs($pentadbir)
            ->put(route('administration.users.update', $pentadbir), [
                'name' => $pentadbir->name,
                'username' => $pentadbir->username,
                'email' => $pentadbir->email,
                'roles' => [User::ROLE_ADMINISTRATOR, User::ROLE_ANALYST],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($pentadbir->fresh()->isAdministrator());
        $this->assertTrue($pentadbir->fresh()->isAnalyst());
    }

    public function test_pentadbir_terakhir_tidak_boleh_dipadam(): void
    {
        $pentadbir = $this->pentadbirLalai();
        $lain = User::factory()->create(['roles' => [User::ROLE_ADMINISTRATOR]]);

        // `$lain` memadam pentadbir lalai — masih ada seorang, jadi dibenarkan.
        $this->actingAs($lain)
            ->delete(route('administration.users.destroy', $pentadbir))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $pentadbir->id]);

        // Kini `$lain` ialah yang terakhir; padam sendiri sudah dihalang, dan
        // penjaga pentadbir terakhir menutup baki laluan.
        $terakhir = User::factory()->create(['roles' => [User::ROLE_ADMINISTRATOR]]);

        $this->actingAs($lain)
            ->delete(route('administration.users.destroy', $terakhir))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, User::query()->administrators()->count());
    }

    public function test_sistem_sentiasa_mempunyai_sekurang_kurangnya_satu_pentadbir(): void
    {
        $pentadbir = $this->pentadbirLalai();

        // Cuba setiap laluan yang boleh menyifarkan bilangan pentadbir.
        $this->actingAs($pentadbir)
            ->delete(route('administration.users.destroy', $pentadbir));

        $this->actingAs($pentadbir)
            ->put(route('administration.users.update', $pentadbir), [
                'name' => $pentadbir->name,
                'username' => $pentadbir->username,
                'email' => $pentadbir->email,
                'roles' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN], // buang peranan pentadbir
            ]);

        $this->assertSame(1, User::query()->administrators()->count());
    }
}
