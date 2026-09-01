<?php

namespace App\Services;

use App\Models\LaporanSemakan;
use App\Models\StatusLaporan;
use App\Models\WorkflowStageStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Satu-satunya tempat status Tiga Laporan dikira.
 *
 * Status ini TIDAK PERNAH disimpan dan tidak boleh diubah dari halaman Status
 * Tiga Laporan. Ia dikira semula setiap kali daripada Kemajuan Analisis Entiti,
 * supaya tiada dua sumber kebenaran bagi soalan yang sama:
 *
 *   Kedudukan laporan (laporan_semakan)      →  status paparan
 *   ------------------------------------------------------------
 *   Draf                                     →  Dalam Proses
 *   Dihantar kepada PPA                      →  Dalam Semakan
 *   Dihantar kepada KB                       →  Dalam Semakan
 *   Dikembalikan                             →  Dalam Semakan
 *   Sah (KB telah menekan "Sahkan")          →  Selesai
 *
 * Bagi jenis laporan yang belum mempunyai kitaran semakannya sendiri, status
 * mengikut kemajuan peringkat FASA SEMASA entiti itu (Belum Mula / Dalam
 * Proses / Siap). Dengan itu setiap lajur tetap bergerak mengikut Kemajuan
 * Analisis Entiti dan bukan mengikut tindakan berasingan pada halaman paparan.
 *
 * Kitaran semakan laporan (laporan_semakan) milik peringkat 4 dan 5, yang
 * belum dibina — jadi dalam fasa ini setiap status datang daripada kemajuan
 * peringkat. Pemetaan daripada kitaran itu DIKEKALKAN kerana rekod yang
 * dicipta sebelum restruktur masih wujud dan mesti terus dibaca dengan betul.
 */
class StatusTigaLaporanService
{
    public function __construct(private readonly KemajuanAnalisisService $kemajuan) {}

    /**
     * Kedudukan semakan laporan → label paparan.
     *
     * "Dikembalikan" kekal Dalam Semakan: laporan masih berada dalam kitaran
     * PA → PPA → KB, cuma giliran membetulkannya kembali kepada PA. Pemetaan
     * ini sengaja sama dengan LaporanSemakan::PAPARAN kecuali dua label yang
     * memang berbeza perbendaharaan antara dua modul.
     *
     * @var array<string, string>
     */
    private const DARI_LAPORAN = [
        LaporanSemakan::DRAF => StatusLaporan::PAPARAN_DALAM_PROSES,
        LaporanSemakan::MENUNGGU_PPA => StatusLaporan::PAPARAN_DALAM_SEMAKAN,
        LaporanSemakan::MENUNGGU_KB => StatusLaporan::PAPARAN_DALAM_SEMAKAN,
        LaporanSemakan::DIKEMBALIKAN => StatusLaporan::PAPARAN_DALAM_SEMAKAN,
        LaporanSemakan::SAH => StatusLaporan::PAPARAN_SELESAI,
    ];

    /**
     * Status keseluruhan entiti → label paparan, bagi jenis laporan yang belum
     * mempunyai rekod semakan sendiri.
     *
     * @var array<string, string>
     */
    private const DARI_KESELURUHAN = [
        KemajuanAnalisisService::KESELURUHAN_BELUM_MULA => StatusLaporan::PAPARAN_BELUM_BERMULA,
        KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => StatusLaporan::PAPARAN_DALAM_PROSES,
        KemajuanAnalisisService::KESELURUHAN_SIAP => StatusLaporan::PAPARAN_SELESAI,
    ];

    /**
     * Status ketiga-tiga laporan bagi satu entiti.
     *
     * @return array<string, array{status: string, kelas: string, kemas_kini: Carbon|null}>
     */
    public function untukEntiti(string $agencyCode): array
    {
        return $this->untukBanyak([$agencyCode])->get($agencyCode, []);
    }

    /**
     * Versi senarai — dua query sahaja, tanpa mengira berapa banyak entiti.
     *
     * @param  array<int, string>  $agencyCodes
     * @return Collection<string, array<string, array{status: string, kelas: string, kemas_kini: Carbon|null}>>
     */
    public function untukBanyak(array $agencyCodes): Collection
    {
        $agencyCodes = array_values(array_unique(array_filter($agencyCodes)));

        if ($agencyCodes === []) {
            return collect();
        }

        $laporan = LaporanSemakan::query()
            ->whereIn('agency_code', $agencyCodes)
            ->get()
            ->groupBy('agency_code');

        $peringkat = $this->kemajuan->peringkatUntukBanyak($agencyCodes);

        return collect($agencyCodes)->mapWithKeys(fn (string $kod) => [
            $kod => $this->bagiEntiti(
                $laporan->get($kod) ?? collect(),
                $peringkat->get($kod),
            ),
        ]);
    }

    /**
     * @param  Collection<int, LaporanSemakan>  $laporan
     * @param  Collection<int, WorkflowStageStatus>|null  $peringkat
     * @return array<string, array{status: string, kelas: string, kemas_kini: Carbon|null}>
     */
    private function bagiEntiti(Collection $laporan, ?Collection $peringkat): array
    {
        $keseluruhan = $this->kemajuan->keseluruhanDaripada($peringkat);
        $asas = self::DARI_KESELURUHAN[$keseluruhan] ?? StatusLaporan::PAPARAN_BELUM_BERMULA;

        // Tarikh rujukan bagi jenis tanpa rekod semakan: kali terakhir
        // mana-mana peringkat entiti ini bergerak.
        $kemasKiniPeringkat = $peringkat?->max('updated_at');

        $hasil = [];

        foreach (array_keys(StatusLaporan::JENIS) as $jenis) {
            // Jenis yang modulnya belum wujud tidak mempunyai status langsung.
            if (! StatusLaporan::jenisAktif($jenis)) {
                $hasil[$jenis] = [
                    'status' => StatusLaporan::PAPARAN_TIADA,
                    'kelas' => null,
                    'kemas_kini' => null,
                ];

                continue;
            }

            $rekod = $laporan->firstWhere('report_type', $jenis);

            $status = $rekod === null
                ? $asas
                : (self::DARI_LAPORAN[$rekod->status] ?? StatusLaporan::PAPARAN_BELUM_BERMULA);

            $hasil[$jenis] = [
                'status' => $status,
                'kelas' => StatusLaporan::badgePaparan($status),
                'kemas_kini' => $rekod?->updated_at ?? $kemasKiniPeringkat,
            ];
        }

        return $hasil;
    }
}
