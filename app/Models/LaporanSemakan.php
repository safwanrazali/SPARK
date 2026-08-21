<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kedudukan semasa satu laporan dalam kitaran semakan dan kelulusan.
 *
 * Jejak setiap tindakan kekal dalam approval_logs (Fasa 1, sedia ada);
 * model ini hanya memegang keadaan semasa supaya senarai, butang dan papan
 * pemuka tidak perlu mengira semula daripada jejak itu.
 */
class LaporanSemakan extends Model
{
    use FiltersByEntityAccess, HasFactory;

    protected $table = 'laporan_semakan';

    /**
     * PA sedang menyiapkan laporan; belum diserahkan kepada sesiapa.
     */
    public const DRAF = 'Draf';

    /**
     * PA telah menekan "Hantar" — menunggu semakan PPA.
     */
    public const MENUNGGU_PPA = 'Dihantar kepada PPA';

    /**
     * PPA telah menekan "Hantar" — menunggu kelulusan KB.
     */
    public const MENUNGGU_KB = 'Dihantar kepada KB';

    /**
     * PPA atau KB telah menekan "Kembalikan"; laporan kembali kepada PA.
     */
    public const DIKEMBALIKAN = 'Dikembalikan';

    /**
     * KB telah menekan "Sahkan". Hanya laporan berstatus ini boleh dimuat turun.
     */
    public const SAH = 'Sah';

    /**
     * Peralihan yang dibenarkan bagi setiap keadaan.
     *
     * Menyimpan peraturan di sini bermakna butang UI dan pengesahan pelayan
     * membaca senarai yang sama — keadaan tidak boleh dipintas dengan
     * menghantar borang terus.
     *
     * @var array<string, array<int, string>>
     */
    public const ALIRAN = [
        self::DRAF => [self::MENUNGGU_PPA],
        self::MENUNGGU_PPA => [self::MENUNGGU_KB, self::DIKEMBALIKAN],
        self::MENUNGGU_KB => [self::SAH, self::DIKEMBALIKAN],
        self::DIKEMBALIKAN => [self::MENUNGGU_PPA],
        self::SAH => [],
    ];

    protected $fillable = [
        'agency_code',
        'agency_name',
        'sector_code',
        'sector_name',
        'report_type',
        'status',
        'catatan',
        'dihantar_oleh_user_id',
        'dihantar_pada',
        'disemak_oleh_user_id',
        'disemak_pada',
        'disahkan_oleh_user_id',
        'disahkan_pada',
    ];

    protected $casts = [
        'dihantar_pada' => 'datetime',
        'disemak_pada' => 'datetime',
        'disahkan_pada' => 'datetime',
    ];

    public function dihantarOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihantar_oleh_user_id');
    }

    public function disemakOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disemak_oleh_user_id');
    }

    public function disahkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh_user_id');
    }

    /**
     * Perbendaharaan yang dilihat pengguna pada "Status Laporan".
     *
     * Keadaan dalaman di atas menjejaki DI TANGAN SIAPA laporan berada;
     * peringkat aliran kerja hanya mengambil berat sama ada laporan masih
     * disiapkan PA, sedang dalam kitaran semakan, atau telah disahkan.
     * Dipetakan di sini dan bukan disimpan sebagai lajur baharu, supaya
     * tiada dua sumber kebenaran bagi status yang sama.
     *
     * "Dikembalikan" kekal Dalam Semakan: laporan masih berada dalam
     * kitaran PA → PPA → KB, cuma giliran membetulkannya kembali kepada PA.
     *
     * @var array<string, string>
     */
    public const PAPARAN = [
        self::DRAF => 'Belum Lengkap',
        self::MENUNGGU_PPA => 'Dalam Semakan',
        self::MENUNGGU_KB => 'Dalam Semakan',
        self::DIKEMBALIKAN => 'Dalam Semakan',
        self::SAH => 'Disahkan',
    ];

    public const PAPARAN_BELUM_LENGKAP = 'Belum Lengkap';

    public const PAPARAN_DALAM_SEMAKAN = 'Dalam Semakan';

    public const PAPARAN_DISAHKAN = 'Disahkan';

    /**
     * Bolehkah laporan ini berpindah kepada keadaan $status?
     */
    public function bolehBeralihKe(string $status): bool
    {
        return in_array($status, self::ALIRAN[$this->status] ?? [], true);
    }

    /**
     * Laporan yang telah disahkan KB — satu-satunya yang boleh dimuat turun.
     */
    public function isSah(): bool
    {
        return $this->status === self::SAH;
    }

    /**
     * Menunggu tindakan PA (sama ada belum dihantar atau telah dikembalikan).
     */
    public function bolehDisuntingPA(): bool
    {
        return in_array($this->status, [self::DRAF, self::DIKEMBALIKAN], true);
    }

    /**
     * Adakah laporan berada dalam kitaran semakan PPA/KB sekarang?
     *
     * Inilah syarat kunci sunting PA: laporan yang berada di tangan penyemak
     * tidak boleh diubah, walaupun melalui borang yang dihantar terus.
     */
    public function sedangDisemak(): bool
    {
        return in_array($this->status, [self::MENUNGGU_PPA, self::MENUNGGU_KB], true);
    }

    /**
     * Label "Status Laporan" bagi paparan — lihat self::PAPARAN.
     */
    public function statusPaparan(): string
    {
        return self::PAPARAN[$this->status] ?? $this->status;
    }

    /**
     * Label bagi entiti yang belum menghantar laporan langsung.
     */
    public static function paparanUntuk(?self $laporan): string
    {
        return $laporan?->statusPaparan() ?? self::PAPARAN_BELUM_LENGKAP;
    }

    public function statusBadgeClass(): string
    {
        return [
            self::SAH => 'status-rendah',
            self::MENUNGGU_PPA => 'status-sederhana',
            self::MENUNGGU_KB => 'status-sederhana',
            self::DIKEMBALIKAN => 'status-sederhana',
        ][$this->status] ?? 'status-tinggi';
    }

    /**
     * Kelas badge bagi label paparan — termasuk keadaan "tiada laporan lagi".
     */
    public static function badgePaparan(?self $laporan): string
    {
        return $laporan?->statusBadgeClass() ?? 'status-tinggi';
    }

    public function scopeForAgency($query, string $agencyCode)
    {
        return $query->where('agency_code', $agencyCode);
    }

    public function scopeMenunggu($query, string $status)
    {
        return $query->where('status', $status);
    }
}
