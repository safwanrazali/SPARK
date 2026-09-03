<?php

namespace App\Services;

use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Support\AliranKerja;
use Illuminate\Support\Collection;

/**
 * Kad corong entiti papan pemuka — Diterima → Dalam Proses → Selesai — dan
 * baris peringkat yang menyokongnya.
 *
 * Dipisahkan daripada DashboardStatistikService kerana ia menjawab satu
 * soalan yang berdiri sendiri: "di mana setiap entiti berada dalam corong?".
 * Servis papan pemuka mengumpul jawapannya bersama kiraan lain; peraturan
 * corong itu sendiri tinggal di sini.
 *
 * Peraturan "medan telah direkod" TIDAK diduakan: ia datang daripada
 * KemajuanAnalisisService — servis yang sama yang menguatkuasakannya pada
 * aliran kerja — supaya papan pemuka tidak boleh memberi jawapan yang
 * berbeza daripada skrin Kemajuan Analisis bagi entiti yang sama.
 */
class DashboardKadEntitiService
{
    public function __construct(
        private readonly KemajuanAnalisisService $kemajuan,
    ) {}

    /**
     * Tiga kad ringkasan entiti, sebagai satu corong.
     *
     *     SEMUA ENTITI
     *         |
     *         v
     *     ENTITI DITERIMA            (Buku Kerja MPQ diterima)
     *         |
     *         +--> ENTITI DALAM PROSES  (didaftar + PA ditugaskan,
     *         |                          peringkat 5 BELUM Selesai)
     *         +--> ENTITI SELESAI       (peringkat 5 Selesai)
     *
     * DITERIMA ialah medan `syarat_lanjut` peringkat 1.1 — Tarikh Terima dan
     * Status Borang Penerimaan Data — dan BUKAN status peringkat 1.1. Status
     * Selesai turut menuntut No. Rujukan, yang dimasukkan oleh PPR; entiti
     * yang Buku Kerja MPQ-nya sudah diterima tidak sepatutnya hilang daripada
     * kiraan ini kerana menunggu pegawai lain. "Set Semula" mengosongkan
     * kedua-dua medan itu, jadi entiti yang ditetapkan semula tercicir
     * sendiri tanpa penyingkiran tambahan.
     *
     * DALAM PROSES menuntut kesemua tiga syarat peringkat 1.2 untuk meneruskan
     * — Tarikh Daftar, Status Borang Pendaftaran Data dan seorang Pegawai
     * Analisis yang ditugaskan — TOLAK entiti yang peringkat 5-nya sudah
     * Selesai, kerana entiti siap bukan lagi entiti yang sedang berjalan.
     *
     * SELESAI ialah peringkat 5 berstatus Selesai, dan tiada yang lain.
     *
     * Setiap set ialah senarai kod entiti UNIK yang dipotong dengan entiti
     * yang boleh diakses pengguna, jadi baris berbilang (khususnya sejarah
     * `entiti_assignment`) tidak boleh menggelembungkan sebarang kiraan.
     *
     * @param  Collection<int, string>  $semuaEntiti
     * @param  Collection<int, WorkflowStageStatus>  $pendaftaran  baris peringkat 1.1
     * @return array{diterima: int, dalamProses: int, selesai: int}
     */
    public function kadEntiti(User $pengguna, Collection $semuaEntiti, Collection $pendaftaran): array
    {
        if ($semuaEntiti->isEmpty()) {
            return ['diterima' => 0, 'dalamProses' => 0, 'selesai' => 0];
        }

        $diterima = $this->kodMedanLanjutDirekod($pendaftaran, AliranKerja::PENERIMAAN_DATA)
            ->intersect($semuaEntiti)
            ->values();

        if ($diterima->isEmpty()) {
            return ['diterima' => 0, 'dalamProses' => 0, 'selesai' => 0];
        }

        $berdaftar = $this->kodMedanLanjutDirekod(
            $this->barisPeringkat(AliranKerja::PENDAFTARAN_DATA),
            AliranKerja::PENDAFTARAN_DATA,
        );

        // Skop `active()` yang SAMA digunakan oleh KemajuanAnalisisService
        // apabila ia memutuskan sama ada peringkat 1.2 boleh diteruskan.
        // unique(): satu entiti boleh mempunyai beberapa baris penugasan
        // (sejarah tukar ganti), dan hanya entitinya dikira di sini.
        $adaPegawaiAnalisis = EntitiAssignment::query()
            ->accessibleBy($pengguna)
            ->active()
            ->pluck('agency_code')
            ->unique();

        $peringkatLimaSelesai = WorkflowStageStatus::query()
            ->atStage(AliranKerja::SEMAKAN_KELULUSAN)
            ->selesai()
            ->pluck('agency_code')
            ->unique();

        return [
            'diterima' => $diterima->count(),
            'dalamProses' => $diterima
                ->intersect($berdaftar)
                ->intersect($adaPegawaiAnalisis)
                ->diff($peringkatLimaSelesai)
                ->count(),
            'selesai' => $diterima->intersect($peringkatLimaSelesai)->count(),
        ];
    }

