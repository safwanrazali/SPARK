<?php

namespace App\Services;

use App\Models\AnalisisInventori;
use App\Models\EntitiAssignment;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Support\SektorDirectory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FASA 7 — pengiraan statistik papan pemuka pemantauan.
 *
 * Prinsip spesifikasi bahagian 10: TIADA nilai statistik disimpan atau
 * ditulis secara tetap. Setiap angka dikira daripada rekod sebenar:
 *
 *   Entity records → Status records → Calculation → Dashboard
 *
 * Takrifan yang digunakan (semuanya berasaskan rekod sedia ada):
 * - Entiti dipantau  : entiti yang mempunyai sekurang-kurangnya satu rekod
 *                      (workflow, penugasan, analisis, status laporan, muat naik)
 * - Dalam proses     : workflow pada peringkat 1–6
 * - Selesai          : workflow pada peringkat 7 (Penyerahan & Penutupan)
 * - Kemajuan keseluruhan : jumlah peringkat dicapai / (bilangan entiti × 7)
 */
class DashboardStatistikService
{
    public function __construct(private readonly EntityAccessService $access) {}

    /**
     * Kira keseluruhan statistik papan pemuka.
     *
     * @return array<string, mixed>
     */
    public function kira(User $pengguna, ?string $sectorCode = null, ?string $dari = null, ?string $hingga = null): array
    {
        $sectorCode = SektorDirectory::sektorWujud($sectorCode) ? $sectorCode : null;
        [$dari, $hingga] = $this->julatTarikh($dari, $hingga);

        $entiti = $this->entitiDipantau($pengguna, $sectorCode, $dari, $hingga);
        $jumlahEntiti = $entiti->count();

        $workflow = $this->workflowDalamSkop($pengguna, $entiti);

        $dalamProses = $workflow->whereBetween('current_stage', [
            WorkflowStatus::FIRST_STAGE,
            WorkflowStatus::LAST_STAGE - 1,
        ])->count();

        $selesai = $workflow->where('current_stage', WorkflowStatus::LAST_STAGE)->count();

        $laporan = $this->statistikLaporan($pengguna, $entiti, $jumlahEntiti);

        return [
            'penapis' => [
                'sector_code' => $sectorCode,
                'sector_name' => $sectorCode ? SektorDirectory::sektor()[$sectorCode]['name'] : null,
                'dari' => $dari?->format('Y-m-d'),
                'hingga' => $hingga?->format('Y-m-d'),
                'aktif' => $sectorCode !== null || $dari !== null || $hingga !== null,
            ],

            'jumlahSektor' => $sectorCode !== null ? 1 : count(SektorDirectory::sektor()),
            'jumlahEntiti' => $jumlahEntiti,
            'dalamProses' => $dalamProses,
            'selesai' => $selesai,
            'belumDidaftar' => max(0, $jumlahEntiti - $workflow->count()),

            'jumlahLaporan' => $laporan['jumlah'],
            'laporanSiap' => $laporan['siap'],
            'laporanDalamProses' => $laporan['dalam_proses'],
            'laporanBelum' => $laporan['belum'],

            'analisisSelesai' => AnalisisInventori::query()
                ->accessibleBy($pengguna)
                ->whereIn('agency_code', $entiti)
                ->where('selesai', true)
                ->count(),

            'kemajuan' => $this->kemajuanKeseluruhan($workflow, $jumlahEntiti),
            'taburanWorkflow' => $this->taburanWorkflow($workflow),
            'mengikutSektor' => $this->mengikutSektor($pengguna, $entiti, $workflow),
        ];
    }

