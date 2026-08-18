<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Seorang pengguna boleh memegang lebih daripada satu peranan.
 *
 * Sifat utama yang dikawal di sini ialah penggabungan kebenaran: kebenaran
 * setiap peranan disatukan, jadi menambah peranan tidak boleh mengurangkan
 * akses sedia ada seseorang.
 */
class PerananBerbilangTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 'A010101';

    private function pentadbir(): User
    {
        return User::factory()->create(['roles' => [User::ROLE_ADMINISTRATOR]]);
    }

    /**
     * @param  array<int, string>  $peranan
     * @return array<string, mixed>
     */
    private function borang(array $peranan, string $username = 'pengguna.baharu'): array
    {
        return [
            'name' => 'Pengguna Baharu',
            'username' => $username,
            'email' => $username.'@contoh.gov.my',
            'roles' => $peranan,
            'password' => 'KataLaluan#2026x',
            'password_confirmation' => 'KataLaluan#2026x',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Penyimpanan
    |--------------------------------------------------------------------------
    */

    public function test_peranan_disimpan_sebagai_senarai(): void
    {
        $pengguna = User::factory()->create([
            'roles' => [User::ROLE_COORDINATOR, User::ROLE_ANALYST],
        ]);

        $this->assertSame(
            [User::ROLE_COORDINATOR, User::ROLE_ANALYST],
            $pengguna->fresh()->assignedRoles(),
        );
    }

    public function test_alias_role_tunggal_kekal_berfungsi(): void
    {
        // Kod dan ujian sedia ada menulis `['role' => X]`; ia mesti terus
        // menghasilkan pengguna dengan satu peranan.
        $pengguna = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN]);

        $this->assertSame([User::ROLE_KETUA_BAHAGIAN], $pengguna->assignedRoles());
        $this->assertSame(User::ROLE_KETUA_BAHAGIAN, $pengguna->role);
        $this->assertTrue($pengguna->isKetuaBahagian());
    }

    public function test_label_setiap_peranan_dipaparkan(): void
    {
        $pengguna = User::factory()->create([
            'roles' => [User::ROLE_KETUA_BAHAGIAN, User::ROLE_ANALYST],
        ]);

        $this->assertSame(
            ['Ketua Bahagian', 'Pegawai Analisis'],
            $pengguna->assignedRoleLabels(),
        );
        $this->assertSame('Ketua Bahagian, Pegawai Analisis', $pengguna->roleLabel());
    }

    public function test_pengguna_tanpa_peranan_tidak_meletup(): void
    {
        $pengguna = User::factory()->create(['roles' => []]);

        $this->assertSame([], $pengguna->assignedRoles());
        $this->assertNull($pengguna->role);
        $this->assertSame('-', $pengguna->roleLabel());
        $this->assertFalse($pengguna->isAdministrator());
    }

    /*
    |--------------------------------------------------------------------------
    | Penggabungan kebenaran
    |--------------------------------------------------------------------------
    */

    public function test_kebenaran_daripada_semua_peranan_digabungkan(): void
    {
        // Penyelaras sahaja tiada `manage-analysis`; Pegawai Analisis sahaja
        // tiada `manage-assignment`. Digabungkan, kedua-duanya diperoleh.
        $gabungan = User::factory()->create([
            'roles' => [User::ROLE_COORDINATOR, User::ROLE_ANALYST],
        ]);

        $this->assertTrue(Gate::forUser($gabungan)->allows('manage-assignment'));
        $this->assertTrue(Gate::forUser($gabungan)->allows('manage-analysis'));
        $this->assertTrue(Gate::forUser($gabungan)->allows('view-dashboard'));

        // Tiada peranan itu memberi pentadbiran, jadi ia kekal ditolak.
        $this->assertFalse(Gate::forUser($gabungan)->allows('access-administration'));
    }

    public function test_menambah_peranan_tidak_mengurangkan_akses(): void
    {
        $penyelaras = User::factory()->create(['roles' => [User::ROLE_COORDINATOR]]);

        foreach (User::roles() as $tambahan) {
            $pengguna = User::factory()->create([
                'roles' => [User::ROLE_COORDINATOR, $tambahan],
            ]);

            foreach (['view-dashboard', 'view-all-entities', 'manage-assignment', 'manage-workflow'] as $gate) {
                if (Gate::forUser($penyelaras)->allows($gate)) {
                    $this->assertTrue(
                        Gate::forUser($pengguna)->allows($gate),
                        "Menambah [{$tambahan}] menghilangkan kebenaran [{$gate}].",
                    );
                }
            }
        }
    }

    public function test_keterlihatan_entiti_mengambil_skop_paling_luas(): void
    {
        // Pegawai Analisis sahaja terhad kepada entiti yang ditugaskan;
        // digabungkan dengan Ketua Bahagian, skop menjadi semua entiti.
        $terhad = User::factory()->create(['roles' => [User::ROLE_ANALYST]]);
        $luas = User::factory()->create([
            'roles' => [User::ROLE_ANALYST, User::ROLE_KETUA_BAHAGIAN],
        ]);

        $this->assertSame([], $terhad->getAccessibleEntities());
        $this->assertNull($luas->getAccessibleEntities());
    }

    public function test_pengguna_berbilang_peranan_disenaraikan_sebagai_pegawai_analisis(): void
    {
        $tunggal = User::factory()->create(['roles' => [User::ROLE_ANALYST], 'name' => 'Analis Tunggal']);
        $gabungan = User::factory()->create([
            'roles' => [User::ROLE_KETUA_BAHAGIAN, User::ROLE_ANALYST],
            'name' => 'Analis Gabungan',
        ]);
        User::factory()->create(['roles' => [User::ROLE_COORDINATOR], 'name' => 'Bukan Analis']);

        $senarai = app(EntityAssignmentService::class)->analystsAvailable()->pluck('id');

        $this->assertTrue($senarai->contains($tunggal->id));
        $this->assertTrue($senarai->contains($gabungan->id));
        $this->assertCount(2, $senarai);
    }

    public function test_entiti_boleh_ditugaskan_kepada_pengguna_berbilang_peranan(): void
    {
        $gabungan = User::factory()->create([
            'roles' => [User::ROLE_KETUA_BAHAGIAN, User::ROLE_ANALYST],
        ]);

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $gabungan,
            User::factory()->create(['roles' => [User::ROLE_COORDINATOR]]),
        );

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'assigned_to_user_id' => $gabungan->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Borang pentadbiran
    |--------------------------------------------------------------------------
    */

    public function test_pentadbir_boleh_memberi_beberapa_peranan_serentak(): void
    {
        $this->actingAs($this->pentadbir())
            ->post(route('administration.users.store'), $this->borang([
                User::ROLE_KETUA_BAHAGIAN,
                User::ROLE_TIMBALAN_PENGARAH_II,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [User::ROLE_KETUA_BAHAGIAN, User::ROLE_TIMBALAN_PENGARAH_II],
            User::where('username', 'pengguna.baharu')->sole()->assignedRoles(),
        );
    }

    public function test_pentadbir_boleh_menukar_set_peranan_pengguna(): void
    {
        $sasaran = User::factory()->create(['roles' => [User::ROLE_ANALYST]]);

        $this->actingAs($this->pentadbir())
            ->put(route('administration.users.update', $sasaran), [
                'name' => $sasaran->name,
                'username' => $sasaran->username,
                'email' => $sasaran->email,
                'roles' => [User::ROLE_COORDINATOR, User::ROLE_ANALYST],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [User::ROLE_COORDINATOR, User::ROLE_ANALYST],
            $sasaran->fresh()->assignedRoles(),
        );
    }

    public function test_sekurang_kurangnya_satu_peranan_diperlukan(): void
    {
        $this->actingAs($this->pentadbir())
            ->post(route('administration.users.store'), $this->borang([]))
            ->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['username' => 'pengguna.baharu']);
    }

    public function test_peranan_tidak_sah_dalam_senarai_ditolak(): void
    {
        $this->actingAs($this->pentadbir())
            ->post(route('administration.users.store'), $this->borang([
                User::ROLE_ANALYST,
                'superuser',
            ]))
            ->assertSessionHasErrors('roles.1');

        $this->assertDatabaseMissing('users', ['username' => 'pengguna.baharu']);
    }

    public function test_peranan_berulang_ditolak(): void
    {
        $this->actingAs($this->pentadbir())
            ->post(route('administration.users.store'), $this->borang([
                User::ROLE_ANALYST,
                User::ROLE_ANALYST,
            ]))
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('users', ['username' => 'pengguna.baharu']);
    }

    /*
    |--------------------------------------------------------------------------
    | Singkatan peranan
    |--------------------------------------------------------------------------
    */

    public function test_setiap_peranan_mempunyai_singkatan(): void
    {
        $singkatan = User::roleShortLabels();

        $this->assertSame(User::roles(), array_keys($singkatan));

        foreach ($singkatan as $peranan => $kod) {
            $this->assertNotSame('', $kod, "Peranan [{$peranan}] tiada singkatan.");
            $this->assertSame(
                $kod,
                strtoupper($kod),
                "Singkatan [{$kod}] mesti huruf besar.",
            );
        }

        $this->assertSame('PS', $singkatan[User::ROLE_ADMINISTRATOR]);
    }

    public function test_singkatan_peranan_tidak_berulang(): void
    {
        // Singkatan yang sama pada dua peranan menjadikan borang mengelirukan.
        $singkatan = array_values(User::roleShortLabels());

        $this->assertSame($singkatan, array_unique($singkatan));
    }

    public function test_label_dan_singkatan_datang_daripada_takrif_yang_sama(): void
    {
        $takrif = User::roleDefinitions();

        $this->assertSame(array_keys($takrif), User::roles());
        $this->assertSame(
            array_map(fn (array $t): string => $t['label'], $takrif),
            User::roleLabels(),
        );
        $this->assertSame(
            array_map(fn (array $t): string => $t['singkatan'], $takrif),
            User::roleShortLabels(),
        );
    }

    public function test_singkatan_peranan_yang_dipegang_boleh_dibaca(): void
    {
        $pengguna = User::factory()->create([
            'roles' => [User::ROLE_COORDINATOR, User::ROLE_ANALYST],
        ]);

        $this->assertSame(['PPA', 'PA'], $pengguna->assignedRoleShortLabels());
    }

    public function test_borang_pentadbir_memaparkan_singkatan_sahaja(): void
    {
        $respons = $this->actingAs($this->pentadbir())
            ->get(route('administration.users.create'))
            ->assertOk();

        foreach (User::roleDefinitions() as $takrif) {
            // Kod pendek ialah satu-satunya teks yang dipapar pada pilihan.
            $respons->assertSee(
                '<span class="role-choice__code">'.$takrif['singkatan'].'</span>',
                false,
            );

            // Nama penuh kekal dicapai tanpa dipapar sebagai teks.
            $respons->assertSee('title="'.$takrif['label'].'"', false);
            $respons->assertSee('aria-label="'.$takrif['label'].'"', false);
        }
    }

    public function test_borang_pentadbir_memaparkan_kotak_semak_bagi_setiap_peranan(): void
    {
        $sasaran = User::factory()->create([
            'roles' => [User::ROLE_COORDINATOR, User::ROLE_ANALYST],
        ]);

        $respons = $this->actingAs($this->pentadbir())
            ->get(route('administration.users.edit', $sasaran))
            ->assertOk();

        foreach (User::roles() as $peranan) {
            $respons->assertSee('name="roles[]" value="'.$peranan.'"', false);
        }
    }
}
