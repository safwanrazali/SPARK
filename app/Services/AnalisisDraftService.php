<?php

namespace App\Services;

use App\Models\AnalisDraftHistory;
use App\Models\AnalisisInventori;
use App\Models\User;
use App\Support\BorangAnalisis;
use App\Support\SeksyenAnalisis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FASA 6 — simpan draf dan sambung semula borang analisis.
 *
 * Aliran yang disokong (spesifikasi bahagian 18):
 * Start → Fill → Save Draft → Exit → Return → Resume → Continue → Complete
 *
 * Reka bentuk:
 * - Draf disimpan seksyen demi seksyen dalam analisis_draft_history (Fasa 1).
 * - Setiap simpanan menaikkan nombor versi; rekod lama dikekalkan sebagai
 *   sejarah dan hanya rekod terbaharu setiap seksyen bertanda is_current.
 * - Hanya seksyen yang BERUBAH ditulis, supaya autosave tidak membanjiri
 *   jadual dengan salinan yang sama.
 * - Draf TIDAK disahkan; ia sengaja dibenarkan separa lengkap. Pengesahan
 *   penuh berlaku hanya pada simpanan muktamad.
 *
 * Tiada muat naik dokumen terlibat — semua dapatan dimasukkan secara manual
 * melalui komponen borang berstruktur.
 */
