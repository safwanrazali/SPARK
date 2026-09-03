<?php

namespace App\Models;

use App\Support\SeksyenAnalisis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Komentar Ketua Bahagian dan Pegawai Penyelaras Analisis pada Laporan
 * Analisis Inventori Kriptografi.
 *
 * Ia satu mekanisme MAKLUM BALAS + PENGAKUAN, bukan kitaran kelulusan:
 *
 *     KB / PPA menulis komentar pada satu seksyen Borang Input
 *       -> PA melihatnya dan mengambil tindakan yang perlu
 *       -> PA menanda "Tindakan Diambil"
 *       -> KB / PPA melihat bahawa tindakan telah diambil
 *
 * Tiada penyerahan untuk semakan, kelulusan, penolakan atau pemulangan.
 * Komentar TIDAK sekali-kali menyekat atau mengubah status peringkat 3.1,
 * dan TIDAK disertakan dalam PDF laporan rasmi.
 *
 * PENAPISAN: KB dan PPA melihat KESEMUA komentar KB dan PPA — pemilikan
 * hanya menentukan siapa boleh MENYUNTING dan MEMADAM, bukan siapa boleh
 * MELIHAT. Lihat App\Policies\LaporanKomentarPolicy.
 */
class LaporanKomentar extends Model
{
    use HasFactory, SoftDeletes;

    /** Komentar masih menunggu tindakan Pegawai Analisis. */
    public const STATUS_TERBUKA = 'terbuka';

    /** Pegawai Analisis telah mengambil tindakan atas komentar ini. */
    public const STATUS_TINDAKAN_DIAMBIL = 'tindakan_diambil';

    /**
     * Status komentar — SENGAJA berbeza daripada "Belum Selesai / Selesai"
     * peringkat 3.1 supaya kedua-duanya tidak dikelirukan. Keduanya bebas.
     */
    public const STATUS = [
        self::STATUS_TERBUKA => 'Terbuka',
        self::STATUS_TINDAKAN_DIAMBIL => 'Tindakan Diambil',
    ];

    /** Had panjang kandungan komentar — dikuatkuasakan borang dan pelayan. */
    public const HAD_KANDUNGAN = 2000;

    protected $table = 'laporan_komentar';

    protected $fillable = [
        'agency_code',
        'agency_name',
        'section',
        'content',
        'status',
        'user_id',
        'tindakan_oleh_user_id',
        'tindakan_pada',
    ];

    protected $attributes = [
        'status' => self::STATUS_TERBUKA,
    ];

    protected function casts(): array
    {
        return [
            'tindakan_pada' => 'datetime',
        ];
    }

    /**
     * Pengarang komentar (KB atau PPA) — pemilik yang boleh menyunting dan
     * memadamnya.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pegawai Analisis yang menanda "Tindakan Diambil".
     */
    public function tindakanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tindakan_oleh_user_id');
    }

    /**
     * Komentar untuk satu entiti, disusun mengikut seksyen dan waktu.
     */
    public function scopeForAgency(Builder $query, string $agencyCode): Builder
    {
        return $query->where('agency_code', $agencyCode)
            ->orderBy('section')
            ->orderByDesc('created_at');
    }

    public function scopeForSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section)->orderByDesc('created_at');
    }

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERBUKA);
    }

    public function scopeTindakanDiambil(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TINDAKAN_DIAMBIL);
    }

    public function sudahDitindak(): bool
    {
        return $this->status === self::STATUS_TINDAKAN_DIAMBIL;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /**
     * Seksyen yang boleh dikomentari: SEMBILAN seksyen Borang Input, tidak
     * lebih dan tidak kurang. Tujuannya memberitahu PA bahagian borang mana
     * yang perlu diberi perhatian, jadi senarai ini mesti kekal terikat pada
     * App\Support\SeksyenAnalisis dan bukan disalin di sini.
     *
     * @return array<string, string>
     */
    public static function seksyenLaporan(): array
    {
        return array_map(
            fn (array $takrif): string => $takrif['label'],
            SeksyenAnalisis::SEKSYEN,
        );
    }
}
