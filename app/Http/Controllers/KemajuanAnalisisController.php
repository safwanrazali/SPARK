<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\AnalisisInventori;
use App\Models\LaporanSemakan;
use App\Models\WorkflowStatus;
use App\Services\EntityAccessService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tindakan pada halaman "Kemajuan Analisis Entiti".
 *
 * Setiap tindakan milik satu peranan pada satu peringkat tertentu:
 *
 *   Peringkat 2, 3, 4  "Selesai"           → Pegawai Analisis
 *   Peringkat 5        "Hantar kepada PPA" → Pegawai Analisis
 *   Peringkat 6        "Hantar kepada KB"  → PPA
 *                      "Kembalikan"        → PPA atau KB (Catatan wajib)
 *                      "Sahkan"            → Ketua Bahagian
 *   Peringkat 7        "Hantar"            → gate submit-to-nacsa
 *
 * Peringkat 5 dan 6 tiada tindakan "Selesai" — kedua-duanya hanya menjadi
 * Selesai apabila Ketua Bahagian mengesahkan laporan. Tiada route memberikan
 * mana-mana peranan jalan pintas ke situ.
 *
 * Kawalan akses entiti dikuatkuasakan oleh middleware `entity.access` pada
 * setiap route, jadi Pegawai Analisis tidak boleh menyentuh entiti pegawai
 * lain walaupun melalui permintaan langsung.
 */
class KemajuanAnalisisController extends Controller
{
    /**
     * Peringkat yang ditandakan Selesai oleh Pegawai Analisis melalui
     * butang "Selesai" pada halaman kemajuan.
     */
    private const PERINGKAT_PA = [
        WorkflowStatus::STAGE_SEMAKAN_AWAL,
        WorkflowStatus::STAGE_PENYEDIAAN,
        WorkflowStatus::STAGE_ANALISIS,
    ];

    public function __construct(
        private readonly KemajuanAnalisisService $kemajuan,
        private readonly LaporanSemakanService $semakan,
        private readonly EntityAccessService $access,
    ) {}

    /**
     * Tandakan peringkat 2, 3 atau 4 sebagai Selesai.
     */
    public function selesai(Request $request, string $agencyCode, int $stage)
    {
        Gate::authorize('advance-analysis-stage');

        $entiti = $this->entitiAtauGagal($agencyCode);

        // Hanya peringkat 2, 3 dan 4 boleh ditandakan Selesai secara terus.
        // Peringkat 5 dan 6 sengaja tiada di sini: permintaan yang cuba
        // menandakannya Selesai ditolak sebelum sampai ke servis.
        abort_unless(in_array($stage, self::PERINGKAT_PA, true), 404);

        try {
            $this->kemajuan->tandakanSelesai($agencyCode, $stage, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Peringkat %02d — %s bagi %s ditandakan Selesai.',
            $stage,
            WorkflowStatus::getStageName($stage),
            $entiti['agency_code'],
        ));
    }

