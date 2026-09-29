<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AnalisisInventori;
use App\Models\EntitiAssignment;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use App\Support\AliranKerja;
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
 * Dua populasi entiti digunakan, dan perbezaannya penting:
 *
 * - Jumlah entiti    : KESELURUHAN entiti dalam senarai induk sistem yang
 *                      boleh diakses pengguna. Inilah penyebut bagi setiap
 *                      peratusan papan pemuka — entiti yang belum disentuh
 *                      langsung tetap sebahagian daripada liputan.
 * - Entiti dipantau  : entiti yang mempunyai sekurang-kurangnya satu rekod
 *                      (workflow, penugasan, analisis, status laporan),
 *                      TOLAK entiti yang telah ditarik keluar daripada aliran
 *                      kerja oleh "Set Semula" Ketua Bahagian. Inilah asas
 *                      kiraan workflow, laporan dan taburan sektor.
 * - Kemajuan entiti  : Belum Mula / Dalam Proses / Siap — perbendaharaan
 *                      KemajuanAnalisisService, yang diselaraskan ke dalam
 *                      lajur `workflow_status.status` pada setiap perubahan
 *                      peringkat (lihat KemajuanAnalisisService::selaraskanKedudukan)
 * - Kemajuan peringkat : jumlah peringkat dicapai / (entiti DIPANTAU × 7) —
 *                      entiti yang tidak pernah didaftarkan tiada baris
 *                      peringkat langsung, jadi ia tidak boleh menokok
 *                      penyebut ukuran kedalaman ini.
 */
class DashboardStatistikService
{
    /**
     * Nilai `workflow_status.status` yang menandakan entiti benar-benar siap.
     *
     * Diselaraskan oleh KemajuanAnalisisService daripada status setiap
     * peringkat; lihat KemajuanAnalisisService::keseluruhanDaripada().
     */
    public const STATUS_SIAP = 'Siap';

    /**
     * Nilai `workflow_status.status` bagi entiti yang kerjanya sedang berjalan.
     *
     * Bukan "semua yang belum siap": entiti yang berdaftar tetapi belum
     * bergerak kekal 'Belum Bermula', dan dikira dalam kategorinya sendiri.
     */
    public const STATUS_DALAM_PROSES = 'Dalam Proses';

    public function __construct(
        private readonly EntityAccessService $access,
        private readonly DashboardKadEntitiService $kad,
        private readonly DashboardTaburanService $taburan,
    ) {}

