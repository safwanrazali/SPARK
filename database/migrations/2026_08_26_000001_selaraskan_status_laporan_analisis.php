<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Templat rasmi Laporan Analisis Inventori Kriptografi hanya membenarkan DUA
 * status laporan: 'Selesai' dan 'Memerlukan Tindakan Susulan'.
 *
 * Lajur `analisis_inventori.status_laporan` sebelum ini menyimpan tiga nilai
 * ('Muktamad', 'Muktamad dengan Catatan', 'Memerlukan Tindakan Susulan').
 * Kedua-dua nilai 'Muktamad*' bermaksud laporan telah dimuktamadkan, jadi ia
 * dipetakan kepada 'Selesai'. 'Memerlukan Tindakan Susulan' kekal.
 *
 * Tanpa migrasi ini, rekod lama akan terus memaparkan status yang tidak lagi
 * wujud dalam senarai borang, dan simpanan seterusnya akan gagal pengesahan.
 *
 * NOTA: lajur ini TIADA kaitan dengan jadual `status_laporan` (status aliran
 * kerja bagi setiap entiti) — jadual tersebut tidak disentuh.
 */
return new class extends Migration
{
    private const PETAAN = [
        'Muktamad' => 'Selesai',
        'Muktamad dengan Catatan' => 'Selesai',
    ];

    public function up(): void
    {
        foreach (self::PETAAN as $lama => $baharu) {
            DB::table('analisis_inventori')
                ->where('status_laporan', $lama)
                ->update(['status_laporan' => $baharu]);
        }

        Schema::table('analisis_inventori', function (Blueprint $table) {
            $table->string('status_laporan')->default('Selesai')->change();
        });
    }

    public function down(): void
    {
        // Pemetaan ini LOSSY: 'Muktamad dengan Catatan' tidak dapat dipulihkan
        // kerana ia bergabung dengan 'Muktamad' semasa up(). Semua rekod
        // 'Selesai' kembali sebagai 'Muktamad'.
        DB::table('analisis_inventori')
            ->where('status_laporan', 'Selesai')
            ->update(['status_laporan' => 'Muktamad']);

        Schema::table('analisis_inventori', function (Blueprint $table) {
            $table->string('status_laporan')->default('Muktamad')->change();
        });
    }
};
