<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buang jadual `muat_naik` — struktur terakhir modul muat naik Excel.
 *
 * SPARK V1.0 ialah sistem berdiri sendiri: dapatan analisis dimasukkan
 * sepenuhnya melalui borang berstruktur sembilan seksyen, dan tiada bahagian
 * aliran kerja, pelaporan, statistik atau jejak audit yang membaca jadual ini.
 * Kod modul itu (controller, servis Excel, model, paparan dan laluan) telah
 * dibuang; migrasi ini membuang bekasnya.
 *
 * JAMINAN KESELAMATAN
 *
 * Migrasi ini TIDAK memadam baris secara senyap. Jika jadual masih mengandungi
 * rekod, ia BERHENTI dengan ralat supaya keputusan dibuat oleh manusia, bukan
 * oleh proses migrasi. Pangkalan data pembangunan semasa mempunyai 0 baris.
 *
 * Tiada jadual lain disentuh. Khususnya `users`, `entiti_assignment`,
 * `workflow_status`, `workflow_stage_status`, `analisis_inventori`,
 * `analisis_draft_history`, `status_laporan`, `laporan_semakan`,
 * `laporan_komentar`, `approval_logs` dan `activity_log` kekal seperti sedia
 * ada — tiada kunci asing merujuk `muat_naik`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muat_naik')) {
            return;
        }

        $baris = DB::table('muat_naik')->count();

        if ($baris > 0) {
            throw new RuntimeException(sprintf(
                'Jadual `muat_naik` mengandungi %d baris. Migrasi dihentikan supaya '.
                'tiada data dibuang tanpa semakan. Sahkan rekod tersebut tidak '.
                'diperlukan, kosongkan jadual secara sedar, kemudian jalankan '.
                'migrasi ini semula.',
                $baris,
            ));
        }

        Schema::drop('muat_naik');
    }

    /**
     * Kembalikan skema asal (jadual kosong) supaya migrasi boleh dipatah balik.
     * Struktur di bawah sepadan dengan
     * 2026_07_20_000000_create_muat_naiks_table.
     */
    public function down(): void
    {
        if (Schema::hasTable('muat_naik')) {
            return;
        }

        Schema::create('muat_naik', function (Blueprint $table) {
            $table->id();
            $table->string('nama_fail')->nullable();
            $table->string('lokasi_fail')->nullable();
            $table->string('status')->nullable();
            $table->integer('jumlah_rekod')->nullable();
            $table->timestamp('tarikh_import')->nullable();
            $table->string('sector_code')->nullable();
            $table->string('sector_name')->nullable();
            $table->string('agency_code')->nullable();
            $table->string('agency_name')->nullable();
            $table->string('nama_helaian')->nullable();
            $table->integer('jumlah_helaian')->nullable();
            $table->integer('jumlah_baris')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
