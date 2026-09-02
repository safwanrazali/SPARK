<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komentar KB dan PPA pada Laporan Analisis Inventori Kriptografi.
 *
 * Komentar hanya dilihat oleh PA (pembuat laporan), tidak disertakan dalam
 * laporan PDF yang dijana. Ia memungkinkan KB dan PPA untuk memberikan maklum
 * balas pada seksyen-seksyen spesifik laporan tanpa memerlukan kitaran
 * kelulusan formal.
 */
class LaporanKomentar extends Model
{
    use HasFactory;

    protected $table = 'laporan_komentar';

    protected $fillable = [
        'agency_code',
        'agency_name',
        'section',
        'content',
        'user_id',
    ];

    /**
     * Pentadbir yang membuat komentar (KB atau PPA).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Komentar untuk satu agensi, disusun mengikut seksyen dan waktu.
     */
    public function scopeForAgency($query, string $agencyCode)
    {
        return $query->where('agency_code', $agencyCode)
            ->orderBy('section')
            ->orderByDesc('created_at');
    }

    /**
     * Komentar untuk satu seksyen.
     */
    public function scopeForSection($query, string $section)
    {
        return $query->where('section', $section)
            ->orderByDesc('created_at');
    }

    /**
     * Hanya komentar daripada pengguna tertentu.
     */
    public function scopeByUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Seksyen-seksyen laporan yang boleh dikomenkari.
     * Selaras dengan struktur pandangan laporan.
     */
    public static function seksyenLaporan(): array
    {
        return [
            'pengenalan' => 'Pengenalan',
            'kerangka_kerja' => 'Kerangka Kerja Kriptografi',
            'keadaan_semasa' => 'Keadaan Semasa Kriptografi',
            'algoritma_kenal_pasti' => 'Algoritma Dikenal Pasti',
            'kesimpulan' => 'Kesimpulan & Cadangan',
            'lampiran' => 'Lampiran',
            'profil_sistem_aset' => '1. Profil Sistem dan Aset',
            'algoritma_kriptografi' => '2. Algoritma Kriptografi',
            'protokol_kriptografi' => '3. Protokol Kriptografi',
            'pustaka_modul_kriptografi' => '4. Pustaka dan Modul Kriptografi',
            'maklumat_vendor' => '5. Maklumat Vendor',
        ];
    }
}
