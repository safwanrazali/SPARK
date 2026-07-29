<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MuatNaik extends Model
{
    use HasFactory;

    protected $table = 'muat_naik';

    protected $fillable = [
        'nama_fail',
        'lokasi_fail',
        'status',
        'jumlah_rekod',
        'tarikh_import',
    ];
}
