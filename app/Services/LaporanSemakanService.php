<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\ApprovalLog;
use App\Models\LaporanSemakan;
use App\Models\User;
use App\Support\AliranKerja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat kedudukan semakan dan kelulusan laporan boleh berubah.
 *
 *   PA  "Hantar kepada PPA" → Draf, lalu Dihantar kepada PPA
 *   PPA "Hantar"        → Dihantar kepada KB
 *   PPA/KB "Kembalikan" → Dikembalikan   (Catatan WAJIB)
 *   KB  "Sahkan"        → Sah            (Catatan pilihan)
 *
 * FASA AKAN DATANG — kitaran ini milik peringkat 4 (Penjanaan Laporan) dan
 * peringkat 5 (Semakan, Kelulusan & Penyerahan Laporan), yang PROSESNYA
 * BELUM DITENTUKAN. Servis, model dan jadualnya DIKEKALKAN sepenuhnya
 * supaya kerja sedia ada dan rekod sedia ada tidak hilang, tetapi TIADA
 * route atau butang memanggilnya dalam fasa ini.
 *
 * Pautan kepada status peringkat telah DIBUANG dengan sengaja: peringkat 4
 * dan 5 tidak menerima sebarang tindakan sehingga prosesnya ditetapkan, dan
 * menyambungkannya semula tanpa spesifikasi bermakna mereka-reka proses itu.
 * Sambungkan semula di sini apabila spesifikasi peringkat 4 dan 5 tiba.
 *
 * Setiap peralihan disemak terhadap LaporanSemakan::ALIRAN, jadi keadaan
 * tidak boleh dilangkau walaupun borang dihantar terus tanpa melalui UI.
 * Setiap tindakan turut dicatat dalam approval_logs (jadual sedia ada) dan
 * dalam jejak audit berpusat.
 */
class LaporanSemakanService
{
    // Servis ini TIDAK lagi bergantung kepada KemajuanAnalisisService:
    // pautannya kepada status peringkat dibuang bersama peringkat 4 dan 5
    // (lihat nota kelas). Ia akan kembali apabila proses peringkat itu
    // ditetapkan.
    public function __construct(
        private readonly AuditTrailService $audit,
    ) {}

    public const JENIS_LALAI = 'inventori';

    /**
     * Tindakan jejak audit bagi penyerahan laporan kepada NACSA.
     *
     * Inilah satu-satunya rekod bahawa penyerahan telah berlaku:
     * penyerahan TIDAK mengubah `laporan_semakan.status` (laporan
     * kekal Sah), jadi jejak inilah yang membezakan "disahkan KB" daripada
     * "telah diserahkan kepada NACSA".
     */
    public const ACTION_DELIVERED = 'report_delivered';

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

        // FASA AKAN DATANG: dahulu penghantaran ini turut menggerakkan
        // peringkat "Jana Laporan" dan "Semakan & Kelulusan" aliran kerja
        // lama. Peringkat 4 dan 5 baharu belum ditakrifkan prosesnya, jadi
        // tiada status peringkat disentuh di sini sehingga ia ditetapkan.
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

        // FASA AKAN DATANG: status peringkat tidak disentuh — lihat nota
        // kelas. Pengembalian ialah sebahagian daripada kitaran semakan
        // peringkat 5, yang prosesnya belum ditetapkan.
        return $laporan;
    }

    /**
     * KB menekan "Sahkan".
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

        // FASA AKAN DATANG: peringkat 4 dan 5 tidak ditandakan Selesai di
        // sini — lihat nota kelas.
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
            self::ACTION_DELIVERED,
            LaporanSemakan::SAH,
            LaporanSemakan::SAH,
            $user,
            ['report_type' => $laporan->report_type, 'stage' => AliranKerja::SEMAKAN_KELULUSAN],
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
