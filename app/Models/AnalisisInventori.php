<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalisisInventori extends Model
{
    use HasFactory;

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
        $dipilih = array_keys($this->data['algoritma'] ?? []);
        $nama = array_map(fn ($k) => explode('|', $k)[1] ?? $k, $dipilih);

        return array_values(array_intersect($nama, config('kriptografi.tidak_disyorkan')));
    }

    /** Algoritma dipilih yang berisiko terhadap ancaman kuantum. */
    public function algoritmaKuantum(): array
    {
        $dipilih = array_keys($this->data['algoritma'] ?? []);
        $nama = array_map(fn ($k) => explode('|', $k)[1] ?? $k, $dipilih);

        return array_values(array_intersect($nama, config('kriptografi.risiko_kuantum')));
    }
}
