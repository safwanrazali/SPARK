<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Services\KemajuanAnalisisService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tindakan pada halaman "Kemajuan Analisis Entiti".
 *
 * Tiga tindakan sahaja, dan kesemuanya bekerja pada KUNCI peringkat
 * ('1.1', '2', '3.1') dan bukan nombor rata:
 *
 *   simpan()   rekod data tangkapan peringkat        pemilik peringkat
 *   selesai()  tandakan peringkat Selesai            pemilik peringkat
 *   rujukan()  masukkan No. Rujukan Borang           PPR
 *
 * Siapa "pemilik peringkat" ditentukan oleh AliranKerja, bukan oleh senarai
 * berasingan di sini — jadi menukar tanggungjawab satu peringkat ialah satu
 * perubahan pada takrifan, bukan pada setiap tempat yang menyemaknya.
 *
 * `rujukan()` diasingkan kerana pemiliknya memang berbeza: PPR memasukkan
 * No. Rujukan Borang bagi peringkat 1.1–1.3 walaupun peringkat itu miliknya
 * KB, PPA dan PA.
 *
 * FASA SEMASA: peringkat 3.2, 4 dan 5 belum dibina. Permintaan terhadapnya
 * ditolak di sini dan sekali lagi di dalam servis, jadi tiada borang yang
 * dihantar terus boleh memintasnya.
 *
 * Kawalan akses entiti dikuatkuasakan oleh middleware `entity.access` pada
 * setiap route, jadi Pegawai Analisis tidak boleh menyentuh entiti pegawai
 * lain walaupun melalui permintaan langsung.
 */
class KemajuanAnalisisController extends Controller
{
    public function __construct(
        private readonly KemajuanAnalisisService $kemajuan,
    ) {}

    /**
     * Rekod data tangkapan satu peringkat tanpa menandakannya Selesai.
     *
     * Memisahkan "simpan" daripada "selesai" bermakna tarikh dan status
     * borang boleh direkodkan semasa kerja masih berjalan — dan peringkat
     * tidak tertutup sebelum masanya semata-mata kerana satu medan diisi.
     */
    public function simpan(Request $request, string $agencyCode, string $stage)
    {
        $entiti = $this->peringkatAtauGagal($agencyCode, $stage);

        $this->benarkanPeringkat($stage);

        $data = $request->validate(
            $this->peraturanMedan($stage),
            [],
            $this->namaMedan($stage),
        );

        try {
            $this->kemajuan->simpanData($agencyCode, $stage, $data, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withInput()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Maklumat peringkat %s bagi %s telah disimpan.',
            AliranKerja::labelPenuh($stage),
            $entiti['agency_code'],
        ));
    }

    /**
     * Tandakan satu peringkat Selesai.
     *
     * Data tangkapan yang dihantar bersama borang disimpan dahulu, supaya
     * pegawai tidak perlu menekan "Simpan" kemudian "Selesai" secara
     * berasingan untuk maklumat yang sama.
     */
    public function selesai(Request $request, string $agencyCode, string $stage)
    {
        $entiti = $this->peringkatAtauGagal($agencyCode, $stage);

        $this->benarkanPeringkat($stage);

        $data = $request->validate(
            $this->peraturanMedan($stage),
            [],
            $this->namaMedan($stage),
        );

        try {
            if ($data !== []) {
                $this->kemajuan->simpanData($agencyCode, $stage, $data, $request->user());
            }

            $this->kemajuan->tandakanSelesai($agencyCode, $stage, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withInput()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Peringkat %s bagi %s ditandakan Selesai.',
            AliranKerja::labelPenuh($stage),
            $entiti['agency_code'],
        ));
    }