    /**
     * Kod entiti yang telah merekodkan KESEMUA medan `syarat_lanjut` peringkat
     * yang diberi.
     *
     * Peraturan "medan telah direkod" datang daripada KemajuanAnalisisService
     * — servis yang sama yang menguatkuasakannya pada aliran kerja — supaya
     * papan pemuka tidak boleh memberi jawapan yang berbeza daripada skrin
     * Kemajuan Analisis bagi entiti yang sama.
     *
     * @param  Collection<int, WorkflowStageStatus>  $baris
     * @return Collection<int, string>
     */
    private function kodMedanLanjutDirekod(Collection $baris, string $stage): Collection
    {
        return $baris
            ->filter(fn (WorkflowStageStatus $rekod) => $this->kemajuan->medanLanjutBelumDirekod($rekod, $stage) === [])
            ->pluck('agency_code')
            ->unique()
            ->values();
    }

    /**
     * Baris peringkat 1.1 ("Penerimaan Data") bagi setiap entiti yang pernah
     * memasuki aliran kerja.
     *
     * TIDAK ditapis mengikut capaian: senarai entiti yang ditetapkan semula
     * ialah penyingkiran global yang kemudiannya dipotong dengan set entiti
     * pengguna, jadi menapisnya dua kali di sini tidak mengubah hasil tetapi
     * boleh membiarkan baris tertinggal dikira sebagai entiti dipantau.
     *
     * @return Collection<int, WorkflowStageStatus>
     */
    public function peringkatPendaftaran(): Collection
    {
        return $this->barisPeringkat(AliranKerja::PENERIMAAN_DATA);
    }

    /**
     * Baris satu peringkat bagi setiap entiti yang pernah memasuki aliran
     * kerja, berserta medan tangkapan peringkat itu.
     *
     * Lajur diambil daripada AliranKerja::medan() dan bukan disenaraikan di
     * sini, supaya menambah medan pada satu peringkat tidak memerlukan
     * suntingan kedua dalam servis ini.
     *
     * @return Collection<int, WorkflowStageStatus>
     */
    public function barisPeringkat(string $stage): Collection
    {
        return WorkflowStageStatus::query()
            ->atStage($stage)
            ->get(array_merge(['agency_code', 'status'], array_keys(AliranKerja::medan($stage))));
    }

    /**
     * Entiti yang telah ditetapkan semula oleh Ketua Bahagian.
     *
     * "Set Semula" menarik entiti keluar daripada aliran kerja tetapi
     * MENGEKALKAN baris peringkat dan baris kedudukannya, supaya jejak
     * auditnya kekal bermakna. Tanpa penyingkiran eksplisit di sini, baris
     * yang tertinggal itu terus dikira sebagai entiti dipantau yang "Dalam
     * Proses" — angka papan pemuka tidak akan turun selepas Set Semula.
     *
     * Baris peringkat hanya wujud melalui pendaftaran (lihat
     * KemajuanAnalisisService::sediakan), jadi baris peringkat 1.1 yang BUKAN
     * Selesai bermakna satu perkara sahaja: entiti itu telah ditetapkan
     * semula. Entiti yang tidak pernah didaftarkan langsung tiada baris
     * peringkat, jadi ia tidak tersentuh dan kekal dikira "belum didaftar".
     *
     * @param  Collection<int, WorkflowStageStatus>  $pendaftaran  baris peringkat 1.1
     * @return Collection<int, string>
     */
    public function kodDitetapkanSemula(Collection $pendaftaran): Collection
    {
        return $pendaftaran
            ->where('status', '!=', WorkflowStageStatus::SELESAI)
            ->pluck('agency_code');
    }
}
