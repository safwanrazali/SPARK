<?php

namespace App\Services;

use App\Models\WorkflowStageStatus;
use App\Support\AliranKerja;
use App\Support\SyaratPeringkat;
use Illuminate\Support\Collection;

/**
 * Ringkasan kemajuan entiti daripada peringkat yang TELAH DIMUATKAN.
 *
 * Dipisahkan daripada KemajuanAnalisisService kerana kesemuanya tulen dan
 * tanpa query: satu Collection peringkat masuk, satu jawapan keluar. Itulah
 * yang membolehkan senarai dan papan pemuka meringkaskan ratusan entiti
 * daripada satu muatan sahaja.
 *
 * Pemalar KESELURUHAN_* kekal pada KemajuanAnalisisService kerana ia sudah
 * menjadi sebahagian daripada antara muka awamnya; kelas ini merujuknya di
 * sana dan TIDAK menduakan nilainya.
 *
 * Peraturan di sini TIDAK berubah semasa pemisahan. KemajuanAnalisisService
 * kekal sebagai pintu masuk awam dan hanya meneruskan panggilan ke sini.
 */
final class KemajuanAnalisisRingkasan
{
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
     * Adakah entiti ini telah MEMASUKI aliran kerja?
     *
     * Soalan yang BERBEZA daripada dalamAliranKerja(), dan perbezaannya
     * timbul daripada peringkat 1.1 yang berderivasi:
     *
     *   telahMemasukiAliran()  ada data peringkat 1.1 — kerja telah bermula
     *   dalamAliranKerja()     peringkat 1.1 SELESAI — ketiga-tiga medannya ada
     *
     * Entiti yang baru direkod Tarikh Terima berada di antara kedua-duanya:
     * ia sedang dikerjakan, dan peringkat 1.2 mungkin sudah terbuka, tetapi
     * peringkat 1.1 belum Selesai kerana No. Rujukan masih menunggu PKD.
     *
     * Baris peringkat sahaja TIDAK memadai sebagai ujian: setSemula()
     * mengekalkan baris dan hanya mengosongkan datanya, jadi entiti yang
     * ditetapkan semula tetap mempunyai baris.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function telahMemasukiAliran(?Collection $peringkat): bool
    {
        $satuSatu = $peringkat?->get(AliranKerja::PENERIMAAN_DATA);

        return $satuSatu !== null && ! $satuSatu->isBelumMula();
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
     * Status keseluruhan entiti — dikira, tidak pernah disimpan.
     *
     * 'Siap' hanya apabila kesemua peringkat FASA SEMASA Selesai.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function keseluruhanDaripada(?Collection $peringkat): string
    {
        if ($peringkat === null || $peringkat->isEmpty()) {
            return KemajuanAnalisisService::KESELURUHAN_BELUM_MULA;
        }

        $semasa = $this->fasaSemasaSahaja($peringkat);

        $jumlah = count(AliranKerja::semasa());
        $selesai = $semasa->where('status', WorkflowStageStatus::SELESAI)->count();

        if ($selesai >= $jumlah) {
            return KemajuanAnalisisService::KESELURUHAN_SIAP;
        }

        $adaKemajuan = $selesai > 0
            || $semasa->where('status', WorkflowStageStatus::DALAM_PROSES)->isNotEmpty();

        return $adaKemajuan
            ? KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES
            : KemajuanAnalisisService::KESELURUHAN_BELUM_MULA;
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
     * BELUM DILEPASI, atau peringkat terakhir fasa ini jika semuanya lepas.
     *
     * "Dilepasi" menggunakan peraturan yang SAMA dengan
     * KemajuanAnalisisGating, bukan status Selesai semata-mata. Perbezaannya
     * penting: No. Rujukan ialah syarat SELESAI bagi peringkat 1.1–1.3 dan
     * 3.1, tetapi ia dimasukkan oleh PKD dan BUKAN syarat lanjut. Dengan
     * ujian isSelesai() sahaja, entiti yang pegawainya sudah bekerja hingga
     * peringkat 3.1 kekal dilaporkan "di peringkat 1.1" selagi PKD belum
     * merekod nombor rujukan — bercanggah dengan prinsip yang dipegang di
     * seluruh sistem, iaitu No. Rujukan tidak menahan kerja peringkat
     * berikutnya.
     *
     * @param  Collection<string, WorkflowStageStatus>|null  $peringkat
     */
    public function peringkatSemasa(?Collection $peringkat): string
    {
        if ($peringkat === null || $peringkat->isEmpty()) {
            return AliranKerja::PERTAMA;
        }

        foreach (AliranKerja::semasa() as $stage) {
            if (! $this->dilepasi($peringkat->get($stage), $stage)) {
                return $stage;
            }
        }

        return AliranKerja::TERAKHIR_SEMASA;
    }

    /**
     * Adakah peringkat ini sudah tidak lagi menahan peringkat berikutnya?
     *
     * Mencerminkan KemajuanAnalisisGating::ralatPendahulu():
     *
     * - Peringkat dengan `syarat_lanjut`: cukup medan tersebut ADA.
     * - Peringkat tanpa `syarat_lanjut` (peringkat 2): mesti benar-benar Selesai.
     *
     * Semakan penugasan Pegawai Analisis SENGAJA tidak disertakan: ia
     * memerlukan query, sedangkan kaedah ini dipanggil sekali bagi SETIAP
     * baris senarai entiti dan mesti kekal bebas query.
     */
    private function dilepasi(?WorkflowStageStatus $rekod, string $stage): bool
    {
        if ($rekod === null) {
            return false;
        }

        if (AliranKerja::syaratLanjut($stage) === []) {
            return $rekod->isSelesai();
        }

        return SyaratPeringkat::medanLanjutBelumDirekod($rekod, $stage) === [];
    }

    /**
     * Kelas badge bagi status keseluruhan (selaras dengan modul lain).
     */
    public function badgeKeseluruhan(string $keseluruhan): string
    {
        return [
            KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
            KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
        ][$keseluruhan] ?? 'status-tinggi';
    }

    /**
     * Peringkat fasa semasa sahaja.
     *
     * @param  Collection<string, WorkflowStageStatus>  $peringkat
     * @return Collection<string, WorkflowStageStatus>
     */
    public function fasaSemasaSahaja(Collection $peringkat): Collection
    {
        $semasa = AliranKerja::semasa();

        return $peringkat->filter(
            fn (WorkflowStageStatus $p): bool => in_array($p->stage, $semasa, true)
        );
    }
}
