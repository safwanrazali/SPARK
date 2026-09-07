<?php

namespace App\Policies;

use App\Models\LaporanCatatan;
use App\Models\User;
use App\Services\EntityAccessService;

/**
 * Kebenaran catatan laporan.
 *
 * TIGA konsep berasingan yang TIDAK boleh dicampuradukkan:
 *
 *   PENGLIHATAN  — PA, KB dan PPA melihat KESEMUA catatan KB/PPA bagi
 *                  entiti yang boleh diaksesnya. Peranan lain (PS, TPII,
 *                  PPR, PKD) tiada akses langsung kepada modul catatan,
 *                  walaupun mereka boleh membuka laporan itu sendiri.
 *
 *   PEMILIKAN    — hanya pengarang boleh menyunting dan memadam catatannya.
 *                  Pemilikan TIDAK PERNAH digunakan sebagai ujian
 *                  penglihatan: KB-1 melihat catatan KB-2, KB melihat
 *                  catatan PPA, dan sebaliknya.
 *
 *   TINDAKAN     — hanya Pegawai Analisis boleh menanda "Tindakan Diambil"
 *                  (dan membatalkannya jika tersilap tanda).
 *
 * Setiap kebenaran turut tertakluk kepada kawalan akses entiti Fasa 4, jadi
 * menukar id catatan atau agency_code dalam permintaan tidak membuka
 * catatan entiti yang tidak boleh diakses pengguna.
 */
class LaporanCatatanPolicy
{
    public function __construct(private readonly EntityAccessService $access) {}

    /**
     * Peranan yang mengambil bahagian dalam modul catatan langsung.
     */
    public function viewAny(User $user): bool
    {
        return $this->bolehMencatat($user) || $user->isAnalyst();
    }

    /**
     * Melihat satu catatan — peranan yang layak DAN akses kepada entitinya.
     * Tiada semakan pengarang di sini; itu sengaja.
     */
    public function view(User $user, LaporanCatatan $catatan): bool
    {
        return $this->viewAny($user) && $this->access->canAccess($user, $catatan->agency_code);
    }

    /**
     * Menulis catatan — KB dan PPA sahaja. Akses entiti disemak berasingan
     * terhadap rekod analisis yang diberi catatan (lihat controller).
     */
    public function create(User $user): bool
    {
        return $this->bolehMencatat($user);
    }

    /**
     * Menyunting — pengarang sahaja. PA tidak boleh menyunting supaya maklum
     * balas asal kekal betul-betul seperti yang ditulis.
     */
    public function update(User $user, LaporanCatatan $catatan): bool
    {
        return $this->milik($user, $catatan);
    }

    /**
     * Memadam — pengarang sahaja. Bukan KB lain, bukan PPA lain, bukan PA,
     * bukan Pentadbir Sistem.
     */
    public function delete(User $user, LaporanCatatan $catatan): bool
    {
        return $this->milik($user, $catatan);
    }

    /**
     * Menanda "Tindakan Diambil" — Pegawai Analisis sahaja, dan hanya pada
     * catatan yang masih Terbuka.
     */
    public function tandakanTindakan(User $user, LaporanCatatan $catatan): bool
    {
        return ! $catatan->sudahDitindak() && $this->bolehBertindak($user, $catatan);
    }

    /**
     * Membatalkan tanda tersebut — juga Pegawai Analisis sahaja. Ini undo
     * bagi silap tanda, BUKAN penolakan atau pemulangan.
     */
    public function batalkanTindakan(User $user, LaporanCatatan $catatan): bool
    {
        return $catatan->sudahDitindak() && $this->bolehBertindak($user, $catatan);
    }

    private function bolehMencatat(User $user): bool
    {
        return $user->isKetuaBahagian() || $user->isCoordinator();
    }

    private function bolehBertindak(User $user, LaporanCatatan $catatan): bool
    {
        return $user->isAnalyst() && $this->access->canAccess($user, $catatan->agency_code);
    }

    private function milik(User $user, LaporanCatatan $catatan): bool
    {
        return $this->bolehMencatat($user)
            && $user->id === $catatan->user_id
            && $this->access->canAccess($user, $catatan->agency_code);
    }
}
