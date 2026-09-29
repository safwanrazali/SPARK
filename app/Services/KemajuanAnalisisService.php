<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\ActivityLog;
use App\Models\AnalisisInventori;
use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use App\Support\AliranKerja;
use App\Support\SyaratPeringkat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat status peringkat Kemajuan Analisis Entiti boleh berubah.
 *
 * Peraturan yang dikuatkuasakan di sini:
 * - Setiap entiti memiliki satu baris bagi SETIAP peringkat yang ditakrifkan
 *   dalam AliranKerja (termasuk peringkat fasa akan datang); tiada baris
 *   bermakna entiti belum memasuki aliran kerja langsung.
 * - Satu peringkat hanya boleh ditandakan Selesai apabila peringkat
 *   sebelumnya dalam turutan telah Selesai. Tiada peringkat boleh dilangkau.
 * - Peringkat fasa akan datang (3.2, 4, 5) TIDAK menerima sebarang tindakan.
 *   Ia wujud supaya strukturnya lengkap dan modulnya boleh ditambah kemudian.
 * - Keseluruhan entiti menjadi 'Siap' apabila kesemua peringkat FASA SEMASA
 *   Selesai — iaitu 1.1 hingga 3.1. Mengukurnya terhadap peringkat yang
 *   belum dibina akan menjadikan 'Siap' mustahil dicapai.
 * - `workflow_status` (kedudukan semasa) diselaraskan pada setiap perubahan,
 *   supaya stepper dan papan pemuka kekal tepat.
 *
 * Kebenaran peranan TIDAK disemak di sini; ia dikawal oleh gate pada lapisan
 * route/controller mengikut seni bina sedia ada.
 */
