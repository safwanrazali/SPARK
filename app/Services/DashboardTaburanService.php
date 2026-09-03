<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use App\Support\AliranKerja;
use Illuminate\Support\Collection;

/**
 * Taburan dan peratusan papan pemuka — bar kemajuan, carta Kemajuan Analisis
 * dan carta entiti siap mengikut sektor.
 *
 * Dipisahkan daripada DashboardStatistikService kerana ia mengambil kiraan
 * yang telah dibuat dan mengubahnya menjadi bentuk yang dilukis oleh carta.
 * Servis papan pemuka mengumpul populasi entiti; kelas ini membahagikannya.
 *
 * Perbendaharaan kemajuan (Belum Mula / Dalam Proses / Siap) diambil terus
 * daripada KemajuanAnalisisService — tiada kategori baharu dicipta di sini.
 */
class DashboardTaburanService
{
    public function __construct(
        private readonly EntityAccessService $access,
    ) {}

    /**
     * Kemajuan peringkat = peringkat dicapai / peringkat maksimum.
     *
     * Penyebutnya ialah entiti DIPANTAU dan bukan keseluruhan entiti: entiti
     * yang tidak pernah didaftarkan tiada baris peringkat, jadi memasukkannya
     * hanya mencairkan ukuran kedalaman ini kepada sifar. Liputan diukur
     * secara berasingan oleh kad peratusan dan carta Kemajuan Keseluruhan.
     *
     * @param  Collection<int, WorkflowStatus>  $workflow
     */
    public function kemajuanKeseluruhan(Collection $workflow, int $jumlahDipantau): int
    {
        if ($jumlahDipantau === 0) {
            return 0;
        }

        // Penyebutnya ialah bilangan peringkat FASA SEMASA, bukan kelima-lima
        // peringkat utama: peringkat 4 dan 5 belum dibina, jadi mengukur
        // terhadapnya menjadikan kemajuan penuh mustahil dipaparkan.
        $maksimum = $jumlahDipantau * count(AliranKerja::semasa());

        $dicapai = WorkflowStageStatus::query()
            ->whereIn('agency_code', $workflow->pluck('agency_code'))
            ->fasaSemasa()
            ->selesai()
            ->count();

        return $maksimum === 0 ? 0 : (int) round(($dicapai / $maksimum) * 100);
    }

    /**
     * Taburan entiti merentas tiga keadaan Kemajuan Analisis.
     *
     * Perbendaharaan dan susunannya diambil terus daripada
     * KemajuanAnalisisService — tiada kategori kemajuan baharu dicipta.
     *
     * @return array<int, array{kunci: string, label: string, nilai: int, peratus: int}>
     */
    public function kemajuanTaburan(int $selesai, int $dalamProses, int $belumMula, int $jumlahEntiti): array
    {
        return [
            [
                'kunci' => 'selesai',
                'label' => KemajuanAnalisisService::KESELURUHAN_SIAP,
                'nilai' => $selesai,
                'peratus' => $this->peratus($selesai, $jumlahEntiti),
            ],
            [
                'kunci' => 'proses',
                'label' => KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES,
                'nilai' => $dalamProses,
                'peratus' => $this->peratus($dalamProses, $jumlahEntiti),
            ],
            [
                'kunci' => 'belum',
                'label' => KemajuanAnalisisService::KESELURUHAN_BELUM_MULA,
                'nilai' => $belumMula,
                'peratus' => $this->peratus($belumMula, $jumlahEntiti),
            ],
        ];
    }

    /**
     * Entiti mengikut sektor — SETIAP sektor disenaraikan, termasuk yang
     * belum mempunyai satu pun entiti selesai.
     *
     * Dua angka bagi setiap sektor, dan keduanya diperlukan oleh carta:
     *
     *   jumlah   bilangan entiti sektor itu dalam senarai induk. Inilah SAIZ
     *            hirisan — kesebelas-sebelas hirisan bersama-sama membahagikan
     *            keseluruhan entiti kepada sektornya.
     *   selesai  entiti sektor itu yang telah menamatkan Kemajuan Analisis,
     *            dengan peratusannya diukur terhadap `jumlah` sektor itu
     *            sendiri — inilah kadar SIAP yang dipaparkan pada legenda.
     *
     * Senarai sektor diambil daripada EntityAccessService supaya sektor yang
     * tiada satu pun entiti boleh diakses tidak muncul kepada pengguna itu.
     *
     * @param  Collection<int, string>  $entiti
     * @param  Collection<int, WorkflowStatus>  $workflow
     * @return array<int, array{kod: string, nama: string, jumlah: int, selesai: int, peratus: int}>
     */
    public function selesaiMengikutSektor(
        User $pengguna,
        ?string $sectorCode,
        Collection $entiti,
        Collection $workflow,
    ): array {
        $sektor = $this->access->sektorFor($pengguna);

        if ($sectorCode !== null) {
            $sektor = array_intersect_key($sektor, [$sectorCode => null]);
        }

        $mengikutSektor = [];

        foreach ($sektor as $kod => $butiran) {
            $kodAgensi = collect($butiran['agencies'])->pluck('code');
            $dalamSektor = $entiti->intersect($kodAgensi);

            $selesai = $dalamSektor->isEmpty()
                ? 0
                : $workflow
                    ->whereIn('agency_code', $dalamSektor)
                    ->where('status', DashboardStatistikService::STATUS_SIAP)
                    ->count();

            $jumlah = $kodAgensi->count();

            $mengikutSektor[] = [
                'kod' => (string) $kod,
                'nama' => $butiran['name'],
                'jumlah' => $jumlah,
                'selesai' => $selesai,
                'peratus' => $this->peratus($selesai, $jumlah),
            ];
        }

        return $mengikutSektor;
    }

    /**
     * Peratusan kad corong entiti — SATU tempat perpuluhan.
     *
     * Peratusan lain pada papan pemuka ialah integer, dan itu memadai kerana
     * penyebutnya kecil. Kad "Entiti Diterima" pula diukur terhadap
     * keseluruhan 252 entiti, di mana pembundaran integer melaporkan 2 entiti
     * (0.79%) sebagai 1% — lebih daripada yang sebenarnya ada. Nilai ini juga
     * menjadi lebar bar kemajuan kadnya, jadi angka dan bar sentiasa sepadan.
     *
     * Penyebut sifar memberi 0.0, bukan NaN atau Infinity.
     */
    public function peratusTepat(int $bilangan, int $jumlah): float
    {
        if ($jumlah <= 0 || $bilangan <= 0) {
            return 0.0;
        }

        return round(($bilangan / $jumlah) * 100, 1);
    }

    /**
     * Peratusan bulat, tanpa pembahagian dengan sifar.
     *
     * Penyebut sifar memberi 0 — papan pemuka tidak boleh memaparkan NaN,
     * Infinity atau peratusan yang mengelirukan.
     */
    public function peratus(int $bilangan, int $jumlah): int
    {
        if ($jumlah <= 0 || $bilangan <= 0) {
            return 0;
        }

        return (int) round(($bilangan / $jumlah) * 100);
    }
}
