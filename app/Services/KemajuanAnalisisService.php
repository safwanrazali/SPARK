<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat status peringkat Kemajuan Analisis Entiti boleh berubah.
 *
 * Peraturan yang dikuatkuasakan di sini (aliran kerja bahagian 5–11):
 * - Setiap entiti memiliki tujuh baris peringkat; tiada baris bermakna entiti
 *   belum didaftarkan langsung.
 * - Satu peringkat hanya boleh ditandakan Selesai apabila peringkat
 *   sebelumnya telah Selesai. Tiada peringkat boleh dilangkau.
 * - Keseluruhan entiti hanya menjadi 'Siap' apabila KESEMUA tujuh peringkat
 *   Selesai — tidak sekali-kali lebih awal.
 * - `workflow_status` (kedudukan semasa) diselaraskan pada setiap perubahan,
 *   supaya stepper dan papan pemuka sedia ada kekal tepat.
 *
 * Kebenaran peranan TIDAK disemak di sini; ia dikawal oleh gate pada lapisan
 * route/controller mengikut seni bina sedia ada.
 */
class KemajuanAnalisisService
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly WorkflowTransitionService $workflow,
    ) {}

    public const ACTION_STAGE_STATUS_CHANGED = 'stage_status_changed';

    public const ACTION_REGISTRATION_COMPLETED = 'registration_completed';

    public const ACTION_REGISTRATION_RESET = 'registration_reset';

    /**
     * Keseluruhan: entiti belum memasuki aliran kerja analisis.
     */
    public const KESELURUHAN_BELUM_MULA = 'Belum Mula';

    public const KESELURUHAN_DALAM_PROSES = 'Dalam Proses';

    public const KESELURUHAN_SIAP = 'Siap';

    /**
     * Cipta tujuh baris peringkat bagi satu entiti, semuanya 'Belum Mula'.
     *
     * Selamat dipanggil berulang kali: baris sedia ada tidak disentuh, jadi
     * pendaftaran semula tidak memadam kemajuan yang telah dicapai.
     *
     * @param  array<string, string>  $entiti  sector_code, sector_name, agency_code, agency_name
     * @return Collection<int, WorkflowStageStatus> dikunci mengikut nombor peringkat
     */
    public function sediakan(array $entiti): Collection
    {
        // Baris pengepala `workflow_status` dicipta melalui servis sedia ada
        // supaya kemasukan entiti ke dalam workflow kekal muncul dalam jejak
        // audit dengan tindakan yang sama seperti sebelum ini.
        $this->workflow->initialize($entiti);

        foreach (array_keys(WorkflowStatus::WORKFLOW_STAGES) as $stage) {
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
     * Status setiap peringkat bagi satu entiti, dikunci mengikut nombor peringkat.
     *
     * @return Collection<int, WorkflowStageStatus>
     */
    public function peringkat(string $agencyCode): Collection
    {
        return WorkflowStageStatus::query()
            ->forAgency($agencyCode)
            ->orderBy('stage')
            ->get()
            ->keyBy('stage');
    }

    /**
     * Status setiap peringkat bagi banyak entiti sekali gus.
     *
     * Senarai entiti memaparkan kemajuan setiap baris; tanpa ini setiap baris
     * mengeluarkan satu query sendiri.
     *
     * @param  array<int, string>  $agencyCodes
     * @return Collection<string, Collection<int, WorkflowStageStatus>>
     */
    public function peringkatUntukBanyak(array $agencyCodes): Collection
    {
        if ($agencyCodes === []) {
            return collect();
        }

        return WorkflowStageStatus::query()
            ->whereIn('agency_code', $agencyCodes)
            ->orderBy('stage')
            ->get()
            ->groupBy('agency_code')
            ->map(fn (Collection $peringkat) => $peringkat->keyBy('stage'));
    }

    /**
     * Adakah entiti telah didaftarkan (peringkat 1 Selesai)?
     *
     * Ini ialah pintu masuk kepada keseluruhan aliran: sebelum ia benar,
     * entiti tidak muncul kepada PPA dan tidak boleh ditugaskan.
     */
    public function pendaftaranSelesai(string $agencyCode): bool
    {
        return WorkflowStageStatus::query()
            ->forAgency($agencyCode)
            ->atStage(WorkflowStatus::STAGE_PENDAFTARAN)
            ->selesai()
            ->exists();
    }

    /**
     * Kod entiti yang telah menyelesaikan pendaftaran.
     *
     * @return array<int, string>
     */
    public function kodPendaftaranSelesai(): array
    {
        return WorkflowStageStatus::query()
            ->atStage(WorkflowStatus::STAGE_PENDAFTARAN)
            ->selesai()
            ->pluck('agency_code')
            ->all();
    }

    /**
     * Bolehkah peringkat ini ditetapkan kepada $status sekarang?
     *
     * Dua peraturan berbeza, kerana kerja boleh bertindih walaupun penyiapan
     * tidak boleh:
     *
     * - Menandakan SELESAI menuntut pendahulunya telah Selesai. Inilah yang
     *   menghalang peringkat dilangkau.
     * - Menandakan DALAM PROSES hanya menuntut pendahulunya telah bermula.
     *   Ini diperlukan oleh carta aliran itu sendiri: "Semakan & Kelulusan"
     *   bermula sebaik laporan dihantar, sedangkan "Jana Laporan" sengaja
     *   kekal Dalam Proses sehingga Ketua Bahagian mengesahkannya.
     *
     * Peringkat pertama ialah tanggungjawab PPR dan mempunyai skrinnya sendiri.
     */
    public function ralatPeringkat(
        string $agencyCode,
        int $stage,
        string $status = WorkflowStageStatus::SELESAI,
    ): ?string {
        if (! WorkflowStatus::isValidStage($stage)) {
            return sprintf('Peringkat %d tidak sah.', $stage);
        }

        $peringkat = $this->peringkat($agencyCode);

        if ($peringkat->isEmpty()) {
            return 'Entiti ini belum didaftarkan dalam Kemajuan Analisis Entiti.';
        }

        if ($stage === WorkflowStatus::FIRST_STAGE) {
            return null;
        }

        $sebelum = $peringkat->get($stage - 1);

        if ($status === WorkflowStageStatus::SELESAI) {
            if ($sebelum === null || ! $sebelum->isSelesai()) {
                return sprintf(
                    'Peringkat %d — %s mesti Selesai terlebih dahulu.',
                    $stage - 1,
                    WorkflowStatus::getStageName($stage - 1),
                );
            }

            return null;
        }

        if ($sebelum === null || $sebelum->isBelumMula()) {
            return sprintf(
                'Peringkat %d — %s mesti bermula terlebih dahulu.',
                $stage - 1,
                WorkflowStatus::getStageName($stage - 1),
            );
        }

        return null;
    }

    /**
     * Bolehkah peringkat ini ditandakan Selesai sekarang?
     */
    public function bolehTandakan(string $agencyCode, int $stage): bool
    {
        return $this->ralatPeringkat($agencyCode, $stage) === null;
    }

    /**
     * Tetapkan status satu peringkat.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function tetapkanStatus(
        string $agencyCode,
        int $stage,
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
                    'stage_name' => WorkflowStatus::getStageName($stage),
                    'keseluruhan' => $this->keseluruhan($agencyCode),
                    'notes' => $notes,
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
    public function tandakanSelesai(string $agencyCode, int $stage, ?User $user = null, ?string $notes = null): WorkflowStageStatus
    {
        return $this->tetapkanStatus($agencyCode, $stage, WorkflowStageStatus::SELESAI, $user, $notes);
    }

    /**
     * Tandakan satu peringkat Dalam Proses — dipanggil apabila kerja bermula
     * (contohnya PA membuka borang analisis).
     *
     * Peringkat yang telah Selesai tidak diundurkan oleh panggilan ini.
     */
    public function tandakanDalamProses(string $agencyCode, int $stage, ?User $user = null): ?WorkflowStageStatus
    {
        $rekod = WorkflowStageStatus::query()
            ->forAgency($agencyCode)
            ->atStage($stage)
            ->first();

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
     * Lengkapkan "Penerimaan & Pendaftaran Data" bagi satu entiti.
     *
     * Ini ialah pintu masuk aliran kerja: entiti dicipta dalam kemajuan,
     * peringkat 1 ditandakan Selesai, dan sejak itu ia dikunci daripada PPR
     * sehingga Ketua Bahagian menetapkannya semula.
     *
     * @param  array<string, string>  $entiti
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function lengkapkanPendaftaran(array $entiti, ?User $user = null): WorkflowStageStatus
    {
        return DB::transaction(function () use ($entiti, $user) {
            $this->sediakan($entiti);

            $rekod = $this->tandakanSelesai(
                $entiti['agency_code'],
                WorkflowStatus::STAGE_PENDAFTARAN,
                $user,
            );

            $this->audit->rekod(
                ['agency_code' => $entiti['agency_code'], 'agency_name' => $entiti['agency_name']],
                self::ACTION_REGISTRATION_COMPLETED,
                WorkflowStageStatus::BELUM_MULA,
                WorkflowStageStatus::SELESAI,
                $user,
                ['stage' => WorkflowStatus::STAGE_PENDAFTARAN, 'dikunci' => true],
            );

            return $rekod;
        });
    }

    /**
     * Status keseluruhan entiti — dikira, tidak pernah disimpan.
     *
     * 'Siap' hanya apabila kesemua tujuh peringkat Selesai; itulah jaminan
     * bahawa entiti tidak boleh ditandakan siap lebih awal.
     */
    public function keseluruhan(string $agencyCode): string
    {
        return $this->keseluruhanDaripada($this->peringkat($agencyCode));
    }

    /**
     * Versi tanpa query — untuk senarai yang telah memuatkan peringkatnya.
     *
     * @param  Collection<int, WorkflowStageStatus>|null  $peringkat
     */
    public function keseluruhanDaripada(?Collection $peringkat): string
    {
        if ($peringkat === null || $peringkat->isEmpty()) {
            return self::KESELURUHAN_BELUM_MULA;
        }

        $jumlah = count(WorkflowStatus::WORKFLOW_STAGES);
        $selesai = $peringkat->where('status', WorkflowStageStatus::SELESAI)->count();

        if ($selesai >= $jumlah) {
            return self::KESELURUHAN_SIAP;
        }

        $adaKemajuan = $selesai > 0
            || $peringkat->where('status', WorkflowStageStatus::DALAM_PROSES)->isNotEmpty();

        return $adaKemajuan ? self::KESELURUHAN_DALAM_PROSES : self::KESELURUHAN_BELUM_MULA;
    }

    /**
     * Bilangan peringkat yang telah Selesai — untuk bar kemajuan.
     *
     * @param  Collection<int, WorkflowStageStatus>|null  $peringkat
     */
    public function bilanganSelesai(?Collection $peringkat): int
    {
        return $peringkat === null
            ? 0
            : $peringkat->where('status', WorkflowStageStatus::SELESAI)->count();
    }

    /**
     * Peringkat yang sedang dikerjakan — peringkat pertama yang belum Selesai.
     *
     * @param  Collection<int, WorkflowStageStatus>|null  $peringkat
     */
    public function peringkatSemasa(?Collection $peringkat): int
    {
        if ($peringkat === null || $peringkat->isEmpty()) {
            return WorkflowStatus::FIRST_STAGE;
        }

        foreach (array_keys(WorkflowStatus::WORKFLOW_STAGES) as $stage) {
            if (! ($peringkat->get($stage)?->isSelesai() ?? false)) {
                return $stage;
            }
        }

        return WorkflowStatus::LAST_STAGE;
    }

    /**
     * Kelas badge bagi status keseluruhan (selaras dengan modul lain).
     */
    public function badgeKeseluruhan(string $keseluruhan): string
    {
        return [
            self::KESELURUHAN_SIAP => 'status-rendah',
            self::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
        ][$keseluruhan] ?? 'status-tinggi';
    }

    /**
     * Kosongkan kemajuan entiti — digunakan oleh "Set Semula" KB.
     *
     * Baris peringkat dikekalkan (bukan dipadam) supaya entiti terus dikenali
     * sebagai berdaftar dalam sistem dan jejak auditnya kekal bermakna.
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
                'current_stage' => $semasa,
                'stage_name' => WorkflowStatus::getStageName($semasa),
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
     * 1. Aliran Kemajuan Analisis semasa — pendaftaran dan setiap perubahan
     *    status peringkat.
     * 2. Kitaran laporan — peringkat 05 hingga 07 digerakkan oleh penghantaran,
     *    semakan, pengembalian dan pengesahan laporan, bukan oleh perubahan
     *    status peringkat secara langsung.
     * 3. Perbendaharaan workflow lama — dikekalkan supaya rekod sejarah yang
     *    ditulis sebelum aliran semasa tidak lenyap daripada paparan.
     *
     * @var list<string>
     */
    public const TINDAKAN_SEJARAH = [
        self::ACTION_REGISTRATION_COMPLETED,
        self::ACTION_REGISTRATION_RESET,
        self::ACTION_STAGE_STATUS_CHANGED,

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