class KemajuanAnalisisService
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly WorkflowTransitionService $workflow,
        private readonly KemajuanAnalisisGating $gating,
        private readonly KemajuanAnalisisRingkasan $ringkasan,
    ) {}

    public const ACTION_STAGE_STATUS_CHANGED = 'stage_status_changed';

    public const ACTION_STAGE_DATA_SAVED = 'stage_data_saved';

    public const ACTION_STAGE_REFERENCE_SAVED = 'stage_reference_saved';

    public const ACTION_REGISTRATION_COMPLETED = 'registration_completed';

    public const ACTION_REGISTRATION_RESET = 'registration_reset';

    /**
     * Keseluruhan: entiti belum memasuki aliran kerja analisis.
     */
    public const KESELURUHAN_BELUM_MULA = 'Belum Mula';

    public const KESELURUHAN_DALAM_PROSES = 'Dalam Proses';

    public const KESELURUHAN_SIAP = 'Siap';

    /**
     * Cipta baris bagi setiap peringkat aliran kerja, semuanya 'Belum Mula'.
     *
     * Selamat dipanggil berulang kali: baris sedia ada tidak disentuh, jadi
     * pemasukan semula tidak memadam kemajuan yang telah dicapai. Ia juga
     * menambah baris bagi peringkat yang BAHARU ditakrifkan — itulah cara
     * peringkat fasa akan datang muncul pada entiti yang telah lama wujud.
     *
     * @param  array<string, string>  $entiti  sector_code, sector_name, agency_code, agency_name
     * @return Collection<string, WorkflowStageStatus> dikunci mengikut kunci peringkat
     */
    public function sediakan(array $entiti): Collection
    {
        // Baris pengepala `workflow_status` dicipta melalui servis sedia ada
        // supaya kemasukan entiti ke dalam workflow kekal muncul dalam jejak
        // audit dengan tindakan yang sama seperti sebelum ini.
        $this->workflow->initialize($entiti);

        foreach (AliranKerja::kekunci() as $stage) {
            WorkflowStageStatus::firstOrCreate(
                ['agency_code' => $entiti['agency_code'], 'stage' => $stage],
                [
                    'agency_name' => $entiti['agency_name'],
                    'sector_code' => $entiti['sector_code'],
                    'sector_name' => $entiti['sector_name'],
                    'status' => WorkflowStageStatus::BELUM_MULA,
                ],
            );
        }

        return $this->peringkat($entiti['agency_code']);
    }

    /**
     * Status setiap peringkat bagi satu entiti, dikunci mengikut kunci
     * peringkat dan disusun mengikut turutan aliran.
     *
     * Susunan dibuat dalam PHP dan bukan melalui `orderBy('stage')`: kunci
     * ialah string, dan susunan abjadnya salah ('1.10' sebelum '1.2').
     *
     * @return Collection<string, WorkflowStageStatus>
     */
    public function peringkat(string $agencyCode): Collection
    {
        return $this->susun(
            WorkflowStageStatus::query()->forAgency($agencyCode)->get()
        );
    }

    /**
     * Status setiap peringkat bagi banyak entiti sekali gus.
     *
     * Senarai entiti memaparkan kemajuan setiap baris; tanpa ini setiap baris
     * mengeluarkan satu query sendiri.
     *
     * @param  array<int, string>  $agencyCodes
     * @return Collection<string, Collection<string, WorkflowStageStatus>>
     */
    public function peringkatUntukBanyak(array $agencyCodes): Collection
    {
        if ($agencyCodes === []) {
            return collect();
        }

        return WorkflowStageStatus::query()
            ->whereIn('agency_code', $agencyCodes)
            ->get()
            ->groupBy('agency_code')
            ->map(fn (Collection $peringkat) => $this->susun($peringkat));
    }

    /**
     * @param  Collection<int, WorkflowStageStatus>  $peringkat
     * @return Collection<string, WorkflowStageStatus>
     */
    private function susun(Collection $peringkat): Collection
    {
        return $peringkat
            ->sortBy(fn (WorkflowStageStatus $p): int => AliranKerja::ordinal($p->stage) ?? PHP_INT_MAX)
            ->keyBy('stage');
    }

    /**
     * Adakah entiti telah memasuki aliran kerja (peringkat 1.1 Selesai)?
     *
     * Ini ialah pintu masuk kepada keseluruhan aliran: sebelum ia benar,
     * entiti tidak muncul kepada PPA dan tidak boleh ditugaskan.
     */
    public function penerimaanSelesai(string $agencyCode): bool
    {
        return WorkflowStageStatus::query()
            ->forAgency($agencyCode)
            ->atStage(AliranKerja::PENERIMAAN_DATA)
            ->selesai()
            ->exists();
    }

    /**
     * Adakah entiti ini berada dalam aliran kerja (peringkat 1.1 Selesai),
     * dijawab daripada peringkat yang telah dimuatkan.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function dalamAliranKerja(?Collection $peringkat): bool
    {
        return $this->ringkasan->dalamAliranKerja($peringkat);
    }

    /**
     * Adakah entiti ini telah MEMASUKI aliran kerja (peringkat 1.1 bermula)?
     *
     * Soalan yang BERBEZA daripada dalamAliranKerja(); lihat
     * KemajuanAnalisisRingkasan bagi sebab perbezaannya.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function telahMemasukiAliran(?Collection $peringkat): bool
    {
        return $this->ringkasan->telahMemasukiAliran($peringkat);
    }

    /**
     * Adakah "Status Laporan" berkenaan bagi entiti ini?
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function statusLaporanBerkenaan(?Collection $peringkat): bool
    {
        return $this->ringkasan->statusLaporanBerkenaan($peringkat);
    }

    /**
     * Kod entiti yang telah memasuki aliran kerja.
     *
     * Entiti yang ditetapkan semula oleh Ketua Bahagian tidak termasuk —
     * peringkat 1.1-nya kembali kepada Belum Mula.
     *
     * @return array<int, string>
     */
    public function kodPenerimaanSelesai(?User $pengguna = null): array
    {
        return WorkflowStageStatus::query()
            ->when($pengguna !== null, fn ($query) => $query->accessibleBy($pengguna))
            ->atStage(AliranKerja::PENERIMAAN_DATA)
            ->selesai()
            ->pluck('agency_code')
            ->all();
    }

    /**
     * Bolehkah peringkat ini ditetapkan kepada $status sekarang?
     *
     * Peraturannya dikuatkuasakan oleh KemajuanAnalisisGating; peringkat
     * dimuatkan di sini supaya kelas itu tidak perlu menyoal sendiri.
     */
    public function ralatPeringkat(
        string $agencyCode,
        string $stage,
        string $status = WorkflowStageStatus::SELESAI,
    ): ?string {
        return $this->gating->ralat($agencyCode, $this->peringkat($agencyCode), $stage, $status);
    }

    /**
     * Bolehkah peringkat ini ditandakan Selesai sekarang?
     */
    public function bolehTandakan(string $agencyCode, string $stage): bool
    {
        return $this->ralatPeringkat($agencyCode, $stage) === null;
    }

    /**
     * Medan `syarat_selesai` yang MASIH TIADA pada peringkat ini.
     *
     * @return array<int, string>
     */
    public function medanBelumLengkap(?WorkflowStageStatus $rekod, string $stage): array
    {
        return SyaratPeringkat::medanBelumLengkap($rekod, $stage);
    }

    /**
     * Kod entiti yang Buku Kerja MPQ-nya TELAH DITERIMA — Tarikh Terima dan
     * Status Borang Penerimaan Data kedua-duanya direkod — disusun daripada
     * yang PALING BARU dikemas kini.
     *
     * Ini takrifan yang SAMA dengan kad "Entiti Diterima" pada papan pemuka:
     * medan `syarat_lanjut` peringkat 1.1, bukan status peringkat 1.1.
     * Status Selesai turut menuntut No. Rujukan, yang dimasukkan oleh PKD —
     * entiti yang bukunya sudah diterima tidak sepatutnya hilang daripada
     * senarai kerana menunggu pegawai lain.
     *
     * Susunan datang daripada `updated_at` baris peringkat 1.1, iaitu masa
     * penerimaan itu direkod atau dipinda. Penapisan medan dibuat dalam PHP
     * dan bukan dalam SQL supaya peraturan "medan telah direkod" kekal SATU
     * (@see medanLanjutBelumDirekod) dan tidak terpesong menjadi versi SQL
     * yang berasingan.
     *
     * @param  User|null  $pengguna  hadkan kepada entiti yang boleh diaksesnya
     * @return array<int, string>
     */
    public function kodDiterima(?User $pengguna = null): array
    {
        return WorkflowStageStatus::query()
            ->when($pengguna, fn ($q) => $q->accessibleBy($pengguna))
            ->atStage(AliranKerja::PENERIMAAN_DATA)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (WorkflowStageStatus $rekod) => $this->medanLanjutBelumDirekod(
                $rekod,
                AliranKerja::PENERIMAAN_DATA,
            ) === [])
            ->pluck('agency_code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Medan `syarat_lanjut` peringkat ini yang MASIH TIADA — medan yang mesti
     * ADA sebelum peringkat SETERUSNYA boleh dimulakan.
     *
     * Awam kerana papan pemuka bertanya soalan yang SAMA secara pukal.
     *
     * @return array<int, string>
     */
    public function medanLanjutBelumDirekod(?WorkflowStageStatus $rekod, string $stage): array
    {
        return SyaratPeringkat::medanLanjutBelumDirekod($rekod, $stage);
    }

    /**
     * Medan peringkat yang MASIH TIADA sebelum No. Rujukannya boleh direkod.
     *
     * @return array<int, string>
     */
    public function medanSebelumRujukan(?WorkflowStageStatus $rekod, string $stage): array
    {
        return SyaratPeringkat::medanSebelumRujukan($rekod, $stage);
    }

    /**
     * Bolehkah No. Rujukan peringkat ini direkod sekarang?
     */
    public function rujukanTersedia(?WorkflowStageStatus $rekod, string $stage): bool
    {
        return SyaratPeringkat::rujukanTersedia($rekod, $stage);
    }

    /**
     * Adakah penugasan Pegawai Analisis masih tertunggak bagi peringkat ini?
     */
    private function penugasanTertunggak(string $agencyCode, string $stage): bool
    {
        return $this->gating->penugasanTertunggak($agencyCode, $stage);
    }

    /**
     * Terbitkan semula status satu peringkat daripada keadaan semasanya.
     *
     * Dipanggil dari luar apabila sesuatu YANG BUKAN medan peringkat berubah
     * dan boleh menjejaskan statusnya — khususnya penugasan Pegawai Analisis,
     * yang merupakan salah satu syarat Selesai peringkat 1.2.
     */
    public function terbitkanSemula(string $agencyCode, string $stage, ?User $user = null): void
    {
        $this->terbitkanStatus($agencyCode, $stage, $user);
    }

    /**
     * Terbitkan semula status setiap peringkat yang bergantung kepada
     * penugasan Pegawai Analisis.
     */
    public function terbitkanSemulaBergantungPenugasan(string $agencyCode, ?User $user = null): void
    {
        foreach (AliranKerja::semasa() as $stage) {
            if (AliranKerja::perluPenugasanUntukSelesai($stage)) {
                $this->terbitkanStatus($agencyCode, $stage, $user);
            }
        }
    }

    /**
     * Terbitkan semula status satu peringkat daripada datanya.
     *
     * Hanya terpakai pada peringkat yang mempunyai `syarat_selesai`. Bagi
     * peringkat itu, status BUKAN sesuatu yang ditetapkan oleh butang — ia
     * jawapan kepada "adakah datanya lengkap":
     *
     *   semua medan ada      → Selesai
     *   sebahagian ada       → Dalam Proses
     *   tiada satu pun       → Belum Mula
     *
     * Dipanggil setiap kali data atau No. Rujukan peringkat itu berubah,
     * supaya status tidak pernah terpisah daripada datanya.
     */
    private function terbitkanStatus(string $agencyCode, string $stage, ?User $user = null): void
    {
        if (! AliranKerja::statusDiterbitkan($stage)) {
            return;
        }

        $rekod = WorkflowStageStatus::query()
            ->forAgency($agencyCode)
            ->atStage($stage)
            ->first();

        if ($rekod === null) {
            return;
        }

        // Penugasan dikira sebagai satu syarat tambahan di sebelah medan,
        // supaya "berapa banyak syarat sudah dipenuhi" kekal satu kiraan.
        $perluPenugasan = AliranKerja::perluPenugasanUntukSelesai($stage);

        $jumlahSyarat = count(AliranKerja::syaratSelesai($stage)) + ($perluPenugasan ? 1 : 0);
        $jumlahTiada = count($this->medanBelumLengkap($rekod, $stage))
            + ($this->penugasanTertunggak($agencyCode, $stage) ? 1 : 0);

        $baharu = match (true) {
            $jumlahTiada === 0 => WorkflowStageStatus::SELESAI,
            $jumlahTiada < $jumlahSyarat => WorkflowStageStatus::DALAM_PROSES,
            default => WorkflowStageStatus::BELUM_MULA,
        };

        if ($rekod->status === $baharu) {
            return;
        }

        $sebelum = $rekod->status;

        $rekod->status = $baharu;

        if ($baharu === WorkflowStageStatus::BELUM_MULA) {
            $rekod->started_at = null;
            $rekod->completed_at = null;
        } else {
            $rekod->started_at ??= now();
            $rekod->completed_at = $baharu === WorkflowStageStatus::SELESAI ? now() : null;
        }

        $rekod->save();

        $this->selaraskanKedudukan($agencyCode, $user);

        $this->audit->rekod(
            ['agency_code' => $rekod->agency_code, 'agency_name' => $rekod->agency_name],
            self::ACTION_STAGE_STATUS_CHANGED,
            $sebelum,
            $baharu,
            $user,
            [
                'stage' => $stage,
                'stage_name' => AliranKerja::labelPenuh($stage),
                'diterbitkan' => true,
                'keseluruhan' => $this->keseluruhan($agencyCode),
            ],
        );
    }

    /**
     * Tetapkan status satu peringkat.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function tetapkanStatus(
        string $agencyCode,
        string $stage,
        string $status,
        ?User $user = null,
        ?string $notes = null,
    ): WorkflowStageStatus {
        if (! WorkflowStageStatus::isValidStatus($status)) {
            throw new InvalidWorkflowTransitionException(sprintf(
                'Status "%s" tidak sah. Status yang dibenarkan: %s.',
                $status,
                implode(', ', WorkflowStageStatus::STATUSES),
            ));
        }

        // Peringkat berderivasi tiada status yang boleh "ditetapkan": statusnya
        // ialah jawapan kepada kelengkapan datanya. Membenarkan penetapan
        // manual akan mencipta sumber kebenaran kedua yang boleh bercanggah
        // dengan data pada baris yang sama.
        if (AliranKerja::statusDiterbitkan($stage)) {
            throw new InvalidWorkflowTransitionException(sprintf(
                'Status peringkat %s diterbitkan daripada datanya dan tidak boleh ditetapkan terus. '
                .'Lengkapkan %s.',
                AliranKerja::labelPenuh($stage),
                implode(', ', array_map(
                    fn (string $lajur) => AliranKerja::labelMedan($stage, $lajur),
                    AliranKerja::syaratSelesai($stage),
                )),
            ));
        }

        $ralat = $this->ralatPeringkat($agencyCode, $stage, $status);

        if ($ralat !== null) {
            throw new InvalidWorkflowTransitionException($ralat);
        }

        return DB::transaction(function () use ($agencyCode, $stage, $status, $user, $notes) {
            $rekod = WorkflowStageStatus::query()
                ->forAgency($agencyCode)
                ->atStage($stage)
                ->lockForUpdate()
                ->firstOrFail();

            $sebelum = $rekod->status;

            if ($sebelum === $status) {
                return $rekod;
            }

            $rekod->status = $status;
            $rekod->updated_by_user_id = $user?->id;

            if ($notes !== null && $notes !== '') {
                $rekod->notes = $notes;
            }

            if ($status === WorkflowStageStatus::DALAM_PROSES && $rekod->started_at === null) {
                $rekod->started_at = now();
            }

            if ($status === WorkflowStageStatus::SELESAI) {
                $rekod->started_at ??= now();
                $rekod->completed_at = now();
            } else {
                $rekod->completed_at = null;
            }

            $rekod->save();

            $this->selaraskanKedudukan($agencyCode, $user);

            $this->audit->rekod(
                ['agency_code' => $rekod->agency_code, 'agency_name' => $rekod->agency_name],
                self::ACTION_STAGE_STATUS_CHANGED,
                $sebelum,
                $status,
                $user,
                [
                    'stage' => $stage,
                    'stage_name' => AliranKerja::labelPenuh($stage),
                    'keseluruhan' => $this->keseluruhan($agencyCode),
                    'notes' => $notes,
                ],
            );

            return $rekod;
        });
    }

    /**
     * Simpan data tangkapan satu peringkat (Tarikh, Status Borang, Nama Fail).
     *
     * Hanya medan yang ditakrifkan bagi peringkat itu dalam AliranKerja
     * diterima — borang tidak boleh menulis lajur peringkat lain. No. Rujukan
     * TIDAK disimpan di sini: ia mempunyai laluannya sendiri kerana pada
     * peringkat 1.1–1.3 ia dimasukkan oleh PKD dan bukan oleh pegawai
     * peringkat berkenaan (@see simpanRujukan).
     *
     * @param  array<string, mixed>  $data  lajur => nilai
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function simpanData(
        string $agencyCode,
        string $stage,
        array $data,
        ?User $user = null,
    ): WorkflowStageStatus {
        if (! AliranKerja::wujud($stage) || AliranKerja::adalahAkanDatang($stage)) {
            throw new InvalidWorkflowTransitionException(sprintf(
                'Peringkat %s tidak menerima data dalam fasa ini.',
                $stage,
            ));
        }

        // Turutan peringkat terpakai kepada MENYIMPAN juga, bukan hanya
        // kepada menyiapkan. Tanpa ini, data peringkat 1.2 boleh direkod
        // sebelum peringkat 1.1 membenarkannya — dan bagi peringkat
        // berderivasi, merekod data ITULAH yang menyiapkannya.
        $ralat = $this->ralatPeringkat($agencyCode, $stage);

        if ($ralat !== null) {
            throw new InvalidWorkflowTransitionException($ralat);
        }

        $dibenarkan = array_keys(AliranKerja::medan($stage));
        $data = array_intersect_key($data, array_flip($dibenarkan));

        $this->sahkanStatusBorang($agencyCode, $stage, $data);

        return DB::transaction(function () use ($agencyCode, $stage, $data, $user) {
            $rekod = WorkflowStageStatus::query()
                ->forAgency($agencyCode)
                ->atStage($stage)
                ->lockForUpdate()
                ->firstOrFail();

            $sebelum = $rekod->only(array_keys($data));

            $rekod->fill($data);
            $rekod->updated_by_user_id = $user?->id;

            // Merekod data peringkat bermakna kerjanya telah bermula. Peringkat
            // yang telah Selesai tidak diundurkan, dan peringkat berderivasi
            // menetapkan statusnya sendiri di bawah.
            if (! AliranKerja::statusDiterbitkan($stage)
                && $rekod->status === WorkflowStageStatus::BELUM_MULA
                && $this->ralatPeringkat($agencyCode, $stage, WorkflowStageStatus::DALAM_PROSES) === null) {
                $rekod->status = WorkflowStageStatus::DALAM_PROSES;
                $rekod->started_at ??= now();
            }

            $rekod->save();

            $this->terbitkanStatus($agencyCode, $stage, $user);

            $this->selaraskanKedudukan($agencyCode, $user);

            $this->audit->rekod(
                ['agency_code' => $rekod->agency_code, 'agency_name' => $rekod->agency_name],
                self::ACTION_STAGE_DATA_SAVED,
                null,
                AliranKerja::labelPenuh($stage),
                $user,
                [
                    'stage' => $stage,
                    'stage_name' => AliranKerja::labelPenuh($stage),
                    'medan' => array_keys($data),
                    'sebelum' => $sebelum,
                ],
            );

            return $rekod;
        });
    }

    /**
     * "Selesai" pada Status Borang menuntut borang itu benar-benar siap.
     *
     * Peringkat 3.1 menangkap Status Laporan Inventori Kriptografi, dan
     * laporan itu tidak boleh diisytiharkan Selesai sementara Borang Input
     * Analisis Inventori Kriptografi masih belum dimuktamadkan — status akan
     * mendahului kerja yang sepatutnya diwakilinya.
     *
     * Nilai lain tidak tersekat: kerja yang sedang berjalan tetap boleh
     * direkod.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidWorkflowTransitionException
     */
    private function sahkanStatusBorang(string $agencyCode, string $stage, array $data): void
    {
        if (! AliranKerja::statusSelesaiPerluBorangAnalisis($stage)) {
            return;
        }

        $status = $data[AliranKerja::MEDAN_STATUS_BORANG] ?? null;

        if ($status !== WorkflowStageStatus::SELESAI) {
            return;
        }

        $siap = AnalisisInventori::query()
            ->where('agency_code', $agencyCode)
            ->where('selesai', true)
            ->exists();

        if ($siap) {
            return;
        }

        throw new InvalidWorkflowTransitionException(sprintf(
            '%s hanya boleh ditetapkan "%s" setelah Borang Input Analisis Inventori '
            .'Kriptografi dilengkapkan.',
            AliranKerja::labelMedan($stage, AliranKerja::MEDAN_STATUS_BORANG),
            WorkflowStageStatus::SELESAI,
        ));
    }

    /**
     * Simpan No. Rujukan satu peringkat.
     *
     * Diasingkan daripada simpanData() kerana pemiliknya berbeza: No. Rujukan
     * Borang Penerimaan / Pendaftaran / Semakan Awal Data dimasukkan oleh
     * Pegawai Kawalan Dokumen, walaupun peringkatnya milik KB, PPA atau PA.
     * Siapa memasukkannya direkodkan pada baris itu sendiri.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function simpanRujukan(
        string $agencyCode,
        string $stage,
        ?string $noRujukan,
        ?User $user = null,
    ): WorkflowStageStatus {
        if (AliranKerja::labelRujukan($stage) === null) {
            throw new InvalidWorkflowTransitionException(sprintf(
                'Peringkat %s tidak mempunyai No. Rujukan.',
                $stage,
            ));
        }

        $noRujukan = is_string($noRujukan) ? trim($noRujukan) : null;
        $noRujukan = $noRujukan === '' ? null : $noRujukan;

        // Borang fizikal mesti direkod dahulu: No. Rujukan sesuatu borang
        // tiada makna sebelum borang itu sendiri wujud.
        $tiada = $this->medanSebelumRujukan(
            $this->peringkat($agencyCode)->get($stage),
            $stage,
        );

        if ($tiada !== []) {
            throw new InvalidWorkflowTransitionException(sprintf(
                '%s belum boleh direkod: peringkat %s memerlukan %s terlebih dahulu.',
                AliranKerja::labelRujukan($stage),
                AliranKerja::labelPenuh($stage),
                implode(' dan ', array_map(
                    fn (string $lajur) => AliranKerja::labelMedan($stage, $lajur),
                    $tiada,
                )),
            ));
        }

        return DB::transaction(function () use ($agencyCode, $stage, $noRujukan, $user) {
            $rekod = WorkflowStageStatus::query()
                ->forAgency($agencyCode)
                ->atStage($stage)
                ->lockForUpdate()
                ->firstOrFail();

            $sebelum = $rekod->no_rujukan;

            $rekod->no_rujukan = $noRujukan;
            $rekod->no_rujukan_oleh_user_id = $user?->id;
            $rekod->no_rujukan_pada = $noRujukan === null ? null : now();
            $rekod->save();

            // No. Rujukan ialah salah satu syarat Selesai peringkat 1.1, jadi
            // merekodnya boleh menyiapkan peringkat itu — dan memadamnya boleh
            // membukanya semula.
            $this->terbitkanStatus($agencyCode, $stage, $user);

            $this->audit->rekod(
                ['agency_code' => $rekod->agency_code, 'agency_name' => $rekod->agency_name],
                self::ACTION_STAGE_REFERENCE_SAVED,
                $sebelum,
                $noRujukan,
                $user,
                [
                    'stage' => $stage,
                    'stage_name' => AliranKerja::labelPenuh($stage),
                    'label' => AliranKerja::labelRujukan($stage),
                ],
            );

            return $rekod;
        });
    }

    /**
     * Tandakan satu peringkat Selesai — tindakan "Selesai" pada antara muka.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function tandakanSelesai(string $agencyCode, string $stage, ?User $user = null, ?string $notes = null): WorkflowStageStatus
    {
        return $this->tetapkanStatus($agencyCode, $stage, WorkflowStageStatus::SELESAI, $user, $notes);
    }

    /**
     * Tandakan satu peringkat Dalam Proses — dipanggil apabila kerja pada
     * peringkat itu benar-benar bermula.
     *
     * Peringkat yang telah Selesai tidak diundurkan, dan peringkat yang
     * belum terbuka (pendahulunya belum bermula) tidak disentuh langsung —
     * jadi memanggilnya lebih awal adalah selamat dan tidak melompat
     * peringkat.
     */
    public function tandakanDalamProses(string $agencyCode, string $stage, ?User $user = null): ?WorkflowStageStatus
    {
        $rekod = WorkflowStageStatus::query()
            ->forAgency($agencyCode)
            ->atStage($stage)
            ->first();

        // Peringkat berderivasi menetapkan statusnya sendiri daripada datanya;
        // membuka borangnya tidak mengubah apa-apa.
        if (AliranKerja::statusDiterbitkan($stage)) {
            return $rekod;
        }

        $bolehMula = $this->ralatPeringkat($agencyCode, $stage, WorkflowStageStatus::DALAM_PROSES) === null;

        if ($rekod === null || $rekod->isSelesai() || ! $bolehMula) {
            return $rekod;
        }

        if ($rekod->status === WorkflowStageStatus::DALAM_PROSES) {
            return $rekod;
        }

        return $this->tetapkanStatus($agencyCode, $stage, WorkflowStageStatus::DALAM_PROSES, $user);
    }

    /**
     * Masukkan entiti ke dalam aliran kerja dengan merekod peringkat 1.1.
     *
     * Status peringkat 1.1 DITERBITKAN daripada datanya, jadi kaedah ini tidak
     * "menanda" apa-apa: ia menyimpan data yang diberi, dan peringkat itu
     * menjadi Selesai apabila ketiga-tiga medannya ada. Memanggilnya tanpa
     * data memasukkan entiti ke dalam aliran tetapi meninggalkan peringkat 1.1
     * Belum Mula — itulah maksudnya.
     *
     * @param  array<string, string>  $entiti
     * @param  array<string, mixed>  $data  data tangkapan peringkat 1.1
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function lengkapkanPenerimaan(array $entiti, ?User $user = null, array $data = []): WorkflowStageStatus
    {
        return DB::transaction(function () use ($entiti, $user, $data) {
            $this->sediakan($entiti);

            $rujukan = $data[AliranKerja::MEDAN_NO_RUJUKAN] ?? null;
            unset($data[AliranKerja::MEDAN_NO_RUJUKAN]);

            if ($data !== []) {
                $this->simpanData($entiti['agency_code'], AliranKerja::PENERIMAAN_DATA, $data, $user);
            }

            if ($rujukan !== null) {
                $this->simpanRujukan($entiti['agency_code'], AliranKerja::PENERIMAAN_DATA, $rujukan, $user);
            }

            $rekod = $this->peringkat($entiti['agency_code'])->get(AliranKerja::PENERIMAAN_DATA);

            $this->audit->rekod(
                ['agency_code' => $entiti['agency_code'], 'agency_name' => $entiti['agency_name']],
                self::ACTION_REGISTRATION_COMPLETED,
                WorkflowStageStatus::BELUM_MULA,
                $rekod->status,
                $user,
                ['stage' => AliranKerja::PENERIMAAN_DATA, 'dikunci' => $rekod->isSelesai()],
            );

            return $rekod;
        });
    }

    /**
     * Status keseluruhan entiti — dikira, tidak pernah disimpan.
     *
     * 'Siap' hanya apabila kesemua peringkat FASA SEMASA Selesai.
     */
    public function keseluruhan(string $agencyCode): string
    {
        return $this->keseluruhanDaripada($this->peringkat($agencyCode));
    }

    /**
     * Versi tanpa query bagi keseluruhan() — untuk senarai yang telah
     * memuatkan peringkatnya.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function keseluruhanDaripada(?Collection $peringkat): string
    {
        return $this->ringkasan->keseluruhanDaripada($peringkat);
    }

    /**
     * Bilangan peringkat fasa semasa yang telah Selesai — untuk bar kemajuan.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function bilanganSelesai(?Collection $peringkat): int
    {
        return $this->ringkasan->bilanganSelesai($peringkat);
    }

    /**
     * Bilangan peringkat yang boleh disiapkan dalam fasa ini — penyebut
     * setiap bar kemajuan.
     */
    public function jumlahPeringkatSemasa(): int
    {
        return $this->ringkasan->jumlahPeringkatSemasa();
    }

    /**
     * Peringkat yang sedang dikerjakan.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function peringkatSemasa(?Collection $peringkat): string
    {
        return $this->ringkasan->peringkatSemasa($peringkat);
    }

    /**
     * Kelas badge bagi status keseluruhan (selaras dengan modul lain).
     */
    public function badgeKeseluruhan(string $keseluruhan): string
    {
        return $this->ringkasan->badgeKeseluruhan($keseluruhan);
    }

    /**
     * Kosongkan kemajuan entiti — digunakan oleh "Set Semula" KB.
     *
     * Baris peringkat dikekalkan (bukan dipadam) supaya entiti terus dikenali
     * dalam sistem dan jejak auditnya kekal bermakna. Data tangkapan turut
     * dikosongkan: entiti keluar semula daripada aliran kerja, jadi tarikh
     * dan nombor rujukan pusingan sebelumnya tidak lagi terpakai.
     */
    public function setSemula(string $agencyCode, ?User $user = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($agencyCode, $user, $reason) {
            $contoh = WorkflowStageStatus::query()->forAgency($agencyCode)->first();

            if ($contoh === null) {
                return;
            }

            WorkflowStageStatus::query()
                ->forAgency($agencyCode)
                ->update([
                    'status' => WorkflowStageStatus::BELUM_MULA,
                    'started_at' => null,
                    'completed_at' => null,
                    'tarikh_terima' => null,
                    'tarikh_daftar' => null,
                    'tarikh_semakan' => null,
                    'tarikh_mula' => null,
                    'tarikh_tamat' => null,
                    'status_borang' => null,
                    'nama_fail' => null,
                    'no_rujukan' => null,
                    'no_rujukan_oleh_user_id' => null,
                    'no_rujukan_pada' => null,
                    'updated_by_user_id' => $user?->id,
                    'notes' => $reason,
                    'updated_at' => now(),
                ]);

            $this->selaraskanKedudukan($agencyCode, $user);

            $this->audit->rekod(
                ['agency_code' => $agencyCode, 'agency_name' => $contoh->agency_name],
                self::ACTION_REGISTRATION_RESET,
                WorkflowStageStatus::SELESAI,
                WorkflowStageStatus::BELUM_MULA,
                $user,
                ['reason' => $reason, 'dikunci' => false],
            );
        });
    }

    /**
     * Selaraskan `workflow_status` dengan status peringkat sebenar.
     *
     * Modul sedia ada (stepper, senarai pemantauan, papan pemuka) membaca
     * jadual itu; menyelaraskannya di sini bermakna mereka kekal tepat tanpa
     * perlu diubah suai satu per satu.
     */
    private function selaraskanKedudukan(string $agencyCode, ?User $user = null): void
    {
        $peringkat = $this->peringkat($agencyCode);

        if ($peringkat->isEmpty()) {
            return;
        }

        $contoh = $peringkat->first();
        $semasa = $this->peringkatSemasa($peringkat);
        $keseluruhan = $this->keseluruhanDaripada($peringkat);
        $utama = AliranKerja::utamaBagi($semasa) ?? AliranKerja::UTAMA_PERTAMA;

        // Perbendaharaan `workflow_status` ialah kitaran StatusLaporan
        // ('Belum Bermula' / 'Dalam Proses' / 'Siap'); petakan ke situ supaya
        // paparan sedia ada tidak melihat nilai yang tidak dikenalinya.
        $status = match ($keseluruhan) {
            self::KESELURUHAN_SIAP => 'Siap',
            self::KESELURUHAN_DALAM_PROSES => 'Dalam Proses',
            default => 'Belum Bermula',
        };

        WorkflowStatus::updateOrCreate(
            ['agency_code' => $agencyCode],
            [
                'agency_name' => $contoh->agency_name,
                'sector_code' => $contoh->sector_code,
                'sector_name' => $contoh->sector_name,
                'current_stage' => $utama,
                'current_stage_key' => $semasa,
                'stage_name' => AliranKerja::labelUtama($utama),
                'status' => $status,
                'status_since' => now(),
                'updated_by_user_id' => $user?->id,
            ],
        );
    }

    /**
     * Tindakan jejak audit yang menggerakkan peringkat sesuatu entiti.
     *
     * Tiga kumpulan, dan ketiga-tiganya diperlukan supaya "Sejarah Peringkat"
     * benar-benar sampai ke kedudukan semasa:
     *
     * 1. Aliran semasa — kemasukan entiti, setiap perubahan status peringkat,
     *    dan data yang direkodkan pada peringkat.
     * 2. Kitaran laporan — dikekalkan supaya rekod sejarah yang ditulis
     *    sebelum restruktur tidak lenyap daripada paparan. Kitaran itu
     *    sendiri milik peringkat 4 dan 5, yang belum dibina.
     * 3. Perbendaharaan workflow lama — dikekalkan atas sebab yang sama.
     *
     * @var list<string>
     */
    public const TINDAKAN_SEJARAH = [
        self::ACTION_REGISTRATION_COMPLETED,
        self::ACTION_REGISTRATION_RESET,
        self::ACTION_STAGE_STATUS_CHANGED,
        self::ACTION_STAGE_DATA_SAVED,
        self::ACTION_STAGE_REFERENCE_SAVED,

        'report_generated',
        'report_submitted',
        'report_reviewed',
        'report_returned',
        'report_approved',
        'report_delivered',
        'report_status_changed',

        WorkflowTransitionService::ACTION_INITIALIZED,
        WorkflowTransitionService::ACTION_STAGE_CHANGED,
        WorkflowTransitionService::ACTION_STATUS_UPDATED,
    ];

    /**
     * Query sejarah peringkat — diasingkan daripada sejarah() supaya
     * pemanggil boleh menomborkannya dan bukan sekadar memotongnya.
     *
     * @return Builder<ActivityLog>
     */
    public function sejarahQuery(string $agencyCode): Builder
    {
        return ActivityLog::query()
            ->where('agency_code', $agencyCode)
            ->whereIn('action', self::TINDAKAN_SEJARAH)
            ->with('changedBy')
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
    }

    /**
     * Sejarah perubahan peringkat bagi satu entiti.
     *
     * @return Collection<int, ActivityLog>
     */
    public function sejarah(string $agencyCode, int $had = 50): Collection
    {
        return $this->sejarahQuery($agencyCode)->limit($had)->get();
    }
}
