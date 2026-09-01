<?php

namespace App\Http\Controllers;

use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Peringkat 1.1 aliran kerja — "Penerimaan Data".
 *
 * MENYIAPKAN peringkat 1.1 kini TIADA pencetus. Kaedah lama — menanda
 * sekumpulan entiti melalui kotak semak dan menekan "Kemas Kini" — telah
 * dibuang bersama laluannya, kerana peringkat ini tidak lagi ditentukan
 * secara pukal. Apa yang menggantikannya belum ditetapkan.
 *
 * Operasi domainnya kekal utuh dalam
 * KemajuanAnalisisService::lengkapkanPenerimaan(): apabila pencetus baharu
 * diberikan, ia disambungkan ke situ dan bukan ditulis semula.
 *
 * Yang tinggal di sini ialah "Set Semula" — hak Ketua Bahagian membuka
 * semula entiti yang telah dikunci.
 *
 * Nota carta aliran menyatakan hanya Ketua Bahagian boleh membuka semula
 * entiti yang telah dikunci — itulah tindakan "Set Semula" di bawah.
 *
 * Skrin pendaftaran dikongsi dengan modul Penugasan (kedua-duanya ialah
 * "Penetapan Entiti"); paparan setiap panel dikawal oleh gate.
 */
class PendaftaranEntitiController extends Controller
{
    public function __construct(
        private readonly KemajuanAnalisisService $kemajuan,
        private readonly EntityAssignmentService $assignments,
    ) {}

    /**
     * Buka semula entiti yang telah dikunci — Ketua Bahagian sahaja.
     *
     * Kemajuan entiti dikosongkan sepenuhnya dan penugasan aktif ditarik
     * balik, kerana entiti itu keluar semula daripada pandangan PPA.
     */
    public function setSemula(Request $request, string $agencyCode)
    {
        Gate::authorize('reset-entity-registration');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'reason' => 'sebab',
        ]);

        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        if (! $this->kemajuan->penerimaanSelesai($agencyCode)) {
            return back()->withErrors([
                'reason' => sprintf('%s belum dikunci, jadi tiada apa untuk ditetapkan semula.', $entiti['agency_code']),
            ]);
        }

        $this->kemajuan->setSemula($agencyCode, $request->user(), $data['reason'] ?? null);

        // Entiti yang ditetapkan semula tidak lagi kelihatan kepada PPA, jadi
        // penugasan yang masih aktif akan menjadi yatim jika dibiarkan.
        if ($this->assignments->activeFor($agencyCode) !== null) {
            $this->assignments->unassign(
                $agencyCode,
                $request->user(),
                $data['reason'] ?? 'Pendaftaran entiti ditetapkan semula.',
            );
        }

        return back()->with('success', sprintf(
            'Peringkat 1.1 Penerimaan Data bagi %s ditetapkan semula kepada Belum Mula.',
            $entiti['agency_code'],
        ));
    }
}
