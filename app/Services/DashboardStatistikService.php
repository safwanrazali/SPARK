<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AnalisisInventori;
use App\Models\EntitiAssignment;
use App\Models\MuatNaik;
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
 *                      (workflow, penugasan, analisis, status laporan, muat naik),
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
        $jumlahEntiti = $this->semuaEntiti($pengguna, $sectorCode)->count();

        // Peringkat 1.1 dibaca SEKALI sahaja: baris yang sama menjawab
        // "siapa telah selesai mendaftar" dan "siapa telah ditetapkan semula".
        $pendaftaran = $this->peringkatPendaftaran();

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

            'pendaftaranSelesai' => $pendaftaranSelesai,
            'dalamProses' => $dalamProses,
            'selesai' => $selesai,
            'belumMula' => $belumMula,

            // Pendaftaran diukur terhadap KESELURUHAN entiti — itulah liputan.
            'peratusPendaftaranSelesai' => $this->peratus($pendaftaranSelesai, $jumlahEntiti),

            // Kemajuan pula diukur terhadap entiti yang TELAH selesai
            // peringkat 1.1 "Penerimaan Data": entiti yang belum melepasi
            // pintu masuk itu belum boleh bergerak langsung, jadi
            // memasukkannya hanya mencairkan ukuran kemajuan sebenar.
            'peratusDalamProses' => $this->peratus($dalamProses, $pendaftaranSelesai),
            'peratusSelesai' => $this->peratus($selesai, $pendaftaranSelesai),

            // Kadar siap merentas KESELURUHAN entiti — ukuran yang dibaca oleh
            // kedua-dua carta, yang skopnya memang keseluruhan entiti.
            'peratusSelesaiKeseluruhan' => $this->peratus($selesai, $jumlahEntiti),

            'belumDidaftar' => max(0, $jumlahDipantau - $workflow->count()),

            'jumlahLaporan' => $this->jumlahLaporan($pengguna, $entiti),

            'analisisSelesai' => $analisis->where('selesai', true)->count(),

            'kemajuan' => $this->kemajuanKeseluruhan($workflow, $jumlahDipantau),
            'kemajuanTaburan' => $this->kemajuanTaburan($selesai, $dalamProses, $belumMula, $jumlahEntiti),
            'selesaiMengikutSektor' => $this->selesaiMengikutSektor(
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
            ->merge(MuatNaik::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->filter()
            ->unique()
            ->diff($this->kodDitetapkanSemula($pendaftaran))
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
    private function kodDitetapkanSemula(Collection $pendaftaran): Collection
    {
        return $pendaftaran
            ->where('status', '!=', WorkflowStageStatus::SELESAI)
            ->pluck('agency_code');
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
    private function peringkatPendaftaran(): Collection
    {
        return WorkflowStageStatus::query()
            ->atStage(AliranKerja::PENERIMAAN_DATA)
            ->get(['agency_code', 'status']);
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
     * Kemajuan peringkat = peringkat dicapai / peringkat maksimum.
     *
     * Penyebutnya ialah entiti DIPANTAU dan bukan keseluruhan entiti: entiti
     * yang tidak pernah didaftarkan tiada baris peringkat, jadi memasukkannya
     * hanya mencairkan ukuran kedalaman ini kepada sifar. Liputan diukur
     * secara berasingan oleh kad peratusan dan carta Kemajuan Keseluruhan.
     *
     * @param  Collection<int, WorkflowStatus>  $workflow
     */
    private function kemajuanKeseluruhan(Collection $workflow, int $jumlahDipantau): int
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
    private function kemajuanTaburan(int $selesai, int $dalamProses, int $belumMula, int $jumlahEntiti): array
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
    private function selesaiMengikutSektor(
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
                    ->where('status', self::STATUS_SIAP)
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
     * Peratusan bulat, tanpa pembahagian dengan sifar.
     *
     * Penyebut sifar memberi 0 — papan pemuka tidak boleh memaparkan NaN,
     * Infinity atau peratusan yang mengelirukan.
     */
    private function peratus(int $bilangan, int $jumlah): int
    {
        if ($jumlah <= 0 || $bilangan <= 0) {
            return 0;
        }

        return (int) round(($bilangan / $jumlah) * 100);
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