class AnalisisDraftService
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public const ACTION_DRAFT_CREATED = 'draft_created';

    public const ACTION_DRAFT_UPDATED = 'draft_updated';

    /**
     * Sediakan rekod analisis untuk entiti — dicipta sebagai cangkerang
     * kosong pada simpanan draf pertama supaya draf mempunyai induk.
     *
     * @param  array<string, string>  $entiti
     */
    public function mulakan(array $entiti, User $user): AnalisisInventori
    {
        return AnalisisInventori::firstOrCreate(
            ['agency_code' => $entiti['agency_code']],
            [
                'sector_code' => $entiti['sector_code'],
                'sector_name' => $entiti['sector_name'],
                'agency_name' => $entiti['agency_name'],
                'status_laporan' => 'Muktamad',
                'data' => [],
                'selesai' => false,
                'user_id' => $user->id,
            ],
        );
    }

    /**
     * Simpan keadaan borang semasa sebagai draf.
     *
     * @param  array<string, mixed>  $borang  keadaan borang penuh
     * @return Collection<int, AnalisDraftHistory> seksyen yang benar-benar ditulis
     */
    public function simpanDraf(
        AnalisisInventori $analisis,
        array $borang,
        User $user,
        ?string $seksyenSemasa = null,
    ): Collection {
        return DB::transaction(function () use ($analisis, $borang, $user, $seksyenSemasa) {
            $sediaAda = $this->drafSemasa($analisis);
            $baharu = SeksyenAnalisis::pecahkan($borang);
            $asas = SeksyenAnalisis::pecahkan($this->borangDipulihkan($analisis));

            $versi = $this->versiSeterusnya($analisis);
            $ditulis = collect();

            foreach ($baharu as $seksyen => $nilai) {
                // Langkau seksyen yang tidak berubah supaya sejarah kekal bermakna.
                if (($asas[$seksyen] ?? null) == $nilai && $sediaAda->has($seksyen)) {
                    continue;
                }

                AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)
                    ->where('section_name', $seksyen)
                    ->update(['is_current' => false]);

                $ditulis->push(AnalisDraftHistory::create([
                    'analisis_inventori_id' => $analisis->id,
                    'version' => $versi,
                    'section_name' => $seksyen,
                    'section_data' => $nilai,
                    'completed' => SeksyenAnalisis::adaKandungan($seksyen, $nilai),
                    'saved_at' => now(),
                    'saved_by_user_id' => $user->id,
                    'is_current' => true,
                ]));
            }

            if ($ditulis->isNotEmpty()) {
                $this->log($analisis, $sediaAda->isEmpty(), $versi, $user, $seksyenSemasa, $ditulis->count());
            }

            return $ditulis;
        });
    }

    /**
     * Draf semasa bagi setiap seksyen.
     *
     * @return Collection<string, AnalisDraftHistory>
     */
    public function drafSemasa(?AnalisisInventori $analisis): Collection
    {
        if ($analisis === null || ! $analisis->exists) {
            return collect();
        }

        return AnalisDraftHistory::query()
            ->where('analisis_inventori_id', $analisis->id)
            ->where('is_current', true)
            ->with('savedBy')
            ->get()
            ->keyBy('section_name');
    }

    /**
     * Keadaan borang untuk dipaparkan semula: rekod tersimpan ditindih
     * oleh draf semasa. Inilah operasi "Resume".
     *
     * @return array<string, mixed>
     */
    public function borangDipulihkan(?AnalisisInventori $analisis): array
    {
        return $this->gabungkanDraf($analisis, $this->drafSemasa($analisis));
    }

    /**
     * Versi borangDipulihkan() bagi pemanggil yang telah memuatkan draf
     * semasa — mengelakkan query kedua bagi data yang sama.
     *
     * @param  Collection<string, AnalisDraftHistory>  $draf
     * @return array<string, mixed>
     */
    private function gabungkanDraf(?AnalisisInventori $analisis, Collection $draf): array
    {
        $borang = BorangAnalisis::daripadaModel($analisis);

        foreach ($draf as $seksyen => $rekod) {
            foreach ((array) $rekod->section_data as $medan => $nilai) {
                $borang[$medan] = $nilai;
            }
        }

        return $borang;
    }

    /**
     * Adakah terdapat draf yang belum dimuktamadkan?
     */
    public function adaDrafBelumSelesai(?AnalisisInventori $analisis): bool
    {
        return $this->drafSemasa($analisis)->isNotEmpty();
    }

    /**
     * Ringkasan untuk paparan: versi draf, masa simpanan terakhir, pegawai
     * yang menyimpan dan keadaan setiap seksyen.
     *
     * Keadaan "diisi" setiap seksyen dikira daripada KEADAAN BORANG SEBENAR
     * (rekod tersimpan ditindih draf semasa) — bukan daripada baris draf
     * sahaja.
     *
     * Bezanya penting: "Simpan Dapatan" memanggil tutupDraf(), yang
     * menurunkan is_current bagi semua baris draf. Jika kiraan bergantung
     * pada baris draf, borang yang telah lengkap dan dimuktamadkan akan
     * dipaparkan sebagai "0 / 9 seksyen diisi" — begitu juga borang yang
     * diisi terus tanpa sekali pun menekan "Simpan Draf". Kedua-duanya
     * salah: maklumatnya ada, cuma bukan di dalam penimbal draf.
     *
     * Metadata draf (versi, masa, pegawai) kekal daripada baris draf,
     * kerana itu memang milik draf.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(?AnalisisInventori $analisis): array
    {
        $draf = $this->drafSemasa($analisis);
        $terakhir = $draf->sortByDesc('saved_at')->sortByDesc('id')->first();

        $borang = SeksyenAnalisis::pecahkan($this->gabungkanDraf($analisis, $draf));

        $seksyen = [];

        foreach (SeksyenAnalisis::SEKSYEN as $kunci => $takrif) {
            $rekod = $draf->get($kunci);

            $seksyen[$kunci] = [
                'label' => $takrif['label'],
                'ada_draf' => $rekod !== null,
                'selesai' => SeksyenAnalisis::adaKandungan($kunci, $borang[$kunci] ?? []),
                'versi' => $rekod?->version,
                'disimpan_pada' => $rekod?->saved_at,
            ];
        }

        $bilanganSelesai = collect($seksyen)->where('selesai', true)->count();

        return [
            'ada_draf' => $draf->isNotEmpty(),
            // Dapatan yang telah disimpan ke dalam rekod sebenar, tanpa draf
            // terbuka — keadaan biasa selepas "Simpan Dapatan".
            'ada_rekod' => ($analisis?->exists ?? false) && $bilanganSelesai > 0,
            'dikemas_kini_pada' => $analisis?->updated_at,
            'versi' => $draf->max('version'),
            'disimpan_pada' => $terakhir?->saved_at,
            'disimpan_oleh' => $terakhir?->savedBy?->name,
            'jumlah_seksyen' => count(SeksyenAnalisis::SEKSYEN),
            'seksyen_selesai' => $bilanganSelesai,
            'seksyen' => $seksyen,
        ];
    }

    /**
     * Sejarah versi draf bagi satu rekod analisis.
     *
     * @return Collection<int, AnalisDraftHistory>
     */
    public function sejarah(AnalisisInventori $analisis, int $had = 50): Collection
    {
        return AnalisDraftHistory::query()
            ->where('analisis_inventori_id', $analisis->id)
            ->with('savedBy')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->limit($had)
            ->get();
    }

    /**
     * Dipanggil selepas simpanan muktamad — draf tidak lagi menjadi sumber
     * pemulihan kerana rekod analisis sebenar telah dikemas kini.
     * Rekod draf dikekalkan sebagai sejarah versi.
     */
    public function tutupDraf(AnalisisInventori $analisis): void
    {
        AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function versiSeterusnya(AnalisisInventori $analisis): int
    {
        return (int) AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)->max('version') + 1;
    }

    private function log(
        AnalisisInventori $analisis,
        bool $pertama,
        int $versi,
        User $user,
        ?string $seksyenSemasa,
        int $bilanganSeksyen,
    ): void {
        // Hanya metadata versi dicatat — kandungan dapatan analisis tidak
        // pernah dimasukkan ke dalam jejak audit.
        $this->audit->rekod(
            [
                'agency_code' => $analisis->agency_code,
                'agency_name' => $analisis->agency_name,
            ],
            $pertama ? self::ACTION_DRAFT_CREATED : self::ACTION_DRAFT_UPDATED,
            $pertama ? null : (string) ($versi - 1),
            (string) $versi,
            $user,
            [
                'analisis_inventori_id' => $analisis->id,
                'version' => $versi,
                'seksyen_semasa' => $seksyenSemasa,
                'seksyen_disimpan' => $bilanganSeksyen,
            ],
        );
    }
}
