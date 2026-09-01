<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use App\Support\AliranKerja;
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
     * Adakah entiti ini berada dalam aliran kerja, dijawab daripada peringkat
     * yang telah dimuatkan — versi tanpa query bagi penerimaanSelesai().
     *
     * Senarai TIDAK boleh menggunakan "ada baris peringkat" sebagai ganti:
     * setSemula() mengekalkan semua baris dan hanya mengembalikan statusnya
     * kepada Belum Mula, jadi entiti yang telah ditetapkan semula oleh Ketua
     * Bahagian tetap mempunyai baris peringkat. Peringkat 1.1 Selesai ialah
     * satu-satunya ujian yang betul.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function dalamAliranKerja(?Collection $peringkat): bool
    {
        return $peringkat?->get(AliranKerja::PENERIMAAN_DATA)?->isSelesai() ?? false;
    }

    /**
     * Adakah "Status Laporan" berkenaan bagi entiti ini?
     *
     * Laporan Analisis Inventori Kriptografi baru bermakna setelah peringkat
     * 3.1 Selesai. Memaparkan statusnya lebih awal membacanya sebagai kerja
     * yang tertunggak — "Belum Lengkap" pada peringkat 2 menuduh Pegawai
     * Analisis kerana borang yang gilirannya belum pun tiba.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function statusLaporanBerkenaan(?Collection $peringkat): bool
    {
        return $peringkat?->get(AliranKerja::ANALISIS_INVENTORI)?->isSelesai() ?? false;
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
     * Dua peraturan berbeza, kerana kerja boleh bertindih walaupun penyiapan
     * tidak boleh:
     *
     * - Menandakan SELESAI menuntut pendahulunya telah Selesai. Inilah yang
     *   menghalang peringkat dilangkau.
     * - Menandakan DALAM PROSES hanya menuntut pendahulunya telah bermula.
     *
     * Peringkat fasa akan datang ditolak terus: modulnya belum dibina, jadi
     * tiada tindakan padanya yang boleh bermakna.
     */
    public function ralatPeringkat(
        string $agencyCode,
        string $stage,
        string $status = WorkflowStageStatus::SELESAI,
    ): ?string {
        if (! AliranKerja::wujud($stage)) {
            return sprintf('Peringkat %s tidak sah.', $stage);
        }

        if (AliranKerja::adalahAkanDatang($stage)) {
            return sprintf(
                'Peringkat %s belum dibina. Ia dikhaskan untuk fasa akan datang.',
                AliranKerja::labelPenuh($stage),
            );
        }

        $peringkat = $this->peringkat($agencyCode);

        if ($peringkat->isEmpty()) {
            return 'Entiti ini belum didaftarkan dalam Kemajuan Analisis Entiti.';
        }

        $sebelumKunci = AliranKerja::sebelum($stage);

        if ($sebelumKunci === null) {
            return null;
        }

        $sebelum = $peringkat->get($sebelumKunci);

        if ($status === WorkflowStageStatus::SELESAI) {
            if ($sebelum === null || ! $sebelum->isSelesai()) {
                return sprintf(
                    'Peringkat %s mesti Selesai terlebih dahulu.',
                    AliranKerja::labelPenuh($sebelumKunci),
                );
            }

            return null;
        }

        if ($sebelum === null || $sebelum->isBelumMula()) {
            return sprintf(
                'Peringkat %s mesti bermula terlebih dahulu.',
                AliranKerja::labelPenuh($sebelumKunci),
            );
        }

        return null;
    }

    /**
     * Bolehkah peringkat ini ditandakan Selesai sekarang?
     */
    public function bolehTandakan(string $agencyCode, string $stage): bool
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
     * peringkat 1.1–1.3 ia dimasukkan oleh PPR dan bukan oleh pegawai
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

        $dibenarkan = array_keys(AliranKerja::medan($stage));
        $data = array_intersect_key($data, array_flip($dibenarkan));

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
            // yang telah Selesai tidak diundurkan.
            if ($rekod->status === WorkflowStageStatus::BELUM_MULA
                && $this->ralatPeringkat($agencyCode, $stage, WorkflowStageStatus::DALAM_PROSES) === null) {
                $rekod->status = WorkflowStageStatus::DALAM_PROSES;
                $rekod->started_at ??= now();
            }

            $rekod->save();

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
     * Simpan No. Rujukan satu peringkat.
     *
     * Diasingkan daripada simpanData() kerana pemiliknya berbeza: No. Rujukan
     * Borang Penerimaan / Pendaftaran / Semakan Awal Data dimasukkan oleh
     * Pegawai Penyelaras Rekod, walaupun peringkatnya milik KB, PPA atau PA.
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
     * Lengkapkan peringkat 1.1 "Penerimaan Data" bagi satu entiti.
     *
     * Ini ialah pintu masuk aliran kerja: entiti dicipta dalam kemajuan,
     * peringkat 1.1 ditandakan Selesai, dan sejak itu ia dikunci sehingga
     * Ketua Bahagian menetapkannya semula.
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

            if ($data !== []) {
                $this->simpanData($entiti['agency_code'], AliranKerja::PENERIMAAN_DATA, $data, $user);
            }

            $rekod = $this->tandakanSelesai(
                $entiti['agency_code'],
                AliranKerja::PENERIMAAN_DATA,
                $user,
            );

            $this->audit->rekod(
                ['agency_code' => $entiti['agency_code'], 'agency_name' => $entiti['agency_name']],
                self::ACTION_REGISTRATION_COMPLETED,
                WorkflowStageStatus::BELUM_MULA,
                WorkflowStageStatus::SELESAI,
                $user,
                ['stage' => AliranKerja::PENERIMAAN_DATA, 'dikunci' => true],
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
     * Versi tanpa query — untuk senarai yang telah memuatkan peringkatnya.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function keseluruhanDaripada(?Collection $peringkat): string
    {
        if ($peringkat === null || $peringkat->isEmpty()) {
            return self::KESELURUHAN_BELUM_MULA;
        }

        $semasa = $this->fasaSemasaSahaja($peringkat);

        $jumlah = count(AliranKerja::semasa());
        $selesai = $semasa->where('status', WorkflowStageStatus::SELESAI)->count();

        if ($selesai >= $jumlah) {
            return self::KESELURUHAN_SIAP;
        }

        $adaKemajuan = $selesai > 0
            || $semasa->where('status', WorkflowStageStatus::DALAM_PROSES)->isNotEmpty();

        return $adaKemajuan ? self::KESELURUHAN_DALAM_PROSES : self::KESELURUHAN_BELUM_MULA;
    }

    /**
     * Bilangan peringkat fasa semasa yang telah Selesai — untuk bar kemajuan.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function bilanganSelesai(?Collection $peringkat): int
    {
        return $peringkat === null
            ? 0
            : $this->fasaSemasaSahaja($peringkat)->where('status', WorkflowStageStatus::SELESAI)->count();
    }

    /**
     * Bilangan peringkat yang boleh disiapkan dalam fasa ini — penyebut
     * setiap bar kemajuan.
     */
    public function jumlahPeringkatSemasa(): int
    {
        return count(AliranKerja::semasa());
    }

    /**
     * Peringkat yang sedang dikerjakan — peringkat fasa semasa yang pertama
     * belum Selesai, atau peringkat terakhir fasa ini jika semuanya selesai.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function peringkatSemasa(?Collection $peringkat): string
    {
        if ($peringkat === null || $peringkat->isEmpty()) {
            return AliranKerja::PERTAMA;
        }

        foreach (AliranKerja::semasa() as $stage) {
            if (! ($peringkat->get($stage)?->isSelesai() ?? false)) {
                return $stage;
            }
        }

        return AliranKerja::TERAKHIR_SEMASA;
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
     * Peringkat fasa semasa sahaja.
     *
     * @param  Collection<string, WorkflowStageStatus>  $peringkat
     * @return Collection<string, WorkflowStageStatus>
     */
    private function fasaSemasaSahaja(Collection $peringkat): Collection
    {
        $semasa = AliranKerja::semasa();

        return $peringkat->filter(
            fn (WorkflowStageStatus $p): bool => in_array($p->stage, $semasa, true)
        );
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