    /**
     * Kira keseluruhan statistik papan pemuka.
     *
     * @return array<string, mixed>
     */
    public function kira(User $pengguna, ?string $sectorCode = null, ?string $dari = null, ?string $hingga = null): array
    {
        $sectorCode = SektorDirectory::sektorWujud($sectorCode) ? $sectorCode : null;
        [$dari, $hingga] = $this->julatTarikh($dari, $hingga);

        // Penyebut setiap peratusan: keseluruhan entiti dalam senarai induk
        // yang boleh diakses pengguna. Penapis tarikh SENGAJA tidak
        // mengecilkannya — ia menapis pergerakan workflow, bukan kewujudan
        // entiti.
        $semuaEntiti = $this->semuaEntiti($pengguna, $sectorCode);
        $jumlahEntiti = $semuaEntiti->count();

        // Peringkat 1.1 dibaca SEKALI sahaja: baris yang sama menjawab
        // "siapa telah selesai mendaftar" dan "siapa telah ditetapkan semula".
        $pendaftaran = $this->kad->peringkatPendaftaran();

        $entiti = $this->entitiDipantau($pengguna, $sectorCode, $dari, $hingga, $pendaftaran);
        $jumlahDipantau = $entiti->count();

        // Peringkat 1.1 "Penerimaan Data" selesai — takrifan yang sama
        // digunakan oleh KemajuanAnalisisService::penerimaanSelesai():
        // baris peringkat 1.1 berstatus Selesai, dan bukan sekadar wujud.
        $pendaftaranSelesai = $pendaftaran
            ->where('status', WorkflowStageStatus::SELESAI)
            ->pluck('agency_code')
            ->intersect($entiti)
            ->count();

        $workflow = $this->workflowDalamSkop($pengguna, $entiti);

        // "Selesai" bermakna KESEMUA peringkat fasa semasa telah Selesai,
        // bukan sekadar berada pada peringkat terakhir. KemajuanAnalisisService
        // menetapkan status 'Siap' pada baris ini hanya apabila syarat itu
        // dipenuhi, jadi entiti tidak boleh dikira siap lebih awal.
        $selesai = $workflow->where('status', self::STATUS_SIAP)->count();

        // Entiti tanpa baris workflow — atau yang barisnya masih
        // 'Belum Bermula' — BUKAN dalam proses: ia belum bergerak langsung.
        $dalamProses = $workflow->where('status', self::STATUS_DALAM_PROSES)->count();

        $belumMula = max(0, $jumlahEntiti - $selesai - $dalamProses);

        $analisis = $this->analisisDalamSkop($pengguna, $entiti);

        $kad = $this->kad->kadEntiti($pengguna, $semuaEntiti, $pendaftaran);

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
            'jumlahDipantau' => $jumlahDipantau,

            // Tiga kad ringkasan entiti — corong Diterima -> Dalam Proses /
            // Selesai. Penyebutnya SENGAJA berbeza daripada kunci kemajuan di
            // bawah; lihat kadEntiti().
            'entitiDiterima' => $kad['diterima'],
            'entitiDalamProses' => $kad['dalamProses'],
            'entitiSelesai' => $kad['selesai'],
            'peratusEntitiDiterima' => $this->taburan->peratusTepat($kad['diterima'], $jumlahEntiti),
            'peratusEntitiDalamProses' => $this->taburan->peratusTepat($kad['dalamProses'], $kad['diterima']),
            'peratusEntitiSelesai' => $this->taburan->peratusTepat($kad['selesai'], $kad['diterima']),

            // Peringkat 1.1 Selesai SEPENUHNYA (termasuk No. Rujukan PKD).
            // Ukuran yang lebih ketat daripada 'entitiDiterima' dan TIDAK lagi
            // memacu mana-mana kad; dikekalkan kerana ia menjawab soalan yang
            // berlainan daripada "Buku Kerja MPQ diterima".
            'pendaftaranSelesai' => $pendaftaranSelesai,
            'dalamProses' => $dalamProses,
            'selesai' => $selesai,
            'belumMula' => $belumMula,

            // Pendaftaran diukur terhadap KESELURUHAN entiti — itulah liputan.
            'peratusPendaftaranSelesai' => $this->taburan->peratus($pendaftaranSelesai, $jumlahEntiti),

            // Kemajuan pula diukur terhadap entiti yang TELAH selesai
            // peringkat 1.1 "Penerimaan Data": entiti yang belum melepasi
            // pintu masuk itu belum boleh bergerak langsung, jadi
            // memasukkannya hanya mencairkan ukuran kemajuan sebenar.
            'peratusDalamProses' => $this->taburan->peratus($dalamProses, $pendaftaranSelesai),
            'peratusSelesai' => $this->taburan->peratus($selesai, $pendaftaranSelesai),

            // Kadar siap merentas KESELURUHAN entiti — ukuran yang dibaca oleh
            // kedua-dua carta, yang skopnya memang keseluruhan entiti.
            'peratusSelesaiKeseluruhan' => $this->taburan->peratus($selesai, $jumlahEntiti),

            'belumDidaftar' => max(0, $jumlahDipantau - $workflow->count()),

            'jumlahLaporan' => $this->jumlahLaporan($pengguna, $entiti),

            'analisisSelesai' => $analisis->where('selesai', true)->count(),

            'kemajuan' => $this->taburan->kemajuanKeseluruhan($workflow, $jumlahDipantau),
            'kemajuanTaburan' => $this->taburan->kemajuanTaburan($selesai, $dalamProses, $belumMula, $jumlahEntiti),
            'selesaiMengikutSektor' => $this->taburan->selesaiMengikutSektor(
                $pengguna,
                $sectorCode,
                $entiti,
                $workflow,
            ),
        ];
    }

    /**
     * KESELURUHAN entiti dalam senarai induk yang boleh diakses pengguna.
     *
     * Menggunakan semula EntityAccessService — sumber tunggal kebenaran bagi
     * "entiti mana yang boleh dilihat pengguna ini" (Fasa 4) — supaya papan
     * pemuka tidak boleh mendedahkan entiti di luar capaian seseorang, dan
     * tidak mempunyai takrifan "semua entiti" yang tersendiri.
     *
     * @return Collection<int, string>
     */
    private function semuaEntiti(User $pengguna, ?string $sectorCode): Collection
    {
        $entiti = $sectorCode !== null
            ? $this->access->entitiDalamSektorFor($pengguna, $sectorCode)
            : $this->access->entitiFor($pengguna);

        return $entiti->pluck('agency_code')->values();
    }

    /**
     * Entiti yang dipantau dalam skop penapis semasa.
     *
     * @param  Collection<int, WorkflowStageStatus>  $pendaftaran  baris peringkat 1.1
     * @return Collection<int, string>
     */
    private function entitiDipantau(
        User $pengguna,
        ?string $sectorCode,
        ?Carbon $dari,
        ?Carbon $hingga,
        Collection $pendaftaran,
    ): Collection {
        $kod = collect()
            ->merge(WorkflowStatus::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(EntitiAssignment::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(AnalisisInventori::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(StatusLaporan::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->filter()
            ->unique()
            ->diff($this->kad->kodDitetapkanSemula($pendaftaran))
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
     * Rekod analisis inventori dalam skop — satu query, dipakai semula oleh
     * kiraan laporan inventori dan kiraan analisis selesai.
     *
     * @param  Collection<int, string>  $entiti
     * @return Collection<int, AnalisisInventori>
     */
    private function analisisDalamSkop(User $pengguna, Collection $entiti): Collection
    {
        if ($entiti->isEmpty()) {
            return collect();
        }

        return AnalisisInventori::query()
            ->accessibleBy($pengguna)
            ->whereIn('agency_code', $entiti)
            ->get(['agency_code', 'selesai']);
    }

    /**
     * Bilangan laporan yang TELAH DISERAHKAN KEPADA NACSA, bagi setiap jenis
     * dalam StatusLaporan::JENIS.
     *
     * Laporan yang masih dalam kitaran — draf, menunggu PPA/KB, malah yang
     * telah disahkan KB — TIDAK dikira. Hanya laporan yang telah melepasi
     * penyerahan diambil kira. Penyerahan itu milik peringkat 5, yang belum
     * dibina, jadi kiraan ini kekal sifar sehingga modulnya tersedia; jejak
     * lama sebelum restruktur terus dikira supaya sejarah tidak hilang.
     *
     * Penyerahan tidak mengubah sebarang lajur status: `laporan_semakan.status`
     * kekal 'Sah' selepasnya (lihat LaporanSemakanService::rekodPenyerahan).
     * Jejak audit `report_delivered` ialah SATU-SATUNYA rekod bahawa butang itu
     * ditekan, dan metadatanya membawa jenis laporan — jadi ia juga
     * satu-satunya sumber yang boleh mengira mengikut jenis.
     *
     * Entiti berbeza dikira, bukan baris jejak: menekan "Hantar" dua kali pada
     * entiti yang sama tetap satu laporan.
     *
     * @param  Collection<int, string>  $entiti
     * @return array<string, int>
     */
    private function jumlahLaporan(User $pengguna, Collection $entiti): array
    {
        $jumlah = array_fill_keys(array_keys(StatusLaporan::JENIS), 0);

        if ($entiti->isEmpty()) {
            return $jumlah;
        }

        $diserahkan = ActivityLog::query()
            ->accessibleBy($pengguna)
            ->where('action', LaporanSemakanService::ACTION_DELIVERED)
            ->whereIn('agency_code', $entiti)
            ->get(['agency_code', 'metadata']);

        $mengikutJenis = $diserahkan
            // Jejak lama sebelum medan itu wujud diandaikan Inventori — itulah
            // satu-satunya jenis yang pernah boleh diserahkan.
            ->groupBy(fn (ActivityLog $log) => $log->metadata['report_type'] ?? LaporanSemakanService::JENIS_LALAI)
            ->map(fn (Collection $jejak) => $jejak->pluck('agency_code')->unique()->count());

        foreach ($mengikutJenis as $jenis => $bilangan) {
            if (array_key_exists($jenis, $jumlah)) {
                $jumlah[$jenis] = $bilangan;
            }
        }

        return $jumlah;
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
