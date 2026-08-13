<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisisInventori extends Model
{
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
