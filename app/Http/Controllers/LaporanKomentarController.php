<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\LaporanKomentar;
use App\Services\AuditTrailService;
use App\Support\SeksyenAnalisis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Komentar KB/PPA pada Laporan Analisis Inventori Kriptografi.
 *
 * Modul maklum balas + pengakuan sahaja. Tiada penyerahan untuk semakan,
 * kelulusan, penolakan atau pemulangan; tiada notifikasi; dan tiada kesan
 * ke atas status peringkat 3.1 — tandakan "Tindakan Diambil" hanya menyentuh
 * baris komentar itu sendiri.
 *
 * DUA lapisan kebenaran dikuatkuasakan pada setiap tindakan:
 *
 *   1. Akses ENTITI — melalui `can:view,analisis` bagi rekod analisis, dan
 *      melalui LaporanKomentarPolicy (yang memanggil EntityAccessService)
 *      bagi komentar sedia ada. Menukar id komentar atau agency_code dalam
 *      permintaan tidak boleh memintasnya.
 *
 *   2. Kebenaran PERANAN/PEMILIKAN — melalui LaporanKomentarPolicy.
 *      Menyembunyikan butang pada UI TIDAK pernah menjadi kawalan; setiap
 *      laluan menolak permintaan yang tidak berkenaan dengan 403.
 */
class LaporanKomentarController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * KB atau PPA menulis komentar pada satu seksyen Borang Input.
     */
    public function store(Request $request, AnalisisInventori $analisis): RedirectResponse
    {
        // Akses entiti (`can:view,analisis` pada laluan) + peranan pengomen.
        $this->authorize('create', LaporanKomentar::class);

        $disahkan = $request->validate([
            // Hanya sembilan kunci seksyen borang diterima — nama seksyen
            // sewenang-wenangnya ditolak di pelayan, bukan sekadar di borang.
            'section' => ['required', 'string', Rule::in(SeksyenAnalisis::kunci())],
            'content' => ['required', 'string', 'max:'.LaporanKomentar::HAD_KANDUNGAN],
        ]);

        $komentar = LaporanKomentar::create([
            'agency_code' => $analisis->agency_code,
            'agency_name' => $analisis->agency_name,
            'section' => $disahkan['section'],
            'content' => $disahkan['content'],
            'status' => LaporanKomentar::STATUS_TERBUKA,
            'user_id' => $request->user()->id,
        ]);

        $this->rekodkan(
            $komentar,
            'comment_created',
            null,
            LaporanKomentar::STATUS[LaporanKomentar::STATUS_TERBUKA],
            $request,
        );

        return $this->kembali($analisis, 'Komentar telah disimpan.');
    }

    /**
     * Pengarang menyunting komentarnya sendiri. PA tidak boleh — maklum balas
     * asal mesti kekal seperti yang ditulis pengomen.
     */
    public function update(Request $request, LaporanKomentar $komentar): RedirectResponse
    {
        $this->authorize('update', $komentar);

        $disahkan = $request->validate([
            'content' => ['required', 'string', 'max:'.LaporanKomentar::HAD_KANDUNGAN],
        ]);

        $komentar->update(['content' => $disahkan['content']]);

        $this->rekodkan($komentar, 'comment_updated', null, null, $request);

        return $this->kembali($komentar, 'Komentar telah dikemas kini.');
    }

    /**
     * Pengarang memadam komentarnya sendiri — pemadaman lembut, jadi baris
     * kekal untuk jejak audit. Tiada peranan lain boleh memadamnya, termasuk
     * KB/PPA lain, PA dan Pentadbir Sistem.
     */
    public function destroy(Request $request, LaporanKomentar $komentar): RedirectResponse
    {
        $this->authorize('delete', $komentar);

        $this->rekodkan($komentar, 'comment_deleted', $komentar->statusLabel(), null, $request);

        $komentar->delete();

        return $this->kembali($komentar, 'Komentar telah dipadamkan.');
    }

    /**
     * PA menanda bahawa tindakan telah diambil.
     *
     * Teks komentar TIDAK disentuh dan komentar TIDAK dipadam — hanya status,
     * identiti PA dan cap masa direkodkan supaya KB/PPA dapat melihat bahawa
     * tindakan telah diambil. Status peringkat 3.1 tidak disentuh langsung.
     */
    public function tandakanTindakan(Request $request, LaporanKomentar $komentar): RedirectResponse
    {
        $this->authorize('tandakanTindakan', $komentar);

        $lama = $komentar->statusLabel();

        $komentar->update([
            'status' => LaporanKomentar::STATUS_TINDAKAN_DIAMBIL,
            'tindakan_oleh_user_id' => $request->user()->id,
            'tindakan_pada' => now(),
        ]);

        $this->rekodkan($komentar, 'comment_action_taken', $lama, $komentar->statusLabel(), $request);

        return $this->kembali($komentar, 'Komentar ditanda sebagai Tindakan Diambil.');
    }

    /**
     * PA membatalkan tanda tersebut — undo bagi silap tanda sahaja. Ia BUKAN
     * penolakan, bukan pemulangan dan tidak tersedia kepada KB/PPA.
     */
    public function batalkanTindakan(Request $request, LaporanKomentar $komentar): RedirectResponse
    {
        $this->authorize('batalkanTindakan', $komentar);

        $lama = $komentar->statusLabel();

        $komentar->update([
            'status' => LaporanKomentar::STATUS_TERBUKA,
            'tindakan_oleh_user_id' => null,
            'tindakan_pada' => null,
        ]);

        $this->rekodkan($komentar, 'comment_reopened', $lama, $komentar->statusLabel(), $request);

        return $this->kembali($komentar, 'Tanda Tindakan Diambil telah dibatalkan.');
    }

    /**
     * Jejak audit mengikut konvensyen sedia ada: ia merekod PERUBAHAN, bukan
     * KANDUNGAN. Teks komentar sengaja TIDAK dicatat — hanya seksyen, pemilik
     * dan peralihan status.
     */
    private function rekodkan(
        LaporanKomentar $komentar,
        string $action,
        ?string $lama,
        ?string $baharu,
        Request $request,
    ): void {
        $this->audit->rekod(
            [
                'agency_code' => $komentar->agency_code,
                'agency_name' => $komentar->agency_name,
            ],
            $action,
            $lama,
            $baharu,
            $request->user(),
            [
                'komentar_id' => $komentar->id,
                'seksyen' => $komentar->section,
                'seksyen_label' => SeksyenAnalisis::label($komentar->section),
                'pengarang_user_id' => $komentar->user_id,
            ],
        );
    }

    /**
     * Kembali ke laporan entiti berkenaan.
     *
     * Komentar menyimpan agency_code, bukan id rekod analisis, jadi rekod itu
     * dicari semula di sini. Jika ia tiada (laporan boleh dilihat sebelum
     * Borang Input disempurnakan), pengguna dikembalikan ke senarai laporan
     * dan BUKAN kepada route() yang akan gagal dengan model null.
     */
    private function kembali(AnalisisInventori|LaporanKomentar $sumber, string $mesej): RedirectResponse
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
