<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use Illuminate\Database\Eloquent\Model;

class StatusLaporan extends Model
{
    use FiltersByEntityAccess;

    protected $table = 'status_laporan';

    public const JENIS = [
        'inventori' => 'Inventori',
        'risiko' => 'Risiko PQC',
        'kesiapsiagaan' => 'Kesiapsiagaan',
    ];

    public const KITARAN = ['Belum Bermula', 'Dalam Proses', 'Siap'];

    protected $fillable = [
        'sector_code', 'sector_name', 'agency_code', 'agency_name',
        'jenis', 'status', 'user_id',
    ];

    public function statusSeterusnya(): string
    {
        $i = array_search($this->status, self::KITARAN, true);

        return self::KITARAN[($i === false ? 0 : $i + 1) % count(self::KITARAN)];
    }
}
