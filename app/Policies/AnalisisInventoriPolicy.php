<?php

namespace App\Policies;

use App\Models\AnalisisInventori;
use App\Models\User;
use App\Services\EntityAccessService;

/**
 * FASA 4 — kawalan akses rekod analisis dan laporan yang terhasil daripadanya.
 *
 * Peraturan peranan sedia ada dikekalkan (gate `manage-analysis`:
 * Pentadbir + Pegawai Analisis). Yang ditambah ialah dimensi entiti:
 * Pegawai Analisis hanya boleh menyentuh rekod bagi entiti yang ditugaskan
 * kepadanya.
 */
class AnalisisInventoriPolicy
{
    public function __construct(private readonly EntityAccessService $access) {}

    /**
     * Melihat senarai — senarai itu sendiri ditapis melalui scope accessibleBy().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Melihat satu rekod analisis (termasuk pratonton laporan).
     */
    public function view(User $user, AnalisisInventori $analisis): bool
    {
        return $this->access->canAccess($user, $analisis->agency_code);
    }

    /**
     * Menjana atau memuat turun laporan bagi rekod ini.
     */
    public function generateReport(User $user, AnalisisInventori $analisis): bool
    {
        return $this->view($user, $analisis);
    }

    /**
     * Mengemas kini dapatan analisis bagi rekod ini.
     */
    public function update(User $user, AnalisisInventori $analisis): bool
    {
        return $this->bolehInputAnalisis($user)
            && $this->access->canAccess($user, $analisis->agency_code);
    }

    /**
     * Peranan yang dibenarkan memasukkan dapatan analisis — selaras dengan
     * gate `manage-analysis` sedia ada supaya tiada peraturan peranan baharu
     * diperkenalkan dalam fasa ini.
     */
    private function bolehInputAnalisis(User $user): bool
    {
        return $user->isAdministrator() || $user->isAnalyst();
    }
}
