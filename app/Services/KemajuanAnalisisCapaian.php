<?php

namespace App\Services;

use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Support\AliranKerja;
use App\Support\SyaratPeringkat;
use Illuminate\Support\Collection;

/**
 * Soalan capaian bagi SATU peringkat: bolehkah pengguna ini melihatnya, dan
 * bolehkah dia menyuntingnya?
 *
 * Kelas ini TIDAK mencipta kebenaran baharu. Ia hanya menggabungkan tiga
 * lapisan yang sudah wujud, mengikut susunan yang sama seperti yang
 * dikuatkuasakan oleh KemajuanAnalisisController:
 *
 *   1. Peranan   — gate yang dinamakan oleh AliranKerja::gate()
 *   2. Giliran   — syarat pendahulu, peraturan yang sama seperti
 *                  KemajuanAnalisisGating::ralatPendahulu()
 *   3. Fasa      — peringkat 3.2, 4 dan 5 belum dibina, jadi tiada tindakan
 *
 * Kawalan akses ENTITI ialah lapisan keempat dan ia BUKAN di sini: ia kekal
 * milik EntityAccessService dan middleware `entity.access`, yang menapis
 * setiap route sebelum controller dijalankan.
 *
 * TULEN dan tanpa keadaan: baris peringkat dan penugasan DIHANTAR MASUK dan
 * bukan disoal di sini, supaya satu muatan boleh menjawab kelapan-lapan
 * peringkat tanpa satu query bagi setiap soalan, dan supaya tiada
 * kebergantungan berpusing dengan KemajuanAnalisisService.
 *
 * PENTING — jawapan di sini ialah untuk PAPARAN. Ia menentukan borang mana
 * yang bermakna untuk dipaparkan, bukan apa yang dibenarkan: setiap tindakan
 * kekal disemak semula pada pelayan oleh gate route/controller dan oleh
 * KemajuanAnalisisService. Borang yang dipaparkan bukan mekanisme kebenaran.
 */
final class KemajuanAnalisisCapaian
{
    /**
     * Status satu peringkat daripada baris yang telah dimuatkan.
     *
     * @param  Collection<string, WorkflowStageStatus>  $peringkat
     */
    public function status(Collection $peringkat, string $kunci): string
    {
        return $peringkat->get($kunci)?->status ?? WorkflowStageStatus::BELUM_MULA;
    }

    /**
     * @param  Collection<string, WorkflowStageStatus>  $peringkat
     */
    public function selesai(Collection $peringkat, string $kunci): bool
    {
        return $this->status($peringkat, $kunci) === WorkflowStageStatus::SELESAI;
    }

    /**
     * Adakah pendahulu peringkat ini membenarkannya bergerak?
     *
     * DUA peraturan, mencerminkan KemajuanAnalisisGating::ralatPendahulu():
     *
     * - Pendahulu dengan `syarat_lanjut`: cukup medan tersebut ADA. Ia tidak
     *   semestinya Selesai — itulah yang membenarkan peringkat 1.2 bermula
     *   sementara No. Rujukan peringkat 1.1 masih menunggu PKD.
     * - Pendahulu lain: mesti benar-benar Selesai.
     *
     * Penugasan Pegawai Analisis BUKAN medan peringkat, jadi ia disemak
     * berasingan — sama seperti KemajuanAnalisisGating::ralatPenugasan().
     *
     * @param  Collection<string, WorkflowStageStatus>  $peringkat
     */
    public function terbuka(Collection $peringkat, ?EntitiAssignment $penugasan, string $kunci): bool
    {
        $sebelum = AliranKerja::sebelum($kunci);

        if ($sebelum === null) {
            return true;
        }

        if (AliranKerja::perluPenugasanUntukLanjut($sebelum) && $penugasan === null) {
            return false;
        }

        if (AliranKerja::syaratLanjut($sebelum) === []) {
            return $this->selesai($peringkat, $sebelum);
        }

        return SyaratPeringkat::medanLanjutBelumDirekod($peringkat->get($sebelum), $sebelum) === [];
    }

    /**
     * Bolehkah pengguna ini MELIHAT peringkat ini?
     *
     * Melihat bukan hak terhad: sesiapa yang boleh membuka halaman entiti —
     * iaitu sesiapa yang lulus kawalan akses entiti — melihat data setiap
     * peringkatnya dalam jadual "Maklumat Peringkat". Yang tiada untuk
     * dilihat hanyalah peringkat fasa akan datang, kerana modulnya belum
     * dibina.
     *
     * Diasingkan daripada bolehSunting() dengan sengaja: satu peranan boleh
     * membaca peringkat yang bukan tanggungjawabnya, tetapi tidak boleh
     * menyentuhnya.
     */
    public function bolehLihat(?User $pengguna, string $kunci): bool
    {
        return $pengguna !== null
            && AliranKerja::wujud($kunci)
            && AliranKerja::adalahSemasa($kunci);
    }

    /**
     * Bolehkah pengguna ini MENYUNTING peringkat ini?
     *
     * Peringkat yang telah Selesai TIDAK dikecualikan: pemiliknya boleh
     * kembali membetulkan maklumat yang tersalah rekod. Itulah semakan yang
     * sama seperti yang dikuatkuasakan oleh KemajuanAnalisisController —
     * gate peranan + syarat pendahulu — dan bukan senarai kedua yang boleh
     * terpesong daripadanya.
     *
     * Menyunting peringkat yang Selesai TIDAK mengundurkan peringkat lain:
     * KemajuanAnalisisService menerbitkan semula status peringkat ITU sahaja
     * daripada datanya sendiri.
     *
     * @param  Collection<string, WorkflowStageStatus>  $peringkat
     */
    public function bolehSunting(
        ?User $pengguna,
        Collection $peringkat,
        ?EntitiAssignment $penugasan,
        string $kunci,
    ): bool {
        if (! $this->bolehLihat($pengguna, $kunci)) {
            return false;
        }

        $gate = AliranKerja::gate($kunci);

        return $gate !== null
            && $pengguna->can($gate)
            && $this->terbuka($peringkat, $penugasan, $kunci);
    }
}
