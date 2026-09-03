<?php

namespace App\Services;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Models\User;

/**
 * Simpanan MUKTAMAD dapatan Analisis Inventori Kriptografi.
 *
 * Dipisahkan daripada AnalisisInventoriController kerana satu "Simpan
 * Dapatan" menyentuh EMPAT rekod — dapatan itu sendiri, penutupan draf,
 * jejak audit dan baris kehadiran status laporan — dan keempat-empatnya
 * mesti berlaku bersama. Mengumpulkannya di sini menjadikan urutan itu boleh
 * dibaca sebagai satu perkara, bukan sebagai ekor kaedah controller.
 *
 * Laluan DRAF tidak melalui sini: ia milik AnalisisDraftService dan sengaja
 * lebih longgar (kod rujukan separa dibenarkan).
 *
 * TIADA peraturan berubah semasa pemisahan.
 */
class AnalisisSimpananService
{
    public function __construct(
        private readonly AnalisisDraftService $draf,
        private readonly AuditTrailService $audit,
    ) {}

    /**
     * @param  array<string, string>  $sektor  baris sektor daripada senarai induk
     * @param  array<string, string>  $agensi  baris agensi daripada senarai induk
     * @param  array<string, mixed>  $lajur  lajur model daripada BorangAnalisis::kepadaModel()
     * @param  array<string, mixed>  $data  dapatan berstruktur
     */
    public function simpanMuktamad(
        array $sektor,
        array $agensi,
        string $sectorCode,
        array $lajur,
        array $data,
        User $user,
    ): AnalisisInventori {
        $sedia = AnalisisInventori::where('agency_code', $agensi['code'])->first();
        $wujudSebelum = $sedia !== null;
        $selesaiSebelum = (bool) $sedia?->selesai;

        $analisis = AnalisisInventori::updateOrCreate(
            ['agency_code' => $agensi['code']],
            $lajur + [
                'sector_code' => $sectorCode,
                'sector_name' => $sektor['name'],
                'agency_name' => $agensi['name'],
                // Menekan "Hantar" ialah pengisytiharan siap; tiada lagi
                // kotak semak berasingan untuk ditanda (atau terlupa ditanda).
                'data' => $data,
                'selesai' => true,
                'user_id' => $user->id,
            ],
        );

        // Dapatan telah masuk ke rekod sebenar — draf tidak lagi menjadi
        // sumber pemulihan, tetapi versinya dikekalkan sebagai sejarah.
        $this->draf->tutupDraf($analisis);

        // FASA 8 — simpanan muktamad direkodkan. Kandungan dapatan TIDAK
        // dicatat; hanya perubahan status penyiapan dan kod rujukan.
        $this->audit->rekod(
            ['agency_code' => $agensi['code'], 'agency_name' => $agensi['name']],
            'analysis_saved',
            $wujudSebelum ? ($selesaiSebelum ? 'Selesai' : 'Dalam Proses') : null,
            $analisis->selesai ? 'Selesai' : 'Dalam Proses',
            $user,
            [
                'analisis_inventori_id' => $analisis->id,
                'kod_rujukan' => $analisis->kod_rujukan,
                'status_laporan' => $analisis->status_laporan,
            ],
        );

        // Baris kehadiran sahaja: entiti yang mempunyai dapatan analisis terus
        // muncul dalam senarai pemantauan. Lajur `status` di sini tidak lagi
        // dipaparkan — Status Tiga Laporan dikira daripada Kemajuan Analisis
        // Entiti (lihat App\Services\StatusTigaLaporanService).
        StatusLaporan::firstOrCreate(
            ['agency_code' => $agensi['code'], 'jenis' => 'inventori'],
            [
                'sector_code' => $sectorCode,
                'sector_name' => $sektor['name'],
                'agency_name' => $agensi['name'],
                'status' => 'Dalam Proses',
                'user_id' => $user->id,
            ],
        );

        return $analisis;
    }
}
