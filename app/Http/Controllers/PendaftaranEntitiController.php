<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Peringkat 1.1 aliran kerja — "Penerimaan Data".
 *
 * Ketua Bahagian atau Pegawai Penyelaras Analisis menanda entiti yang
 * datanya telah diterima, kemudian menekan "Kemas Kini". Entiti yang
 * dikemas kini dikunci dan mula kelihatan kepada PPA untuk ditugaskan.
 *
 * Peringkat 1.2 (Pendaftaran Data) dan 1.3 (Semakan Awal Data) dilakukan
 * seterusnya pada halaman Kemajuan Analisis Entiti, bersama data tangkapan
 * masing-masing — bukan di sini, kerana ia kerja setiap entiti dan bukan
 * penandaan pukal.
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
     * Tandakan peringkat 1.1 "Penerimaan Data" Selesai bagi entiti dipilih.
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
            if ($this->kemajuan->penerimaanSelesai($agencyCode)) {
                $dilangkau++;

                continue;
            }

            try {
                $this->kemajuan->lengkapkanPenerimaan($entiti, $request->user());
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
