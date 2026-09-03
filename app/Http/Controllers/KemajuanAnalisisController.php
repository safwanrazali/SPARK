<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidAssignmentException;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\User;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\AliranKerja;
use App\Support\PeraturanPeringkat;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
        private readonly EntityAssignmentService $assignments,
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
            PeraturanPeringkat::medan($stage),
            [],
            PeraturanPeringkat::nama($stage),
        );

        $this->pastikanDalamAliran($entiti, $stage);

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

        // Kebenaran DAHULU: peranan yang tidak memiliki peringkat ini mesti
        // menerima 403 yang sama seperti peringkat lain, bukan 404 yang
        // menerangkan bentuk tindakan peringkat itu.
        $this->benarkanPeringkat($stage);

        // Peringkat berderivasi tiada tindakan "Selesai": statusnya ialah
        // jawapan kepada kelengkapan datanya, jadi ia disiapkan dengan
        // merekod medannya melalui simpan(). Menawarkan laluan kedua ke
        // status yang sama hanya mencipta cara memintas syaratnya.
        abort_if(AliranKerja::statusDiterbitkan($stage), 404, sprintf(
            'Peringkat %s disiapkan dengan merekod datanya, bukan dengan menandakannya Selesai.',
            AliranKerja::labelPenuh($stage),
        ));

        $data = $request->validate(
            PeraturanPeringkat::medan($stage),
            [],
            PeraturanPeringkat::nama($stage),
        );

        $this->pastikanDalamAliran($entiti, $stage);

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
     * Setiap No. Rujukan — keempat-empatnya — dimasukkan oleh Pegawai
     * Penyelaras Rekod, tanpa mengira siapa memiliki peringkatnya. Gate
     * diambil daripada takrifan aliran kerja dan bukan ditulis tetap di sini.
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

        // No. Rujukan direkodkan PADA baris peringkat, jadi entiti mesti
        // sudah berada dalam aliran kerja. Memasukkannya ke dalam aliran
        // ialah tindakan peringkat 1.1 — bukan tindakan PPR.
        if ($this->kemajuan->peringkat($agencyCode)->isEmpty()) {
            return back()->withErrors([
                'no_rujukan' => sprintf(
                    '%s belum memasuki aliran kerja. Peringkat %s perlu direkodkan terlebih dahulu.',
                    $entiti['agency_code'],
                    AliranKerja::labelPenuh(AliranKerja::PERTAMA),
                ),
            ]);
        }

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
     * Tugaskan Pegawai Analisis kepada entiti — kerja peringkat 1.2.
     *
     * PPA mendaftarkan data DAN menetapkan pegawai yang akan menjalankan
     * peringkat seterusnya; kerana itu kawalan ini berada dalam blok
     * peringkat 1.2 dan bukan pada skrinnya sendiri.
     *
     * Syaratnya sama seperti peringkat 1.2 itu sendiri: entiti mesti telah
     * melepasi peringkat 1.1. Tanpa semakan ini, permintaan langsung boleh
     * menugaskan entiti yang belum pun memasuki aliran kerja.
     */
    public function tugaskan(Request $request, string $agencyCode)
    {
        $entiti = $this->entitiAtauGagal($agencyCode);

        // Ambang yang sama seperti MEMBUKA peringkat 1.2: syarat lanjut
        // peringkat 1.1 mesti dipenuhi. Menggunakan ambang "telah bermula"
        // yang lebih longgar akan membenarkan penugasan pada entiti yang
        // peringkat 1.2-nya sendiri masih tertutup.
        $ralat = $this->kemajuan->ralatPeringkat($agencyCode, AliranKerja::PENDAFTARAN_DATA);

        if ($ralat !== null) {
            return back()->withErrors(['assigned_to_user_id' => $ralat]);
        }

        $data = $request->validate([
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'assigned_to_user_id' => 'Pegawai Analisis',
        ]);

        $pegawai = User::findOrFail($data['assigned_to_user_id']);

        try {
            $this->assignments->assign($entiti, $pegawai, $request->user(), $data['notes'] ?? null);
        } catch (InvalidAssignmentException $e) {
            return back()->withInput()->withErrors(['assigned_to_user_id' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            '%s telah ditugaskan kepada %s.',
            $entiti['agency_code'],
            $pegawai->name,
        ));
    }

    /**
     * Peringkat PERTAMA ialah pintu masuk aliran kerja: melaksanakannya pada
     * entiti yang belum mempunyai baris peringkat MEMASUKKAN entiti itu ke
     * dalam aliran.
     *
     * Inilah yang menggantikan penandaan pukal skrin Penetapan Entiti: KB
     * atau PPA membuka halaman Kemajuan entiti, merekod Tarikh Terima dan
     * Status Borang Penerimaan Data, dan entiti itu masuk ke dalam aliran.
     *
     * Peringkat lain TIDAK memasukkan entiti: ia mesti sudah berada di dalam
     * aliran, dan servis menolaknya jika tidak.
     *
     * @param  array<string, string>  $entiti
     */
    private function pastikanDalamAliran(array $entiti, string $stage): void
    {
        if ($stage === AliranKerja::PERTAMA) {
            // Selamat dipanggil berulang kali: baris sedia ada tidak disentuh.
            $this->kemajuan->sediakan($entiti);
        }
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
     * Entiti mesti wujud dalam senarai induk sektor.
     *
     * @return array<string, string>
     */
    private function entitiAtauGagal(string $agencyCode): array
    {
        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        return $entiti;
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
