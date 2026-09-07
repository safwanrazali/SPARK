<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\LaporanCatatan;
use App\Services\AuditTrailService;
use App\Support\SeksyenAnalisis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catatan KB/PPA pada Laporan Analisis Inventori Kriptografi.
 *
 * Modul maklum balas + pengakuan sahaja. Tiada penyerahan untuk semakan,
 * kelulusan, penolakan atau pemulangan; tiada notifikasi; dan tiada kesan
 * ke atas status peringkat 3.1 — tandakan "Tindakan Diambil" hanya menyentuh
 * baris catatan itu sendiri.
 *
 * DUA lapisan kebenaran dikuatkuasakan pada setiap tindakan:
 *
 *   1. Akses ENTITI — melalui `can:view,analisis` bagi rekod analisis, dan
 *      melalui LaporanCatatanPolicy (yang memanggil EntityAccessService)
 *      bagi catatan sedia ada. Menukar id catatan atau agency_code dalam
 *      permintaan tidak boleh memintasnya.
 *
 *   2. Kebenaran PERANAN/PEMILIKAN — melalui LaporanCatatanPolicy.
 *      Menyembunyikan butang pada UI TIDAK pernah menjadi kawalan; setiap
 *      laluan menolak permintaan yang tidak berkenaan dengan 403.
 */
class LaporanCatatanController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * KB atau PPA menulis catatan pada satu seksyen Borang Input.
     */
    public function store(Request $request, AnalisisInventori $analisis): RedirectResponse
    {
        // Akses entiti (`can:view,analisis` pada laluan) + peranan pencatat.
        $this->authorize('create', LaporanCatatan::class);

        $disahkan = $request->validate([
            // Hanya sembilan kunci seksyen borang diterima — nama seksyen
            // sewenang-wenangnya ditolak di pelayan, bukan sekadar di borang.
            'section' => ['required', 'string', Rule::in(SeksyenAnalisis::kunci())],
            'content' => ['required', 'string', 'max:'.LaporanCatatan::HAD_KANDUNGAN],
        ]);

        $catatan = LaporanCatatan::create([
            'agency_code' => $analisis->agency_code,
            'agency_name' => $analisis->agency_name,
            'section' => $disahkan['section'],
            'content' => $disahkan['content'],
            'status' => LaporanCatatan::STATUS_TERBUKA,
            'user_id' => $request->user()->id,
        ]);

        $this->rekodkan(
            $catatan,
            'comment_created',
            null,
            LaporanCatatan::STATUS[LaporanCatatan::STATUS_TERBUKA],
            $request,
        );

        return $this->kembali($analisis, 'Catatan telah disimpan.');
    }

    /**
     * Pengarang menyunting catatannya sendiri. PA tidak boleh — maklum balas
     * asal mesti kekal seperti yang ditulis pencatatnya.
     */
    public function update(Request $request, LaporanCatatan $catatan): RedirectResponse
    {
        $this->authorize('update', $catatan);

        $disahkan = $request->validate([
            'content' => ['required', 'string', 'max:'.LaporanCatatan::HAD_KANDUNGAN],
        ]);

        $catatan->update(['content' => $disahkan['content']]);

        $this->rekodkan($catatan, 'comment_updated', null, null, $request);

        return $this->kembali($catatan, 'Catatan telah dikemas kini.');
    }

    /**
     * Pengarang memadam catatannya sendiri — pemadaman lembut, jadi baris
     * kekal untuk jejak audit. Tiada peranan lain boleh memadamnya, termasuk
     * KB/PPA lain, PA dan Pentadbir Sistem.
     */
    public function destroy(Request $request, LaporanCatatan $catatan): RedirectResponse
    {
        $this->authorize('delete', $catatan);

        $this->rekodkan($catatan, 'comment_deleted', $catatan->statusLabel(), null, $request);

        $catatan->delete();

        return $this->kembali($catatan, 'Catatan telah dipadamkan.');
    }

    /**
     * PA menanda bahawa tindakan telah diambil.
     *
     * Teks catatan TIDAK disentuh dan catatan TIDAK dipadam — hanya status,
     * identiti PA dan cap masa direkodkan supaya KB/PPA dapat melihat bahawa
     * tindakan telah diambil. Status peringkat 3.1 tidak disentuh langsung.
     */
    public function tandakanTindakan(Request $request, LaporanCatatan $catatan): RedirectResponse
    {
        $this->authorize('tandakanTindakan', $catatan);

        $lama = $catatan->statusLabel();

        $catatan->update([
            'status' => LaporanCatatan::STATUS_TINDAKAN_DIAMBIL,
            'tindakan_oleh_user_id' => $request->user()->id,
            'tindakan_pada' => now(),
        ]);

        $this->rekodkan($catatan, 'comment_action_taken', $lama, $catatan->statusLabel(), $request);

        return $this->kembali($catatan, 'Catatan ditanda sebagai Tindakan Diambil.');
    }

    /**
     * PA membatalkan tanda tersebut — undo bagi silap tanda sahaja. Ia BUKAN
     * penolakan, bukan pemulangan dan tidak tersedia kepada KB/PPA.
     */
    public function batalkanTindakan(Request $request, LaporanCatatan $catatan): RedirectResponse
    {
        $this->authorize('batalkanTindakan', $catatan);

        $lama = $catatan->statusLabel();

        $catatan->update([
            'status' => LaporanCatatan::STATUS_TERBUKA,
            'tindakan_oleh_user_id' => null,
            'tindakan_pada' => null,
        ]);

        $this->rekodkan($catatan, 'comment_reopened', $lama, $catatan->statusLabel(), $request);

        return $this->kembali($catatan, 'Tanda Tindakan Diambil telah dibatalkan.');
    }

    /**
     * Jejak audit mengikut konvensyen sedia ada: ia merekod PERUBAHAN, bukan
     * KANDUNGAN. Teks catatan sengaja TIDAK dicatat — hanya seksyen, pemilik
     * dan peralihan status.
     */
    private function rekodkan(
        LaporanCatatan $catatan,
        string $action,
        ?string $lama,
        ?string $baharu,
        Request $request,
    ): void {
        $this->audit->rekod(
            [
                'agency_code' => $catatan->agency_code,
                'agency_name' => $catatan->agency_name,
            ],
            $action,
            $lama,
            $baharu,
            $request->user(),
            [
                'catatan_id' => $catatan->id,
                'seksyen' => $catatan->section,
                'seksyen_label' => SeksyenAnalisis::label($catatan->section),
                'pengarang_user_id' => $catatan->user_id,
            ],
        );
    }

    /**
     * Kembali ke laporan entiti berkenaan.
     *
     * Catatan menyimpan agency_code, bukan id rekod analisis, jadi rekod itu
     * dicari semula di sini. Jika ia tiada (laporan boleh dilihat sebelum
     * Borang Input disempurnakan), pengguna dikembalikan ke senarai laporan
     * dan BUKAN kepada route() yang akan gagal dengan model null.
     */
    private function kembali(AnalisisInventori|LaporanCatatan $sumber, string $mesej): RedirectResponse
    {
        $analisis = $sumber instanceof AnalisisInventori
            ? $sumber
            : AnalisisInventori::where('agency_code', $sumber->agency_code)->first();

        $tujuan = $analisis
            ? route('laporan.inventori', $analisis)
            : route('laporan.index');

        return redirect()->to($tujuan)->with('success', $mesej);
    }
}
