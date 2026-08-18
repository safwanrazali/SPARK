<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pentadbir menetapkan semula kata laluan pengguna.
 *
 * Tiga perkara berlaku serentak, dan ketiga-tiganya perlu:
 *
 * 1. Kata laluan sementara dijana — pentadbir tidak memilih kata laluan
 *    lemah, dan kata laluan lama tidak lagi sah.
 * 2. Akaun ditanda `must_change_password`, jadi pemiliknya wajib
 *    menggantikan kata laluan sementara itu pada log masuk berikutnya.
 * 3. Sesi aktif akaun itu dibatalkan. Tanpa langkah ini, sesi yang dirampas
 *    kekal hidup selepas tetapan semula — dan kerana skrin tukar kata laluan
 *    tidak meminta kata laluan semasa, penyerang boleh menetapkan kata
 *    laluannya sendiri dan mengekalkan akses.
 */
class TetapSemulaKataLaluan
{
    /**
     * @return string Kata laluan sementara, untuk dipapar SEKALI sahaja
     */
    public function jalankan(User $pengguna): string
    {
        $sementara = $this->janaKataLaluan();

        $pengguna->forceFill([
            'password' => Hash::make($sementara),
            'must_change_password' => true,
        ])->save();

        $this->batalkanSesi($pengguna);

        return $sementara;
    }

    /**
     * Kata laluan sementara perlu disampaikan kepada pengguna secara lisan
     * atau bertulis, jadi simbol ditinggalkan supaya ia tidak tersalah taip.
     * Pada 16 aksara huruf-nombor campuran, entropinya jauh melebihi minimum
     * 12 aksara yang dikenakan ke atas kata laluan pilihan sendiri.
     *
     * Sama seperti AkaunPentadbirLalai supaya kedua-dua laluan pemulihan
     * menghasilkan kata laluan yang setara kekuatannya.
     */
    private function janaKataLaluan(): string
    {
        return Str::password(16, symbols: false);
    }

    /**
     * Sesi disimpan dalam pangkalan data (SESSION_DRIVER=database), jadi
     * membuang barisnya menamatkan setiap sesi aktif akaun tersebut.
     */
    private function batalkanSesi(User $pengguna): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $pengguna->getKey())
            ->delete();
    }
}
