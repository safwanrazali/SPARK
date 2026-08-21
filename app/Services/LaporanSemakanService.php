<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\ApprovalLog;
use App\Models\LaporanSemakan;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat kedudukan semakan dan kelulusan laporan boleh berubah.
 *
 * Aliran (carta aliran bahagian 7–9):
 *
 *   PA  "Hantar kepada PPA" → Draf, lalu Dihantar kepada PPA
 *   PPA "Hantar"        → Dihantar kepada KB
 *   PPA/KB "Kembalikan" → Dikembalikan   (Catatan WAJIB)
 *   KB  "Sahkan"        → Sah            (Catatan pilihan; peringkat 5 dan 6
 *                                         menjadi Selesai)
 *
 * Setiap peralihan disemak terhadap LaporanSemakan::ALIRAN, jadi keadaan
 * tidak boleh dilangkau walaupun borang dihantar terus tanpa melalui UI.
 * Setiap tindakan turut dicatat dalam approval_logs (jadual sedia ada) dan
 * dalam jejak audit berpusat.
 */
class LaporanSemakanService
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly KemajuanAnalisisService $kemajuan,
    ) {}

    public const JENIS_LALAI = 'inventori';

    /**
     * Kedudukan semakan bagi satu entiti, dicipta sebagai Draf jika belum ada.
     *
     * @param  array<string, string>  $entiti
     */
    public function mulakan(array $entiti, string $reportType = self::JENIS_LALAI): LaporanSemakan
    {
        return LaporanSemakan::firstOrCreate(
            ['agency_code' => $entiti['agency_code'], 'report_type' => $reportType],
            [
                'agency_name' => $entiti['agency_name'],
                'sector_code' => $entiti['sector_code'],
                'sector_name' => $entiti['sector_name'],
                'status' => LaporanSemakan::DRAF,
            ],
        );
    }

    public function untuk(string $agencyCode, string $reportType = self::JENIS_LALAI): ?LaporanSemakan
    {
        return LaporanSemakan::query()
            ->forAgency($agencyCode)
            ->where('report_type', $reportType)
            ->first();
    }

    /**
     * Kedudukan semakan bagi banyak entiti sekali gus.
     *
     * @param  array<int, string>  $agencyCodes
     * @return Collection<string, LaporanSemakan>
     */
    public function untukBanyak(array $agencyCodes, string $reportType = self::JENIS_LALAI): Collection
    {
        if ($agencyCodes === []) {
            return collect();
        }

        return LaporanSemakan::query()
            ->whereIn('agency_code', $agencyCodes)
            ->where('report_type', $reportType)
            ->get()
            ->keyBy('agency_code');
    }

    /**
     * PA menekan "Hantar" — laporan diserahkan kepada PPA untuk semakan.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function hantarKepadaPPA(LaporanSemakan $laporan, User $user): LaporanSemakan
    {
        $laporan = $this->beralih($laporan, LaporanSemakan::MENUNGGU_PPA, $user, null, function (LaporanSemakan $l) use ($user) {
            $l->dihantar_oleh_user_id = $user->id;
            $l->dihantar_pada = now();
            $l->catatan = null;
        });

        // Penghantaran kepada PPA — dan BUKAN penyiapan borang — inilah yang
        // menggerakkan kedua-dua peringkat kepada Dalam Proses. Dilakukan di
        // sini, bukan dalam controller, supaya kedudukan laporan dan status
        // peringkat tidak boleh terpisah apabila salah satu gagal.
        //
        // Kedua-duanya kekal Dalam Proses sehingga KB mengesahkan laporan;
        // tiada tindakan PA atau PPA boleh menjadikannya Selesai.
        foreach ([WorkflowStatus::STAGE_JANA_LAPORAN, WorkflowStatus::STAGE_SEMAKAN_KELULUSAN] as $stage) {
            $this->kemajuan->tetapkanStatus(
                $laporan->agency_code,
                $stage,
                WorkflowStageStatus::DALAM_PROSES,
                $user,
            );
        }

        return $laporan;
    }

    /**
     * PPA menekan "Hantar" — laporan diserahkan kepada KB untuk kelulusan.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function hantarKepadaKB(LaporanSemakan $laporan, User $user): LaporanSemakan
    {
        return $this->beralih($laporan, LaporanSemakan::MENUNGGU_KB, $user, null, function (LaporanSemakan $l) use ($user) {
            $l->disemak_oleh_user_id = $user->id;
            $l->disemak_pada = now();
        });
    }

    /**
     * PPA atau KB menekan "Kembalikan".
     *
     * Catatan WAJIB — inilah peraturan yang menyebabkan butang "Kembalikan"
     * kekal dilumpuhkan sehingga medan Catatan diisi. Pengesahan dibuat di
     * sini juga supaya penghantaran borang terus tidak boleh memintasnya.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function kembalikan(LaporanSemakan $laporan, User $user, ?string $catatan): LaporanSemakan
    {
        $catatan = is_string($catatan) ? trim($catatan) : '';

        if ($catatan === '') {
            throw new InvalidWorkflowTransitionException(
                'Catatan wajib diisi sebelum laporan boleh dikembalikan kepada Pegawai Analisis.'
            );
        }

        $laporan = $this->beralih($laporan, LaporanSemakan::DIKEMBALIKAN, $user, $catatan, function (LaporanSemakan $l) use ($catatan) {
            $l->catatan = $catatan;
        });

        // Laporan kembali ke tangan PA untuk dibetulkan. Kedua-dua peringkat
        // kekal Dalam Proses — pengembalian ialah sebahagian daripada kitaran
        // semakan, bukan pengunduran daripadanya (aliran kerja bahagian 10).
        $this->kemajuan->tetapkanStatus(
            $laporan->agency_code,
            WorkflowStatus::STAGE_JANA_LAPORAN,
            WorkflowStageStatus::DALAM_PROSES,
            $user,
            $catatan,
        );

        return $laporan;
    }

    /**
     * KB menekan "Sahkan".
     *
     * Hanya di sini "Jana Laporan" menjadi Selesai — bukan semasa laporan
     * dijana atau dihantar (carta aliran bahagian 7).
     *
     * Catatan adalah PILIHAN di sini (berbeza daripada "Kembalikan", yang
     * mewajibkannya). Ia direkodkan dalam approval_logs dan jejak audit —
     * iaitu apa yang dipaparkan pada Sejarah Peringkat entiti — dan TIDAK
     * pernah masuk ke dalam laporan itu sendiri.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function sahkan(LaporanSemakan $laporan, User $user, ?string $catatan = null): LaporanSemakan
    {
        $catatan = is_string($catatan) && trim($catatan) !== '' ? trim($catatan) : null;

        $laporan = $this->beralih($laporan, LaporanSemakan::SAH, $user, $catatan, function (LaporanSemakan $l) use ($user, $catatan) {
            $l->disahkan_oleh_user_id = $user->id;
            $l->disahkan_pada = now();

            // Sebab pengembalian terdahulu tidak boleh kekal selepas laporan
            // disahkan; ia digantikan oleh catatan pengesahan, atau dikosongkan.
            $l->catatan = $catatan;
        });

        $this->kemajuan->tandakanSelesai($laporan->agency_code, WorkflowStatus::STAGE_JANA_LAPORAN, $user);
        $this->kemajuan->tandakanSelesai($laporan->agency_code, WorkflowStatus::STAGE_SEMAKAN_KELULUSAN, $user);

        return $laporan;
    }

    /**
     * Penyerahan laporan yang telah disahkan kepada NACSA.
     *
     * Tiada peralihan keadaan: laporan kekal Sah. Yang direkodkan ialah
     * penyerahannya, supaya jejak audit membezakan "disahkan KB" daripada
     * "diserahkan kepada NACSA" (aliran kerja bahagian 23).
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function rekodPenyerahan(LaporanSemakan $laporan, User $user): void
    {
        if (! $laporan->isSah()) {
            throw new InvalidWorkflowTransitionException(
                'Laporan perlu berstatus Sah sebelum boleh diserahkan kepada NACSA.'
            );
        }

        $this->audit->rekod(
            ['agency_code' => $laporan->agency_code, 'agency_name' => $laporan->agency_name],
            'report_delivered',
            LaporanSemakan::SAH,
            LaporanSemakan::SAH,
            $user,
            ['report_type' => $laporan->report_type, 'stage' => WorkflowStatus::STAGE_PENYERAHAN],
        );
    }

    /**
     * Sejarah semakan bagi satu entiti.
     *
     * @return Collection<int, ApprovalLog>
     */
    public function sejarah(string $agencyCode, string $reportType = self::JENIS_LALAI): Collection
    {
        return ApprovalLog::query()
            ->forAgency($agencyCode)
            ->forReportType($reportType)
            ->with('approvedBy')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Laksanakan satu peralihan keadaan beserta jejaknya.
     *
     * @param  callable(LaporanSemakan): void|null  $ubah
     *
     * @throws InvalidWorkflowTransitionException
     */
    private function beralih(
        LaporanSemakan $laporan,
        string $kepada,
        User $user,
        ?string $catatan,
        ?callable $ubah = null,
    ): LaporanSemakan {
        if (! $laporan->bolehBeralihKe($kepada)) {
            throw new InvalidWorkflowTransitionException(sprintf(
                'Laporan berstatus "%s" tidak boleh bertukar kepada "%s".',
                $laporan->status,
                $kepada,
            ));
        }

        return DB::transaction(function () use ($laporan, $kepada, $user, $catatan, $ubah) {
            $sebelum = $laporan->status;

            $laporan->status = $kepada;

            if ($ubah !== null) {
                $ubah($laporan);
            }

            $laporan->save();

            ApprovalLog::create([
                'agency_code' => $laporan->agency_code,
                'agency_name' => $laporan->agency_name,
                'report_type' => $laporan->report_type,
                'status_before' => $sebelum,
                'status_after' => $kepada,
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'comments' => $catatan,
            ]);

            $this->audit->rekod(
                ['agency_code' => $laporan->agency_code, 'agency_name' => $laporan->agency_name],
                $this->tindakanAudit($kepada),
                $sebelum,
                $kepada,
                $user,
                ['report_type' => $laporan->report_type, 'catatan' => $catatan],
            );

            return $laporan;
        });
    }

    /**
     * Tindakan jejak audit yang sepadan dengan keadaan baharu — menggunakan
     * perbendaharaan yang telah dikhaskan dalam ActivityLog::ACTIONS.
     */
    private function tindakanAudit(string $kepada): string
    {
        return match ($kepada) {
            LaporanSemakan::MENUNGGU_PPA => 'report_submitted',
            LaporanSemakan::MENUNGGU_KB => 'report_reviewed',
            LaporanSemakan::DIKEMBALIKAN => 'report_returned',
            LaporanSemakan::SAH => 'report_approved',
            default => 'report_status_changed',
        };
    }
}
