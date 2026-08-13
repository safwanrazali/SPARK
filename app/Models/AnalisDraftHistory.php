<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk melacak versi draf laporan analisis.
 *
 * Membolehkan:
 * - Simpan draf seksyen demi seksyen
 * - Resume dari tempat terakhir
 * - Jejak versi
 * - Pemulihan jika data hilang
 */
class AnalisDraftHistory extends Model
{
    use HasFactory;

    protected $table = 'analisis_draft_history';

    protected $fillable = [
        'analisis_inventori_id',
        'version',
        'section_name',
        'section_data',
        'completed',
        'saved_at',
        'saved_by_user_id',
        'is_current',
    ];

    protected $casts = [
        'section_data' => 'array',
        'completed' => 'boolean',
        'is_current' => 'boolean',
        'saved_at' => 'datetime',
    ];

    /**
     * Analisis inventori yang berkaitan.
     */
    public function analisisInventori(): BelongsTo
    {
        return $this->belongsTo(AnalisisInventori::class);
    }

    /**
     * Pegawai yang menyimpan draf.
     */
    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by_user_id');
    }

    /**
     * Scope untuk menyeleksi versi semasa.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope untuk menyeleksi analisis tertentu.
     */
    public function scopeForAnalysis($query, $analisisId)
    {
        return $query->where('analisis_inventori_id', $analisisId);
    }

    /**
     * Scope untuk menseleksi seksyen tertentu.
     */
    public function scopeForSection($query, $sectionName)
    {
        return $query->where('section_name', $sectionName);
    }

    /**
     * Dapatkan versi sebelumnya.
     */
    public function getPreviousVersion()
    {
        return self::where('analisis_inventori_id', $this->analisis_inventori_id)
            ->where('version', '<', $this->version)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Adakah analisis lengkap (semua seksyen selesai)?
     */
    public function isComplete()
    {
        return $this->completed;
    }
}
