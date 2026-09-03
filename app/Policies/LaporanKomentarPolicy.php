<?php

namespace App\Policies;

use App\Models\LaporanKomentar;
use App\Models\User;
use App\Services\EntityAccessService;

/**
 * Kebenaran komentar laporan.
 *
 * TIGA konsep berasingan yang TIDAK boleh dicampuradukkan:
 *
 *   PENGLIHATAN  — PA, KB dan PPA melihat KESEMUA komentar KB/PPA bagi
 *                  entiti yang boleh diaksesnya. Peranan lain (PS, TPII,
 *                  PPR, PKD) tiada akses langsung kepada modul komentar,
 *                  walaupun mereka boleh membuka laporan itu sendiri.
 *
 *   PEMILIKAN    — hanya pengarang boleh menyunting dan memadam komentarnya.
 *                  Pemilikan TIDAK PERNAH digunakan sebagai ujian
 *                  penglihatan: KB-1 melihat komentar KB-2, KB melihat
 *                  komentar PPA, dan sebaliknya.
 *
 *   TINDAKAN     — hanya Pegawai Analisis boleh menanda "Tindakan Diambil"
 *                  (dan membatalkannya jika tersilap tanda).
 *
 * Setiap kebenaran turut tertakluk kepada kawalan akses entiti Fasa 4, jadi
 * menukar id komentar atau agency_code dalam permintaan tidak membuka
 * komentar entiti yang tidak boleh diakses pengguna.
 */
class LaporanKomentarPolicy
{
    public function __construct(private readonly EntityAccessService $access) {}

    /**
     * Peranan yang mengambil bahagian dalam modul komentar langsung.
     */
    public function viewAny(User $user): bool
    {
        return $this->bolehMengomen($user) || $user->isAnalyst();
    }

    /**
     * Melihat satu komentar — peranan yang layak DAN akses kepada entitinya.
     * Tiada semakan pengarang di sini; itu sengaja.
     */
    public function view(User $user, LaporanKomentar $komentar): bool
    {
        return $this->viewAny($user) && $this->access->canAccess($user, $komentar->agency_code);
    }

    /**
     * Menulis komentar — KB dan PPA sahaja. Akses entiti disemak berasingan
     * terhadap rekod analisis yang dikomentari (lihat controller).
     */
    public function create(User $user): bool
    {
        return $this->bolehMengomen($user);
    }

    /**
     * Menyunting — pengarang sahaja. PA tidak boleh menyunting supaya maklum
     * balas asal kekal betul-betul seperti yang ditulis.
     */
    public function update(User $user, LaporanKomentar $komentar): bool
    {
        return $this->milik($user, $komentar);
    }

    /**
     * Memadam — pengarang sahaja. Bukan KB lain, bukan PPA lain, bukan PA,
     * bukan Pentadbir Sistem.
     */
    public function delete(User $user, LaporanKomentar $komentar): bool
    {
        return $this->milik($user, $komentar);
    }

    /**
     * Menanda "Tindakan Diambil" — Pegawai Analisis sahaja, dan hanya pada
     * komentar yang masih Terbuka.
     */
    public function tandakanTindakan(User $user, LaporanKomentar $komentar): bool
    {
        return ! $komentar->sudahDitindak() && $this->bolehBertindak($user, $komentar);
    }

    /**
     * Membatalkan tanda tersebut — juga Pegawai Analisis sahaja. Ini undo
     * bagi silap tanda, BUKAN penolakan atau pemulangan.
     */
    public function batalkanTindakan(User $user, LaporanKomentar $komentar): bool
    {
        return $komentar->sudahDitindak() && $this->bolehBertindak($user, $komentar);
    }

    private function bolehMengomen(User $user): bool
    {
        return $user->isKetuaBahagian() || $user->isCoordinator();
    }

    private function bolehBertindak(User $user, LaporanKomentar $komentar): bool
    {
        return $user->isAnalyst() && $this->access->canAccess($user, $komentar->agency_code);
    }

    private function milik(User $user, LaporanKomentar $komentar): bool
    {
        return $this->bolehMengomen($user)
            && $user->id === $komentar->user_id
            && $this->access->canAccess($user, $komentar->agency_code);
    }
}
