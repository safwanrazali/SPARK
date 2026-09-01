<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use App\Support\AliranKerja;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status DAN data tangkapan satu peringkat aliran kerja bagi satu entiti.
 *
 * WorkflowStatus menjawab "di mana entiti ini sekarang"; model ini menjawab
 * "apa status setiap peringkatnya, dan apa yang direkodkan padanya". Papan
 * pemuka membaca daripada sini supaya angka tidak perlu dikira semula
 * daripada jejak audit — pangkalan data ialah sumber kebenaran, bukan
 * keadaan UI.
 *
 * `stage` ialah KUNCI peringkat ('1.1', '2', '3.1'), bukan integer: dalam
 * struktur bersarang, sub-peringkat ialah sebahagian daripada identiti
 * peringkat. Lihat App\Support\AliranKerja.
 *
 * Perbendaharaan status di sini SENGAJA berbeza daripada StatusLaporan::KITARAN
 * ('Belum Bermula' / 'Dalam Proses' / 'Siap'), yang milik modul Status Tiga
 * Laporan. Peringkat berakhir dengan 'Selesai'; hanya keseluruhan entiti
 * berakhir dengan 'Siap' (lihat KemajuanAnalisisService::keseluruhan()).
 */
class WorkflowStageStatus extends Model
{
    use FiltersByEntityAccess, HasFactory;

    protected $table = 'workflow_stage_status';

    public const BELUM_MULA = 'Belum Mula';

    public const DALAM_PROSES = 'Dalam Proses';

    public const SELESAI = 'Selesai';

    /**
     * Kitaran status bagi satu peringkat.
     */
    public const STATUSES = [
        self::BELUM_MULA,
        self::DALAM_PROSES,
        self::SELESAI,
    ];

    protected $fillable = [
        'agency_code',
        'agency_name',
        'sector_code',
        'sector_name',
        'stage',
        'status',
        'tarikh_terima',
        'tarikh_semakan',
        'tarikh_mula',
        'tarikh_tamat',
        'status_borang',
        'nama_fail',
        'no_rujukan',
        'no_rujukan_oleh_user_id',
        'no_rujukan_pada',
        'started_at',
        'completed_at',
        'updated_by_user_id',
        'notes',
    ];

    protected $casts = [
        'stage' => 'string',
        'tarikh_terima' => 'date',
        'tarikh_semakan' => 'date',
        'tarikh_mula' => 'date',
        'tarikh_tamat' => 'date',
        'no_rujukan_pada' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Pegawai yang memasukkan No. Rujukan — pada peringkat 1.1 hingga 1.3
     * ini ialah PPR, bukan pegawai yang melaksanakan peringkat itu.
     */
    public function noRujukanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_rujukan_oleh_user_id');
    }

    public static function isValidStatus(?string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public function isSelesai(): bool
    {
        return $this->status === self::SELESAI;
    }

    public function isBelumMula(): bool
    {
        return $this->status === self::BELUM_MULA;
    }

    /**
     * Nama peringkat — sumbernya kekal AliranKerja supaya setiap modul
     * membaca struktur yang sama.
     */
    public function stageName(): string
    {
        return AliranKerja::label($this->stage);
    }

    /**
     * Nombor dan nama, seperti dipaparkan kepada pengguna ("1.1 Penerimaan Data").
     */
    public function stageLabel(): string
    {
        return AliranKerja::labelPenuh($this->stage);
    }

    /**
     * Peringkat utama yang memiliki peringkat ini.
     */
    public function mainStage(): ?int
    {
        return AliranKerja::utamaBagi($this->stage);
    }

    /**
     * Adakah peringkat ini sebahagian daripada fasa semasa?
     *
     * Peringkat fasa akan datang mempunyai baris (supaya strukturnya wujud)
     * tetapi tidak menerima sebarang tindakan.
     */
    public function fasaSemasa(): bool
    {
        return AliranKerja::adalahSemasa($this->stage);
    }

    /**
     * Data tangkapan peringkat ini: label => nilai, mengikut medan yang
     * benar-benar berkenaan baginya.
     *
     * @return array<string, mixed>
     */
    public function dataTangkapan(): array
    {
        $hasil = [];

        foreach (AliranKerja::medan($this->stage) as $lajur => $label) {
            $hasil[$label] = $this->{$lajur};
        }

        if (($rujukan = AliranKerja::labelRujukan($this->stage)) !== null) {
            $hasil[$rujukan] = $this->no_rujukan;
        }

        return $hasil;
    }

    /**
     * Kelas badge selaras dengan modul pemantauan yang lain.
     */
    public function statusBadgeClass(): string
    {
        return [
            self::SELESAI => 'status-rendah',
            self::DALAM_PROSES => 'status-sederhana',
        ][$this->status] ?? 'status-tinggi';
    }

    public function scopeForAgency($query, string $agencyCode)
    {
        return $query->where('agency_code', $agencyCode);
    }

    public function scopeAtStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeSelesai($query)
    {
        return $query->where('status', self::SELESAI);
    }

    /**
     * Peringkat fasa semasa sahaja — asas setiap kiraan kemajuan.
     */
    public function scopeFasaSemasa($query)
    {
        return $query->whereIn('stage', AliranKerja::semasa());
    }
}
