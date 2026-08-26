<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadual `status_laporan` — baris kehadiran laporan bagi satu entiti.
 *
 * PENTING: lajur `status` BUKAN lagi sumber kebenaran bagi modul Status Tiga
 * Laporan. Sejak status ketiga-tiga laporan dikira daripada Kemajuan Analisis
 * Entiti (lihat App\Services\StatusTigaLaporanService), tiada antara muka yang
 * menulis atau membaca lajur itu untuk paparan; ia dikekalkan supaya rekod
 * sedia ada dan jejak auditnya tidak hilang.
 *
 * Yang masih digunakan daripada jadual ini ialah kehadiran barisnya — entiti
 * yang mempunyai rekod laporan terus muncul dalam senarai pemantauan.
 */
class StatusLaporan extends Model
{
    use FiltersByEntityAccess;

    protected $table = 'status_laporan';

    public const JENIS = [
        'inventori' => 'Inventori',
        'risiko' => 'Risiko PQC',
        'kesiapsiagaan' => 'Kesiapsiagaan',
    ];

    /**
     * Jenis laporan yang benar-benar mempunyai aliran kerja dalam versi ini.
     *
     * Laporan Risiko PQC dan Kesiapsiagaan belum dibina, jadi tiada status
     * yang boleh dikira bagi kedua-duanya — ia dipaparkan sebagai "N/A" dan
     * bukan "Belum Bermula", supaya tiada siapa menyangka ia sedang menunggu
     * tindakan. Tambah kunci di sini apabila modulnya tersedia.
     *
     * @var array<int, string>
     */
    public const JENIS_AKTIF = ['inventori'];

    public static function jenisAktif(string $jenis): bool
    {
        return in_array($jenis, self::JENIS_AKTIF, true);
    }

    /**
     * Kitaran lama lajur `status`.
     *
     * Dikekalkan kerana WorkflowStatus::STATUSES menggunakannya sebagai
     * perbendaharaan status kerja dalam peringkat semasa. Ia BUKAN
     * perbendaharaan paparan Status Tiga Laporan — lihat self::PAPARAN.
     */
    public const KITARAN = ['Belum Bermula', 'Dalam Proses', 'Siap'];

    /**
     * Perbendaharaan paparan Status Tiga Laporan.
     *
     * Empat keadaan, semuanya dikira daripada Kemajuan Analisis Entiti:
     *
     *   Belum Bermula  entiti belum memulakan aliran kerja analisis
     *   Dalam Proses   analisis sedang berjalan; laporan belum dihantar
     *   Dalam Semakan  laporan di tangan PPA atau menunggu kelulusan KB
     *   Selesai        Ketua Bahagian telah mengesahkan laporan
     */
    public const PAPARAN_BELUM_BERMULA = 'Belum Bermula';

    public const PAPARAN_DALAM_PROSES = 'Dalam Proses';

    public const PAPARAN_DALAM_SEMAKAN = 'Dalam Semakan';

    public const PAPARAN_SELESAI = 'Selesai';

    /**
     * Bukan satu status: penanda bahawa jenis laporan itu belum wujud dalam
     * versi ini. Sengaja di luar self::PAPARAN kerana ia tidak pernah dikira
     * dalam sebarang taburan atau peratusan.
     */
    public const PAPARAN_TIADA = 'N/A';

    public const PAPARAN = [
        self::PAPARAN_BELUM_BERMULA,
        self::PAPARAN_DALAM_PROSES,
        self::PAPARAN_DALAM_SEMAKAN,
        self::PAPARAN_SELESAI,
    ];

    protected $fillable = [
        'sector_code', 'sector_name', 'agency_code', 'agency_name',
        'jenis', 'status', 'user_id',
    ];

    /**
     * Kelas badge bagi satu label paparan — selaras dengan modul lain.
     *
     * "Dalam Semakan" berkongsi warna dengan "Dalam Proses" kerana kedua-duanya
     * bermakna kerja masih berjalan; hanya "Selesai" bertukar hijau.
     *
     * "N/A" mengembalikan null: ia bukan status, jadi ia dipaparkan sebagai
     * teks malap tanpa pil warna.
     */
    public static function badgePaparan(string $paparan): ?string
    {
        if ($paparan === self::PAPARAN_TIADA) {
            return null;
        }

        return [
            self::PAPARAN_SELESAI => 'status-rendah',
            self::PAPARAN_DALAM_SEMAKAN => 'status-sederhana',
            self::PAPARAN_DALAM_PROSES => 'status-sederhana',
        ][$paparan] ?? 'status-tinggi';
    }
}
