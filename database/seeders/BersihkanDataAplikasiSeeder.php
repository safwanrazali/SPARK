<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Kosongkan data aplikasi, KEKALKAN akaun pengguna.
 *
 * Seeder ini TIDAK dipanggil oleh DatabaseSeeder. Ia mesti dijalankan secara
 * eksplisit:
 *
 *     php artisan db:seed --class=BersihkanDataAplikasiSeeder
 *
 * Ia mengembalikan pangkalan data kepada keadaan "belum digunakan" untuk
 * demo dan ujian setempat: tiada entiti dipantau, tiada dapatan analisis,
 * tiada laporan, tiada jejak aktiviti — tetapi setiap akaun, peranan dan
 * kata laluan kekal seperti sedia ada.
 *
 * Skema TIDAK disentuh: tiada DROP, ALTER atau perubahan indeks/kekangan.
 * Kekangan kunci asing kekal AKTIF sepanjang operasi.
 */
class BersihkanDataAplikasiSeeder extends Seeder
{
    /**
     * Jadual data perniagaan/transaksi, disusun anak → induk.
     *
     * `analisis_draft_history` didahulukan kerana ia satu-satunya rujukan
     * antara dua jadual perniagaan (analisis_inventori_id, ON DELETE CASCADE).
     * Selebihnya hanya merujuk `users` dan tidak bergantung sesama sendiri.
     *
     * @var list<string>
     */
    public const JADUAL_DIKOSONGKAN = [
        'analisis_draft_history',
        'activity_log',
        'approval_logs',
        'workflow_stage_status',
        'workflow_status',
        'analisis_inventori',
        'status_laporan',
        'entiti_assignment',
        'laporan_semakan',
        'muat_naik',
    ];

    /**
     * Jadual yang SENGAJA tidak disentuh.
     *
     * users / password_reset_tokens  akaun dan kelayakan pengesahan
     * sessions                       log masuk aktif — bukan data perniagaan
     * migrations                     keadaan skema
     * cache, jobs, ...               infrastruktur rangka kerja
     *
     * Senarai induk sektor dan entiti TIADA dalam pangkalan data; ia berada
     * dalam config/sektor.php dan kekal utuh.
     *
     * @var list<string>
     */
    public const JADUAL_DIKEKALKAN = [
        'users',
        'password_reset_tokens',
        'sessions',
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function run(): void
    {
        $sebelumPengguna = DB::table('users')->count();

        DB::transaction(function () {
            foreach (self::JADUAL_DIKOSONGKAN as $jadual) {
                // delete() dan bukan truncate(): truncate pada sesetengah
                // pemacu memutuskan kekangan kunci asing, dan spesifikasi
                // tugas ini melarangnya.
                $bilangan = DB::table($jadual)->delete();

                $this->command?->info(sprintf('  %-26s %6d baris dipadam', $jadual, $bilangan));
            }

            // Tetapkan semula pembilang AUTOINCREMENT bagi jadual yang
            // dikosongkan sahaja. sqlite_sequence ialah jadual data dalaman
            // SQLite — memadam barisnya menetapkan semula pembilang tanpa
            // mengubah skema (setara ALTER TABLE ... AUTO_INCREMENT = 1).
            // Baris `users` dan `migrations` sengaja tidak disentuh.
            if (DB::getDriverName() === 'sqlite') {
                DB::table('sqlite_sequence')
                    ->whereIn('name', self::JADUAL_DIKOSONGKAN)
                    ->delete();
            }
        });

        $selepasPengguna = DB::table('users')->count();

        // Jaring keselamatan terakhir: jika bilangan pengguna berubah,
        // sesuatu yang tidak dijangka telah berlaku dan ia mesti diketahui.
        if ($sebelumPengguna !== $selepasPengguna) {
            throw new \RuntimeException(sprintf(
                'Bilangan pengguna berubah daripada %d kepada %d — data pengguna tidak sepatutnya tersentuh.',
                $sebelumPengguna,
                $selepasPengguna,
            ));
        }

        $this->command?->info(sprintf('  Akaun pengguna kekal: %d', $selepasPengguna));
    }
}