    /**
     * "Hantar kepada PPA" — peringkat 5 bermula.
     *
     * Ini satu-satunya laluan masuk kepada kitaran semakan. Peringkat 5 dan 6
     * menjadi Dalam Proses di sini, BUKAN Selesai: laporan hanya selesai
     * setelah Ketua Bahagian mengesahkannya (aliran kerja bahagian 15).
     */
    public function hantar(Request $request, string $agencyCode)
    {
        Gate::authorize('advance-analysis-stage');

        $entiti = $this->entitiAtauGagal($agencyCode);

        // Dua syarat, kedua-duanya disemak di pelayan supaya butang yang
        // dilumpuhkan pada antara muka bukan satu-satunya halangan:
        //
        // 1. Peringkat "Analisis Data" mesti Selesai (bahagian 21).
        // 2. Borang Input Analisis Inventori Kriptografi mesti Lengkap —
        //    draf separa siap tidak memadai (bahagian 7).
        if (! $this->kemajuan->peringkat($agencyCode)->get(WorkflowStatus::STAGE_ANALISIS)?->isSelesai()) {
            return back()->withErrors([
                'laporan' => 'Peringkat 04 — Analisis Data perlu Selesai sebelum laporan boleh dihantar.',
            ]);
        }

        if (! $this->analisisLengkap($agencyCode)) {
            return back()->withErrors([
                'laporan' => 'Borang Input Analisis Inventori Kriptografi perlu Lengkap sebelum laporan boleh dihantar.',
            ]);
        }

        // Laporan dicipta pada penghantaran pertama; penghantaran semula
        // selepas dikembalikan menggunakan rekod yang sama.
        $laporan = $this->semakan->mulakan($entiti);

        try {
            $this->semakan->hantarKepadaPPA($laporan, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withErrors(['laporan' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Laporan bagi %s telah dihantar kepada Pegawai Penyelaras Analisis.',
            $entiti['agency_code'],
        ));
    }

    /**
     * "Hantar" oleh PPA — laporan diserahkan kepada Ketua Bahagian.
     */
    public function semak(Request $request, string $agencyCode)
    {
        Gate::authorize('review-report');

        $entiti = $this->entitiAtauGagal($agencyCode);
        $laporan = $this->laporanAtauGagal($agencyCode);

        try {
            $this->semakan->hantarKepadaKB($laporan, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withErrors(['laporan' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Laporan bagi %s telah dihantar kepada Ketua Bahagian.',
            $entiti['agency_code'],
        ));
    }

    /**
     * "Kembalikan" oleh PPA atau Ketua Bahagian — Catatan wajib.
     */
    public function kembalikan(Request $request, string $agencyCode)
    {
        $entiti = $this->entitiAtauGagal($agencyCode);
        $laporan = $this->laporanAtauGagal($agencyCode);

        // Siapa yang boleh mengembalikan bergantung kepada di tangan siapa
        // laporan itu berada sekarang.
        Gate::authorize(
            $laporan->status === LaporanSemakan::MENUNGGU_KB ? 'approve-report' : 'review-report'
        );

        $data = $request->validate([
            'catatan' => ['required', 'string', 'max:2000'],
        ], [
            'catatan.required' => 'Catatan wajib diisi sebelum laporan boleh dikembalikan.',
        ], [
            'catatan' => 'catatan',
        ]);

        try {
            $this->semakan->kembalikan($laporan, $request->user(), $data['catatan']);
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withInput()->withErrors(['catatan' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Laporan bagi %s telah dikembalikan kepada Pegawai Analisis.',
            $entiti['agency_code'],
        ));
    }

    /**
     * "Sahkan" oleh Ketua Bahagian — peringkat 5 dan 6 menjadi Selesai.
     */
    public function sahkan(Request $request, string $agencyCode)
    {
        Gate::authorize('approve-report');

        $entiti = $this->entitiAtauGagal($agencyCode);
        $laporan = $this->laporanAtauGagal($agencyCode);

        try {
            $this->semakan->sahkan($laporan, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withErrors(['laporan' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Laporan bagi %s telah disahkan. Jana Laporan dan Semakan & Kelulusan kini Selesai.',
            $entiti['agency_code'],
        ));
    }

    /**
     * "Hantar" pada peringkat 7 — penyerahan laporan yang telah disahkan
     * kepada NACSA, dan penutupan entiti.
     */
    public function serah(Request $request, string $agencyCode)
    {
        Gate::authorize('submit-to-nacsa');

        $entiti = $this->entitiAtauGagal($agencyCode);
        $laporan = $this->semakan->untuk($agencyCode);

        // Hanya laporan yang telah disahkan boleh diserahkan.
        if ($laporan === null || ! $laporan->isSah()) {
            return back()->withErrors([
                'stage' => 'Laporan perlu berstatus Sah sebelum boleh diserahkan kepada NACSA.',
            ]);
        }

        try {
            $this->kemajuan->tandakanSelesai(
                $agencyCode,
                WorkflowStatus::STAGE_PENYERAHAN,
                $request->user(),
            );

            $this->semakan->rekodPenyerahan($laporan, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Laporan bagi %s telah diserahkan kepada NACSA. Kemajuan Analisis Entiti kini %s.',
            $entiti['agency_code'],
            $this->kemajuan->keseluruhan($agencyCode),
        ));
    }

    /**
     * Adakah Borang Input Analisis Inventori Kriptografi telah disimpan
     * sebagai Lengkap?
     *
     * Draf sengaja tidak dikira — itulah beza antara "Simpan Draf" dan
     * "Simpan Dapatan" (aliran kerja bahagian 7).
     */
    private function analisisLengkap(string $agencyCode): bool
    {
        return AnalisisInventori::query()
            ->where('agency_code', $agencyCode)
            ->where('selesai', true)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    private function entitiAtauGagal(string $agencyCode): array
    {
        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        return $entiti;
    }

    private function laporanAtauGagal(string $agencyCode): LaporanSemakan
    {
        $laporan = $this->semakan->untuk($agencyCode);

        abort_if($laporan === null, 404, 'Laporan bagi entiti ini belum dijana.');

        return $laporan;
    }
}
