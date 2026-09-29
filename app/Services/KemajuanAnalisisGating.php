<?php

namespace App\Services;

use App\Models\EntitiAssignment;
use App\Models\WorkflowStageStatus;
use App\Support\AliranKerja;
use App\Support\SyaratPeringkat;
use Illuminate\Support\Collection;

/**
 * Syarat pendahulu: bolehkah peringkat ini bergerak sekarang?
 *
 * Dipisahkan daripada KemajuanAnalisisService kerana ia menjawab soalan yang
 * berbeza. Servis itu MENGUBAH status peringkat; kelas ini hanya MEMBACA
 * keadaan dan mengembalikan ayat ralat — ia tidak pernah menulis, tidak
 * merekod audit dan tidak membuka transaksi.
 *
 * Peringkat yang sedang dinilai DIHANTAR MASUK dan bukan disoal di sini,
 * supaya tiada kebergantungan berpusing dengan KemajuanAnalisisService dan
 * supaya satu muatan peringkat boleh menjawab beberapa soalan.
 *
 * Peraturan di sini TIDAK berubah semasa pemisahan. KemajuanAnalisisService
 * kekal sebagai pintu masuk awam (ralatPeringkat(), bolehTandakan()) dan
 * hanya meneruskan panggilan ke sini.
 */
final class KemajuanAnalisisGating
{
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
     *
     * @param  Collection<string, WorkflowStageStatus>  $peringkat
     */
    public function ralat(
        string $agencyCode,
        Collection $peringkat,
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

        if ($peringkat->isEmpty()) {
            return 'Entiti ini belum didaftarkan dalam Kemajuan Analisis Entiti.';
        }

        $sebelumKunci = AliranKerja::sebelum($stage);

        if ($sebelumKunci === null) {
            return null;
        }

        $sebelum = $peringkat->get($sebelumKunci);

        if ($status === WorkflowStageStatus::SELESAI) {
            return $this->ralatPendahulu($agencyCode, $sebelum, $sebelumKunci);
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
     * Adakah penugasan Pegawai Analisis masih tertunggak bagi peringkat ini?
     *
     * Hanya bermakna pada peringkat yang menuntutnya (1.2).
     */
    public function penugasanTertunggak(string $agencyCode, string $stage): bool
    {
        if (! AliranKerja::perluPenugasanUntukSelesai($stage)) {
            return false;
        }

        return ! $this->adaPenugasanAktif($agencyCode);
    }

    /**
     * Adakah pendahulu membenarkan peringkat berikutnya dimulakan?
     *
     * DUA peraturan, dan perbezaannya disengajakan:
     *
     * - Peringkat dengan `syarat_lanjut`: cukup medan tersebut ADA. Peringkat
     *   itu tidak semestinya Selesai. Peringkat 1.1 memerlukannya kerana No.
     *   Rujukan miliknya dimasukkan oleh PKD, dan kerja peringkat 1.2 tidak
     *   sepatutnya tertahan menunggu pegawai lain.
     * - Peringkat lain: peraturan lalai — mesti benar-benar Selesai.
     */
    private function ralatPendahulu(string $agencyCode, ?WorkflowStageStatus $sebelum, string $sebelumKunci): ?string
    {
        $syarat = AliranKerja::syaratLanjut($sebelumKunci);

        if ($syarat === []) {
            if ($sebelum === null || ! $sebelum->isSelesai()) {
                return sprintf('Peringkat %s mesti Selesai terlebih dahulu.', AliranKerja::labelPenuh($sebelumKunci));
            }

            return $this->ralatPenugasan($agencyCode, $sebelumKunci);
        }

        $tiada = SyaratPeringkat::medanLanjutBelumDirekod($sebelum, $sebelumKunci);

        if ($tiada !== []) {
            return sprintf(
                'Peringkat %s memerlukan %s terlebih dahulu.',
                AliranKerja::labelPenuh($sebelumKunci),
                implode(' dan ', array_map(
                    fn (string $lajur) => AliranKerja::labelMedan($sebelumKunci, $lajur),
                    $tiada,
                )),
            );
        }

        return $this->ralatPenugasan($agencyCode, $sebelumKunci);
    }

    /**
     * Peringkat yang menuntut penugasan tidak boleh membuka peringkat
     * seterusnya sehingga seorang Pegawai Analisis ditugaskan.
     */
    private function ralatPenugasan(string $agencyCode, string $sebelumKunci): ?string
    {
        if (! AliranKerja::perluPenugasanUntukLanjut($sebelumKunci)) {
            return null;
        }

        return $this->adaPenugasanAktif($agencyCode) ? null : sprintf(
            'Peringkat %s memerlukan Pegawai Analisis ditugaskan terlebih dahulu.',
            AliranKerja::labelPenuh($sebelumKunci),
        );
    }

    /**
     * `entiti_assignment` disoal terus dan bukan melalui EntityAssignmentService
     * supaya semakan syarat ini tidak bergantung kepada servis penugasan hanya
     * untuk satu soalan ya/tidak.
     */
    private function adaPenugasanAktif(string $agencyCode): bool
    {
        return EntitiAssignment::query()
            ->forAgency($agencyCode)
            ->active()
            ->exists();
    }
}