    /**
     * Entiti yang dipantau dalam skop penapis semasa.
     *
     * @return Collection<int, string>
     */
    private function entitiDipantau(User $pengguna, ?string $sectorCode, ?Carbon $dari, ?Carbon $hingga): Collection
    {
        $kod = collect()
            ->merge(WorkflowStatus::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(EntitiAssignment::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(AnalisisInventori::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(StatusLaporan::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(MuatNaik::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->filter()
            ->unique()
            ->values();

        if ($sectorCode !== null) {
            $dalamSektor = SektorDirectory::entitiDalamSektor($sectorCode)->pluck('agency_code');
            $kod = $kod->intersect($dalamSektor)->values();
        }

        if ($dari !== null || $hingga !== null) {
            // Penapis tarikh disokong oleh tarikh status workflow — satu-satunya
            // tarikh pemantauan yang direkodkan bagi setiap entiti (Fasa 2).
            $dalamJulat = WorkflowStatus::query()
                ->accessibleBy($pengguna)
                ->when($dari, fn ($q) => $q->where('status_since', '>=', $dari))
                ->when($hingga, fn ($q) => $q->where('status_since', '<=', $hingga))
                ->pluck('agency_code');

            $kod = $kod->intersect($dalamJulat)->values();
        }

        return $kod;
    }

    /**
     * @param  Collection<int, string>  $entiti
     * @return Collection<int, WorkflowStatus>
     */
    private function workflowDalamSkop(User $pengguna, Collection $entiti): Collection
    {
        if ($entiti->isEmpty()) {
            return collect();
        }

        return WorkflowStatus::query()
            ->accessibleBy($pengguna)
            ->whereIn('agency_code', $entiti)
            ->get();
    }

    /**
     * Kemajuan keseluruhan = peringkat dicapai / peringkat maksimum.
     *
     * @param  Collection<int, WorkflowStatus>  $workflow
     */
    private function kemajuanKeseluruhan(Collection $workflow, int $jumlahEntiti): int
    {
        if ($jumlahEntiti === 0) {
            return 0;
        }

        $maksimum = $jumlahEntiti * WorkflowStatus::LAST_STAGE;

        return (int) round(($workflow->sum('current_stage') / $maksimum) * 100);
    }

    /**
     * Taburan entiti merentas 7 peringkat workflow.
     *
     * @param  Collection<int, WorkflowStatus>  $workflow
     * @return array<int, array<string, mixed>>
     */
    private function taburanWorkflow(Collection $workflow): array
    {
        $jumlah = $workflow->count();
        $taburan = [];

        foreach (WorkflowStatus::WORKFLOW_STAGES as $nombor => $nama) {
            $bilangan = $workflow->where('current_stage', $nombor)->count();

            $taburan[] = [
                'peringkat' => $nombor,
                'nama' => $nama,
                'bilangan' => $bilangan,
                'peratus' => $jumlah > 0 ? (int) round(($bilangan / $jumlah) * 100) : 0,
            ];
        }

        return $taburan;
    }

    /**
     * Kiraan laporan. Setiap entiti dipantau mempunyai tiga jenis laporan;
     * rekod yang belum wujud dikira sebagai belum bermula.
     *
     * @param  Collection<int, string>  $entiti
     * @return array<string, int>
     */
    private function statistikLaporan(User $pengguna, Collection $entiti, int $jumlahEntiti): array
    {
        $status = StatusLaporan::query()
            ->accessibleBy($pengguna)
            ->whereIn('agency_code', $entiti)
            ->get();

        $jumlah = $jumlahEntiti * count(StatusLaporan::JENIS);
        $siap = $status->where('status', 'Siap')->count();
        $dalamProses = $status->where('status', 'Dalam Proses')->count();

        return [
            'jumlah' => $jumlah,
            'siap' => $siap,
            'dalam_proses' => $dalamProses,
            'belum' => max(0, $jumlah - $siap - $dalamProses),
        ];
    }

    /**
     * Kemajuan mengikut sektor — hanya sektor yang mempunyai entiti dipantau.
     *
     * @param  Collection<int, string>  $entiti
     * @param  Collection<int, WorkflowStatus>  $workflow
     * @return array<int, array<string, mixed>>
     */
    private function mengikutSektor(User $pengguna, Collection $entiti, Collection $workflow): array
    {
        $mengikutSektor = [];

        foreach (SektorDirectory::sektor() as $kod => $sektor) {
            $kodAgensi = collect($sektor['agencies'])->pluck('code');
            $dalamSektor = $entiti->intersect($kodAgensi);

            if ($dalamSektor->isEmpty()) {
                continue;
            }

            $selesai = $workflow
                ->whereIn('agency_code', $dalamSektor)
                ->where('current_stage', WorkflowStatus::LAST_STAGE)
                ->count();

            $mengikutSektor[] = [
                'kod' => $kod,
                'nama' => $sektor['name'],
                'selesai' => $selesai,
                'jumlah' => $dalamSektor->count(),
            ];
        }

        return $mengikutSektor;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function julatTarikh(?string $dari, ?string $hingga): array
    {
        $mula = $this->tarikh($dari)?->startOfDay();
        $akhir = $this->tarikh($hingga)?->endOfDay();

        // Julat terbalik dibetulkan supaya penapis kekal bermakna.
        if ($mula !== null && $akhir !== null && $mula->greaterThan($akhir)) {
            [$mula, $akhir] = [$akhir->copy()->startOfDay(), $mula->copy()->endOfDay()];
        }

        return [$mula, $akhir];
    }

    private function tarikh(?string $nilai): ?Carbon
    {
        if ($nilai === null || trim($nilai) === '') {
            return null;
        }

        try {
            return Carbon::parse($nilai);
        } catch (\Throwable) {
            return null;
        }
    }
}