    /**
     * Masukkan No. Rujukan peringkat.
     *
     * Pemiliknya berbeza mengikut peringkat, jadi gate diambil daripada
     * takrifan aliran kerja dan bukan ditulis tetap di sini:
     *
     *   1.1–1.3  No. Rujukan Borang    Pegawai Penyelaras Rekod
     *   3.1      No. Rujukan Laporan   pegawai peringkat itu (PA)
     *
     * Tiada semakan status peringkat di sini dengan sengaja: nombor rujukan
     * boleh direkodkan sepanjang peringkat itu berjalan, bukan hanya selepas
     * ia ditandakan Selesai.
     */
    public function rujukan(Request $request, string $agencyCode, string $stage)
    {
        $entiti = $this->peringkatAtauGagal($agencyCode, $stage);

        $gate = AliranKerja::gateRujukan($stage);

        abort_if($gate === null, 404);

        Gate::authorize($gate);

        $data = $request->validate([
            'no_rujukan' => ['nullable', 'string', 'max:255'],
        ], [], [
            'no_rujukan' => AliranKerja::labelRujukan($stage),
        ]);

        try {
            $this->kemajuan->simpanRujukan($agencyCode, $stage, $data['no_rujukan'] ?? null, $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withInput()->withErrors(['no_rujukan' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            '%s bagi %s telah dikemas kini.',
            AliranKerja::labelRujukan($stage),
            $entiti['agency_code'],
        ));
    }

    /**
     * Kebenaran melaksanakan peringkat ini, mengikut gate yang dinamakan
     * oleh takrifan aliran kerja.
     */
    private function benarkanPeringkat(string $stage): void
    {
        $gate = AliranKerja::gate($stage);

        // Peringkat fasa akan datang tiada gate kerana ia tiada tindakan.
        abort_if($gate === null, 404);

        Gate::authorize($gate);
    }

    /**
     * Peraturan pengesahan bagi medan peringkat ini.
     *
     * Hanya medan yang ditakrifkan bagi peringkat berkenaan diterima; borang
     * tidak boleh menulis medan peringkat lain walaupun ia dihantar.
     *
     * `status_borang` tiada senarai nilai: perbendaharaannya belum
     * ditetapkan, jadi tiada nilai direka di sini.
     *
     * @return array<string, array<int, mixed>>
     */
    private function peraturanMedan(string $stage): array
    {
        $peraturan = [];

        foreach (array_keys(AliranKerja::medan($stage)) as $medan) {
            $peraturan[$medan] = in_array($medan, AliranKerja::MEDAN_TARIKH, true)
                ? ['nullable', 'date']
                : ['nullable', 'string', 'max:255'];
        }

        // Tarikh Tamat tidak boleh mendahului Tarikh Mula — satu-satunya
        // peraturan silang medan, dan ia datang daripada makna medan itu
        // sendiri, bukan daripada proses perniagaan yang belum ditetapkan.
        if (isset($peraturan[AliranKerja::MEDAN_TARIKH_TAMAT], $peraturan[AliranKerja::MEDAN_TARIKH_MULA])) {
            $peraturan[AliranKerja::MEDAN_TARIKH_TAMAT][] = 'after_or_equal:'.AliranKerja::MEDAN_TARIKH_MULA;
        }

        return $peraturan;
    }

    /**
     * Label medan untuk mesej ralat — diambil daripada takrifan aliran kerja
     * supaya borang dan mesej ralat menggunakan perkataan yang sama.
     *
     * @return array<string, string>
     */
    private function namaMedan(string $stage): array
    {
        return AliranKerja::medan($stage);
    }

    /**
     * Entiti mesti wujud dan peringkat mesti sah sebelum apa-apa dilakukan.
     *
     * @return array<string, string>
     */
    private function peringkatAtauGagal(string $agencyCode, string $stage): array
    {
        abort_unless(AliranKerja::wujud($stage), 404, 'Peringkat aliran kerja tidak dikenali.');

        // Peringkat fasa akan datang tidak menerima sebarang permintaan.
        abort_if(AliranKerja::adalahAkanDatang($stage), 404, sprintf(
            'Peringkat %s belum dibina.',
            AliranKerja::labelPenuh($stage),
        ));

        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        return $entiti;
    }
}
