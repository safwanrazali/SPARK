<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman pertama yang dilihat pengguna selepas log masuk DAN selepas menukar
 * kata laluan.
 *
 * Peraturannya ialah KEBENARAN, bukan senarai peranan: sesiapa yang boleh
 * membuka papan pemuka mendarat di situ, dan sesiapa yang tidak mendarat pada
 * Kemajuan Analisis Entiti — ruang kerjanya.
 *
 * Pegawai Analisis ialah satu-satunya peranan TANPA papan pemuka keseluruhan
 * (gate `view-dashboard`). Mengalihkannya ke papan pemuka menghasilkan 403.
 * Kerana itu peraturan ini berada di SATU tempat sahaja: menambah atau
 * membuang peranan pada gate itu memindahkan halaman mendarat secara automatik,
 * dan tiada salinan kedua yang boleh terpesong daripadanya.
 */
final class HalamanMendarat
{
    /**
     * URL halaman mendarat bagi pengguna diberi, atau pengguna semasa jika null.
     */
    public static function url(?User $pengguna = null): string
    {
        $bolehLihatPapanPemuka = $pengguna === null
            ? Gate::allows('view-dashboard')
            : Gate::forUser($pengguna)->allows('view-dashboard');

        return $bolehLihatPapanPemuka
            ? route('dashboard')
            : route('workflow.index');
    }
}
