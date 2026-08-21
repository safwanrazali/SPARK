<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * FASA 13 — semakan kesediaan pelepasan (release readiness).
 *
 * Ujian ini bukan menguji ciri sistem; ia menguji perkara yang mesti betul
 * SEBELUM sistem dipasang pada pelayan:
 *
 * - kawalan keselamatan asas (had percubaan log masuk, kelayakan semaian)
 * - konfigurasi persekitaran pengeluaran
 * - integriti migrasi (naik, turun, naik semula)
 * - inventori route dan kebenaran
 * - penjanaan laporan
 * - ketiadaan kebergantungan muat naik dokumen dalam aliran pelaporan
 */
class Phase13ReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | 1. Keselamatan — had percubaan log masuk
    |--------------------------------------------------------------------------
    */

    public function test_percubaan_log_masuk_dihadkan_selepas_lima_kegagalan(): void
    {
        User::factory()->create(['username' => 'pegawai', 'password' => 'kata-laluan-benar']);

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))->post(route('login.attempt'), [
                'username' => 'pegawai',
                'password' => 'salah',
            ]);
        }

        // Percubaan seterusnya disekat walaupun kata laluan kali ini betul.
        $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.attempt'), [
                'username' => 'pegawai',
                'password' => 'kata-laluan-benar',
            ])
            ->assertOk()
            ->assertSee('Terlalu banyak percubaan');

        $this->assertGuest();
    }

    public function test_sekatan_ditamatkan_selepas_tempoh_berlalu(): void
    {
        User::factory()->create(['username' => 'pegawai', 'password' => 'kata-laluan-benar']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['username' => 'pegawai', 'password' => 'salah']);
        }

        $this->travel(61)->seconds();

        $this->post(route('login.attempt'), [
            'username' => 'pegawai',
            'password' => 'kata-laluan-benar',
        ])->assertRedirect(route('analisis.index'));

        $this->assertAuthenticated();

        $this->travelBack();
    }

    public function test_sekatan_tidak_menjejaskan_akaun_pegawai_lain(): void
    {
        User::factory()->create(['username' => 'pegawai.a', 'password' => 'rahsia-a']);
        User::factory()->create(['username' => 'pegawai.b', 'password' => 'rahsia-b']);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login.attempt'), ['username' => 'pegawai.a', 'password' => 'salah']);
        }

        // Akaun lain daripada IP yang sama masih boleh log masuk.
        $this->post(route('login.attempt'), [
            'username' => 'pegawai.b',
            'password' => 'rahsia-b',
        ])->assertRedirect(route('analisis.index'));

        $this->assertAuthenticated();
    }

    public function test_log_masuk_berjaya_mengosongkan_kiraan_percubaan(): void
    {
        User::factory()->create(['username' => 'pegawai', 'password' => 'kata-laluan-benar']);

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.attempt'), ['username' => 'pegawai', 'password' => 'salah']);
        }

        $this->post(route('login.attempt'), ['username' => 'pegawai', 'password' => 'kata-laluan-benar']);
        $this->assertAuthenticated();

        $this->post(route('logout'));

        // Kiraan bermula semula: empat kegagalan lagi tidak menyekat.
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.attempt'), ['username' => 'pegawai', 'password' => 'salah']);
        }

        $this->post(route('login.attempt'), ['username' => 'pegawai', 'password' => 'kata-laluan-benar'])
            ->assertRedirect(route('analisis.index'));

        $this->assertAuthenticated();
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Keselamatan — kelayakan semaian
    |--------------------------------------------------------------------------
    */

    public function test_semaian_pemasangan_hanya_mencipta_akaun_pentadbir(): void
    {
        config()->set('pentadbir.password', 'kata-laluan-pemasangan');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());

        $pentadbir = User::first();
        $this->assertSame(User::ROLE_ADMINISTRATOR, $pentadbir->role);

        // Tiada akaun ujian rangka kerja dengan kata laluan lalai.
        $this->assertFalse(Hash::check('password', $pentadbir->password));
        $this->assertNull(User::where('username', 'testuser')->first());
    }

    public function test_semaian_tidak_menetapkan_semula_kata_laluan_pentadbir_sedia_ada(): void
    {
        config()->set('pentadbir.password', 'kata-laluan-pemasangan');
        $this->seed(AdminUserSeeder::class);

        $pentadbir = User::firstOrFail();
        $pentadbir->update(['password' => 'kata-laluan-baharu-pegawai']);

        // Pemasangan semula / kemas kini menjalankan semula seeder.
        $this->seed(AdminUserSeeder::class);

        $pentadbir->refresh();

        $this->assertTrue(Hash::check('kata-laluan-baharu-pegawai', $pentadbir->password));
        $this->assertFalse(Hash::check('kata-laluan-pemasangan', $pentadbir->password));
        $this->assertSame(1, User::count());
    }

    public function test_semaian_pengeluaran_gagal_tanpa_kata_laluan_yang_ditetapkan(): void
    {
        config()->set('pentadbir.password', null);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD');

        // Dipanggil terus: `php artisan db:seed` pada pelayan pengeluaran
        // memerlukan pengesahan interaktif yang tiada dalam ujian.
        app(AdminUserSeeder::class)->run();
    }

    public function test_pemasangan_pembangunan_menjana_kata_laluan_rawak(): void
    {
        config()->set('pentadbir.password', null);

        $this->seed(AdminUserSeeder::class);

        $pentadbir = User::firstOrFail();

        foreach (['password', 'admin', 'rahsia', '12345678'] as $lemah) {
            $this->assertFalse(
                Hash::check($lemah, $pentadbir->password),
                "Kata laluan pentadbir tidak boleh [{$lemah}].",
            );
        }
    }

    public function test_tiada_kata_laluan_ditulis_di_dalam_repositori(): void
    {
        $fail = array_merge(
            glob(base_path('database/seeders/*.php')) ?: [],
            glob(base_path('config/*.php')) ?: [],
            glob(base_path('app/Http/Controllers/**/*.php')) ?: [],
        );

        foreach ($fail as $satu) {
            $kandungan = (string) file_get_contents($satu);

            $this->assertDoesNotMatchRegularExpression(
                '/Hash::make\(\s*[\'"][^\'"]+[\'"]\s*\)/',
                $kandungan,
                'Kata laluan literal ditemui dalam '.basename($satu).'.',
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Konfigurasi persekitaran
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, string>
     */
    private function bacaTemplatEnv(string $fail): array
    {
        $laluan = base_path($fail);

        $this->assertFileExists($laluan, "Templat persekitaran [{$fail}] tiada.");

        $nilai = [];

        foreach (file($laluan, FILE_IGNORE_NEW_LINES) ?: [] as $baris) {
            $baris = trim($baris);

            if ($baris === '' || str_starts_with($baris, '#') || ! str_contains($baris, '=')) {
                continue;
            }

            [$kunci, $isi] = explode('=', $baris, 2);
            $nilai[trim($kunci)] = trim($isi, " \t\"'");
        }

        return $nilai;
    }

    public function test_templat_persekitaran_pengeluaran_selamat_secara_lalai(): void
    {
        $env = $this->bacaTemplatEnv('.env.production.example');

        $this->assertSame('production', $env['APP_ENV'] ?? null);
        $this->assertSame('false', $env['APP_DEBUG'] ?? null, 'APP_DEBUG mesti false pada pelayan pengeluaran.');
        $this->assertSame('true', $env['SESSION_SECURE_COOKIE'] ?? null);
        $this->assertSame('true', $env['SESSION_HTTP_ONLY'] ?? null);
        $this->assertSame('true', $env['SESSION_ENCRYPT'] ?? null);
        $this->assertSame('lax', $env['SESSION_SAME_SITE'] ?? null);
        $this->assertNotSame('debug', $env['LOG_LEVEL'] ?? null);
        $this->assertArrayHasKey('ADMIN_PASSWORD', $env);
        $this->assertArrayHasKey('DB_DATABASE', $env);
    }

    public function test_templat_persekitaran_pembangunan_mengandungi_kunci_yang_diperlukan(): void
    {
        $env = $this->bacaTemplatEnv('.env.example');

        foreach ([
            'APP_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_URL',
            'DB_CONNECTION', 'SESSION_DRIVER', 'CACHE_STORE', 'QUEUE_CONNECTION',
            'ADMIN_USERNAME', 'ADMIN_PASSWORD',
        ] as $kunci) {
            $this->assertArrayHasKey($kunci, $env, "Kunci [{$kunci}] tiada dalam .env.example.");
        }

        $this->assertSame('', $env['ADMIN_PASSWORD'] ?? null, 'ADMIN_PASSWORD tidak boleh mempunyai nilai lalai.');
    }

    public function test_fail_persekitaran_sebenar_tidak_dijejaki_oleh_git(): void
    {
        $gitignore = (string) file_get_contents(base_path('.gitignore'));

        foreach (['.env', '.env.production'] as $fail) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote($fail, '/').'$/m',
                $gitignore,
                "Fail [{$fail}] mesti diabaikan oleh git.",
            );
        }
    }

    /**
     * Nilai lalai konfigurasi sesi (bukan nilai persekitaran ujian, yang
     * sengaja menggunakan pemacu 'array').
     */
    public function test_kuki_sesi_dikeraskan_secara_lalai(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));

        $env = $this->bacaTemplatEnv('.env.production.example');
        $this->assertSame('database', $env['SESSION_DRIVER'] ?? null);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Integriti migrasi
    |--------------------------------------------------------------------------
    */

    public function test_setiap_migrasi_boleh_dipatah_balik(): void
    {
        foreach (glob(database_path('migrations/*.php')) ?: [] as $fail) {
            $this->assertStringContainsString(
                'public function down',
                (string) file_get_contents($fail),
                'Migrasi '.basename($fail).' tiada kaedah down().',
            );
        }
    }

    public function test_migrasi_boleh_dipatah_balik_dan_dijalankan_semula(): void
    {
        $jadual = [
            'users', 'sessions', 'cache', 'jobs', 'muat_naik',
            'analisis_inventori', 'status_laporan', 'entiti_assignment',
            'workflow_status', 'activity_log', 'analisis_draft_history', 'approval_logs',
        ];

        Artisan::call('migrate:reset', ['--force' => true]);

        foreach ($jadual as $satu) {
            $this->assertFalse(Schema::hasTable($satu), "Jadual [{$satu}] masih wujud selepas migrate:reset.");
        }

        Artisan::call('migrate', ['--force' => true]);

        foreach ($jadual as $satu) {
            $this->assertTrue(Schema::hasTable($satu), "Jadual [{$satu}] tiada selepas migrasi dijalankan semula.");
        }
    }

    public function test_lajur_penting_setiap_jadual_pemantauan_wujud(): void
    {
        $dijangka = [
            'entiti_assignment' => ['agency_code', 'assigned_to_user_id', 'assigned_by_user_id', 'assigned_at', 'status'],
            'workflow_status' => ['agency_code', 'current_stage', 'stage_name', 'status', 'status_since', 'updated_by_user_id'],
            'activity_log' => ['agency_code', 'action', 'old_value', 'new_value', 'changed_by_user_id', 'changed_at', 'metadata'],
            'analisis_draft_history' => ['analisis_inventori_id', 'version', 'section_name', 'section_data', 'saved_at', 'saved_by_user_id', 'is_current'],
            'approval_logs' => ['agency_code', 'report_type', 'status_before', 'status_after', 'approved_by_user_id', 'approved_at', 'comments'],
        ];

        foreach ($dijangka as $jadual => $lajur) {
            foreach ($lajur as $satu) {
                $this->assertTrue(
                    Schema::hasColumn($jadual, $satu),
                    "Lajur [{$jadual}.{$satu}] tiada.",
                );
            }
        }
    }

    public function test_satu_penugasan_aktif_dikuatkuasakan_oleh_pangkalan_data(): void
    {
        $indeks = collect(Schema::getIndexes('entiti_assignment'));

        $this->assertTrue(
            $indeks->contains(fn (array $i) => ($i['unique'] ?? false)
                && in_array('agency_code', $i['columns'], true)),
            'Kekangan unik penugasan aktif tiada pada jadual entiti_assignment.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Inventori route
    |--------------------------------------------------------------------------
    */

    public function test_inventori_route_aplikasi_kekal_seperti_yang_didokumenkan(): void
    {
        $sebenar = collect(app('router')->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->getActionName(), 'App\\Http\\Controllers\\'))
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->sort()
            ->values()
            ->all();

        $dijangka = [
            'administration.users.create',
            'administration.users.destroy',
            'administration.users.edit',
            'administration.users.index',
            'administration.users.store',
            'administration.users.tetap-semula-kata-laluan',
            'administration.users.update',
            'analisis.borang',
            'analisis.draf',
            'analisis.index',
            'analisis.simpan',
            'audit.index',
            'dashboard',
            'entiti.show',
            'kata-laluan.simpan',
            'kata-laluan.tukar',
            'kemajuan.hantar',
            'kemajuan.kembalikan',
            'kemajuan.sahkan',
            'kemajuan.selesai',
            'kemajuan.semak',
            'kemajuan.serah',
            'laporan.index',
            'laporan.inventori',
            'laporan.unduh',
            'login',
            'login.attempt',
            'logout',
            'muat-naik.destroy',
            'muat-naik.history',
            'muat-naik.index',
            'muat-naik.preview',
            'muat-naik.store',
            'penugasan.index',
            'penugasan.pendaftaran.kemas-kini',
            'penugasan.pendaftaran.set-semula',
            'penugasan.show',
            'penugasan.simpan',
            'penugasan.tarik',
            'profil.edit',
            'profil.update',
            'status.index',
            'status.kitar',
            'workflow.index',
            'workflow.show',
        ];

        $this->assertSame($dijangka, $sebenar);
    }

    public function test_setiap_route_yang_mengubah_data_dilindungi_kebenaran(): void
    {
        $terbuka = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\')) {
                continue;
            }

            if (! array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            // Pengecualian yang dijangka: route ini terbuka kepada setiap
            // pengguna yang telah log masuk, jadi tiada gate kebenaran yang
            // munasabah. `profil.update` menulis hanya kepada rekod pengguna
            // yang membuat permintaan — tiada parameter pengguna pada route,
            // dan `role` tiada dalam peraturan pengesahannya.
            // `kata-laluan.simpan` menulis hanya kata laluan pengguna yang
            // membuat permintaan, dan mesti kekal boleh dicapai justeru kerana
            // akaun itu belum boleh menggunakan sistem.
            if (in_array($route->getName(), ['login.attempt', 'logout', 'profil.update', 'kata-laluan.simpan'], true)) {
                continue;
            }

            $adaKebenaran = collect($middleware)->contains(
                fn ($m) => is_string($m) && (str_starts_with($m, 'can:') || $m === 'entity.access')
            );

            if (! $adaKebenaran) {
                $terbuka[] = $route->uri();
            }
        }

        $this->assertSame(
            [],
            $terbuka,
            'Route penulisan tanpa gate/middleware kebenaran: '.implode(', ', $terbuka),
        );
    }

    public function test_middleware_web_menguatkuasakan_perlindungan_asas(): void
    {
        $kumpulan = app(HttpKernel::class)->getMiddlewareGroups()['web'] ?? [];

        foreach ([
            PreventRequestForgery::class => 'perlindungan CSRF',
            EncryptCookies::class => 'penyulitan kuki',
            StartSession::class => 'pengurusan sesi',
        ] as $middleware => $tujuan) {
            $this->assertContains(
                $middleware,
                $kumpulan,
                "Kumpulan middleware web tiada {$tujuan}.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Penjanaan laporan
    |--------------------------------------------------------------------------
    */

    public function test_aset_templat_laporan_tersedia(): void
    {
        foreach (['image/logo_nacsa.png', 'image/logo_ptpkm.png'] as $aset) {
            $this->assertFileExists(public_path($aset), "Aset laporan [{$aset}] tiada.");
        }

        foreach (['laporan.pdf.body', 'laporan.pdf.header', 'laporan.pdf.footer', 'laporan.inventori'] as $paparan) {
            $this->assertTrue(view()->exists($paparan), "Paparan laporan [{$paparan}] tiada.");
        }
    }

    public function test_binaan_aset_hadapan_tersedia_untuk_pengeluaran(): void
    {
        $manifest = public_path('build/manifest.json');

        $this->assertFileExists($manifest, 'Jalankan `npm run build` sebelum pemasangan.');

        $isi = json_decode((string) file_get_contents($manifest), true);

        $this->assertIsArray($isi);
        $this->assertNotEmpty($isi, 'Manifest binaan kosong.');
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Tiada kebergantungan muat naik dokumen (spesifikasi bahagian 3)
    |--------------------------------------------------------------------------
    */

    public function test_aliran_pelaporan_tidak_merujuk_modul_muat_naik(): void
    {
        $aliranPelaporan = [
            'app/Http/Controllers/AnalisisInventoriController.php',
            'app/Http/Controllers/LaporanController.php',
            'app/Services/AnalisisDraftService.php',
            'app/Support/BorangAnalisis.php',
            'app/Support/SeksyenAnalisis.php',
        ];

        foreach ($aliranPelaporan as $fail) {
            $kandungan = (string) file_get_contents(base_path($fail));

            $this->assertStringNotContainsString('MuatNaik', $kandungan, $fail.' merujuk modul muat naik.');
            $this->assertStringNotContainsString('muat-naik', $kandungan, $fail.' merujuk route muat naik.');
        }
    }

    /**
     * Paparan borang dapatan dan templat laporan tidak boleh menawarkan
     * sebarang tindakan muat naik. (Menu sisi kekal memaparkan modul muat
     * naik sedia ada kepada peranan yang dibenarkan — ia bukan sebahagian
     * daripada aliran pelaporan.)
     */
    public function test_paparan_borang_dan_templat_laporan_tiada_tindakan_muat_naik(): void
    {
        $paparan = [
            'resources/views/analisis/form.blade.php',
            'resources/views/analisis/index.blade.php',
            'resources/views/laporan/index.blade.php',
            'resources/views/laporan/inventori.blade.php',
            'resources/views/laporan/pdf/body.blade.php',
            'resources/views/laporan/pdf/header.blade.php',
            'resources/views/laporan/pdf/footer.blade.php',
        ];

        foreach ($paparan as $fail) {
            $kandungan = (string) file_get_contents(base_path($fail));

            foreach (['muat-naik', 'muat_naik', 'MuatNaik', 'type="file"'] as $petunjuk) {
                $this->assertStringNotContainsString(
                    $petunjuk,
                    $kandungan,
                    $fail.' mengandungi tindakan muat naik ['.$petunjuk.'].',
                );
            }
        }
    }
}
