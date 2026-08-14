<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MuatNaik extends Model
{
    use FiltersByEntityAccess, HasFactory;

    protected $table = 'muat_naik';

    protected $fillable = [
        'nama_fail',
        'lokasi_fail',
        'status',
        'jumlah_rekod',
        'tarikh_import',
        'sector_code',
        'sector_name',
        'agency_code',
        'agency_name',
    ];
}
