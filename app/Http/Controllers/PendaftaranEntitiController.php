<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Peringkat 1 aliran kerja — "Penerimaan & Pendaftaran Data".
 *
 * Pegawai Penyelaras Rekod menanda entiti yang datanya telah diterima dan
 * didaftarkan, kemudian menekan "Kemas Kini". Entiti yang dikemas kini
 * dikunci: PPR tidak boleh mengubahnya lagi, dan ia mula kelihatan kepada
 * Pegawai Penyelaras Analisis untuk ditugaskan.
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
     * Tandakan "Penerimaan & Pendaftaran Data" Selesai bagi entiti dipilih.
     */
    public function kemasKini(Request $request)
    {
        Gate::authorize('register-entity-data');

        $data = $request->validate([
            'agency_codes' => ['required', 'array', 'min:1'],
            'agency_codes.*' => ['required', 'string'],
        ], [
            'agency_codes.required' => 'Tandakan sekurang-kurangnya satu entiti sebelum mengemas kini.',
        ], [
            'agency_codes' => 'entiti',
        ]);

        $dikemasKini = 0;
        $dilangkau = 0;

        foreach (array_unique($data['agency_codes']) as $agencyCode) {
            $entiti = SektorDirectory::cariEntiti($agencyCode);

            if ($entiti === null) {
                continue;
            }

            // Entiti yang telah dikunci tidak boleh ditanda semula — semakan
            // ini menghalang borang lama atau permintaan langsung daripada
            // memintas kunci tersebut.
            if ($this->kemajuan->pendaftaranSelesai($agencyCode)) {
                $dilangkau++;

                continue;
            }

            try {
                $this->kemajuan->lengkapkanPendaftaran($entiti, $request->user());
                $dikemasKini++;
            } catch (InvalidWorkflowTransitionException $e) {
                return back()->withErrors(['agency_codes' => $e->getMessage()]);
            }
        }

        if ($dikemasKini === 0) {
            return back()->withErrors([
                'agency_codes' => 'Tiada entiti dikemas kini — entiti yang ditanda telah pun dikunci.',
            ]);
        }

        return back()->with('success', sprintf(
            '%d entiti dikemas kini kepada Selesai dan kini dikunci%s.',
            $dikemasKini,
            $dilangkau > 0 ? sprintf(' (%d dilangkau kerana telah dikunci)', $dilangkau) : '',
        ));
    }

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

        if (! $this->kemajuan->pendaftaranSelesai($agencyCode)) {
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
            'Penerimaan & Pendaftaran Data bagi %s ditetapkan semula kepada Belum Mula.',
            $entiti['agency_code'],
        ));
    }
}
