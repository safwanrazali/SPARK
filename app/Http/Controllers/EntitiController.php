<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\StatusTigaLaporanService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;

/**
 * FASA 5 — pusat maklumat entiti (spesifikasi bahagian 13).
 *
 * Halaman ini ialah HELAIAN REKOD entiti: apa yang telah direkodkan
 * setakat ini, dan tiada lagi. Ia SENGAJA tidak membawa stepper, borang
 * tindakan atau sejarah peringkat — ketiga-tiganya milik halaman Kemajuan
 * (WorkflowController::show), dan memaparkannya di kedua-dua tempat
 * menjadikan halaman ini salinan kedua yang tiada sesiapa perlukan.
 *
 * Pembahagiannya:
 *   Kemajuan         di mana kerja DILAKUKAN — stepper, borang, sejarah
 *   Maklumat Entiti  apa yang TELAH direkod — status setiap Borang, setiap
 *                    No. Rujukan, penugasan, dapatan dan status laporan
 *
 * Tiada data baharu dikira di sini. Setiap query ditapis mengikut kawalan
 * akses Fasa 4 dan route dilindungi middleware `entity.access`.
 */
class EntitiController extends Controller
{
    public function __construct(
        private readonly EntityAccessService $access,
        private readonly EntityAssignmentService $assignments,
        private readonly KemajuanAnalisisService $kemajuan,
        private readonly StatusTigaLaporanService $statusLaporan,
    ) {}

    public function show(Request $request, string $agencyCode)
    {
        $pengguna = $request->user();

        // Lapisan kawalan akses kedua selepas middleware route.
        $this->access->authorize($pengguna, $agencyCode);

        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        /*
        | Baris peringkat ialah SATU-SATUNYA sumber kedudukan pada halaman
        | ini. `workflow_status` tidak dibaca langsung: nilainya diterbitkan
        | daripada baris yang sama (lihat KemajuanAnalisisService::
        | selaraskanKedudukan), jadi membacanya di sini hanya menambah
        | sumber kedua yang boleh terpesong.
        */
        $peringkat = $this->kemajuan->peringkat($agencyCode);

        // Kedua-dua relasi ini dipaparkan pada setiap baris jadual Status
        // Borang dan No. Rujukan; dimuatkan sekali gus supaya jadual tidak
        // mengeluarkan query bagi setiap peringkat.
        $peringkat->load('updatedBy', 'noRujukanOleh');

        $keseluruhan = $this->kemajuan->keseluruhanDaripada($peringkat);

        return view('entiti.show', [
            'entiti' => $entiti,
            'penugasan' => $this->assignments->activeFor($agencyCode),

            // Status sebenar setiap peringkat — sumber kepada jadual Status
            // Borang dan jadual No. Rujukan.
            'peringkat' => $peringkat,

            'peringkatSemasa' => $this->kemajuan->peringkatSemasa($peringkat),
            // telahMemasukiAliran(), bukan dalamAliranKerja(): peringkat 1.1
            // hanya Selesai setelah PPR merekod No. Rujukan, dan menuntutnya
            // di sini memaparkan "Belum Didaftarkan" bagi entiti yang sudah
            // bergerak jauh ke dalam aliran kerja. Selaras dengan senarai
            // Kemajuan Analisis dan halaman Kemajuan Entiti.
            'dalamAliran' => $this->kemajuan->telahMemasukiAliran($peringkat),

            'keseluruhan' => $keseluruhan,
            'badgeKeseluruhan' => match ($keseluruhan) {
                KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
                KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
                default => 'status-tinggi',
            },

            // Diukur terhadap peringkat FASA SEMASA (1.1 hingga 3.1), bukan
            // kelapan-lapan peringkat: 3.2, 4 dan 5 belum dibina, jadi
            // mengukur terhadapnya memaparkan kerja siap sebagai kekurangan
            // yang tiada siapa boleh tutup.
            'jumlahFasa' => $this->kemajuan->jumlahPeringkatSemasa(),
            'siapFasa' => $this->kemajuan->bilanganSelesai($peringkat),

            'analisis' => AnalisisInventori::query()
                ->accessibleBy($pengguna)
                ->where('agency_code', $agencyCode)
                ->first(),

            // Status ketiga-tiga laporan dikira daripada Kemajuan Analisis
            // Entiti, sumber yang sama seperti halaman Status Tiga Laporan,
            // supaya dua halaman tidak boleh menunjukkan nilai berbeza.
            'statusLaporan' => $this->statusLaporan->untukEntiti($agencyCode),
        ]);
    }
}
