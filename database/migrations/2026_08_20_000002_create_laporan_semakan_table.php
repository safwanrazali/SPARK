<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kedudukan semasa satu laporan dalam kitaran semakan dan kelulusan.
     *
     * `approval_logs` sedia ada menyimpan JEJAK setiap tindakan semakan.
     * Jadual ini menyimpan KEDUDUKAN SEMASA sahaja, supaya senarai dan papan
     * pemuka tidak perlu mengira semula keadaan daripada jejak setiap kali.
     *
     * Kitaran (lihat LaporanSemakan::ALIRAN):
     *   Draf → Dihantar kepada PPA → Dihantar kepada KB → Sah
     *   mana-mana peringkat semakan boleh → Dikembalikan → Draf semula
     */
    public function up(): void
    {
        Schema::create('laporan_semakan', function (Blueprint $table) {
            $table->id();

            $table->string('agency_code');
            $table->string('agency_name');
            $table->string('sector_code');
            $table->string('sector_name');

            // inventori | risiko | kesiapsiagaan (lihat ApprovalLog::REPORT_TYPES)
            $table->string('report_type')->default('inventori');

            $table->string('status')->default('Draf');

            // Catatan wajib apabila laporan dikembalikan kepada PA.
            $table->text('catatan')->nullable();

            $table->foreignId('dihantar_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dihantar_pada')->nullable();

            $table->foreignId('disemak_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disemak_pada')->nullable();

            $table->foreignId('disahkan_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disahkan_pada')->nullable();

            $table->timestamps();

            // Satu kedudukan semasa bagi setiap laporan setiap entiti.
            $table->unique(['agency_code', 'report_type']);

            $table->index(['status']);
            $table->index(['sector_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_semakan');
    }
};
