<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use App\Support\BorangAnalisis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalisisInventori extends Model
{
    use FiltersByEntityAccess, HasFactory;

    protected $table = 'analisis_inventori';

    protected $fillable = [
        'sector_code', 'sector_name', 'agency_code', 'agency_name',
        'tarikh_laporan', 'kod_rujukan', 'status_laporan',
        'data', 'selesai', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'selesai' => 'boolean',
            'tarikh_laporan' => 'date',
        ];
    }

    /**
     * PHASE 1 — Relationships untuk workflow dan draft system.
     */

    /**
     * Pegawai yang membuat analisis ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sejarah draf laporan analisis ini.
     */
    public function draftHistories(): HasMany
    {
        return $this->hasMany(AnalisDraftHistory::class);
    }

    /**
     * Dapatkan versi draf semasa.
     */
    public function getCurrentDraft()
    {
        return $this->draftHistories()
            ->where('is_current', true)
            ->latest()
            ->first();
    }

    /** Algoritma dipilih yang tidak lagi disyorkan (untuk kesimpulan automatik). */
    public function algoritmaLapuk(): array
    {
        return $this->padanan(config('kriptografi.tidak_disyorkan'));
    }

    /** Algoritma dipilih yang berisiko terhadap ancaman kuantum. */
    public function algoritmaKuantum(): array
    {
        return $this->padanan(config('kriptografi.risiko_kuantum'));
    }

    /**
     * Padankan senarai rujukan dengan algoritma yang direkodkan entiti.
     *
     * Dua sumber diimbas kerana katalog checkbox kini mengandungi algoritma
     * AKSA MySEAL (Approved) SAHAJA. Algoritma lapuk seperti 3DES dan MD5,
     * serta algoritma klasik seperti RSA dan DSA, tiada dalam katalog itu dan
     * direkodkan oleh pegawai melalui medan "Lain-lain". Mengimbas kunci
     * checkbox sahaja akan menyebabkan penandaan "tidak lagi disyorkan"
     * berhenti berfungsi sepenuhnya.
     *
     * Padanan dibuat tanpa mengira huruf besar/kecil kerana "Lain-lain" ialah
     * teks bebas; nilai yang dikembalikan ialah ejaan rasmi daripada config,
     * bukan apa yang ditaip.
     *
     * @param  list<string>  $rujukan
     * @return list<string>
     */
    private function padanan(array $rujukan): array
    {
        $direkod = array_map(
            fn ($k) => explode('|', $k)[1] ?? $k,
            array_keys($this->data['algoritma'] ?? []),
        );

        $direkod = array_merge($direkod, array_column(
            BorangAnalisis::algoritmaLain($this->data['algoritma_lain'] ?? null),
            'nama',
        ));

        $indeks = [];

        foreach ($direkod as $satu) {
            $indeks[mb_strtolower(trim((string) $satu))] = true;
        }

        return array_values(array_filter(
            $rujukan,
            fn ($r) => isset($indeks[mb_strtolower($r)]),
        ));
    }
}
