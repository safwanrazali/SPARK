<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel untuk menyimpan data muat naik fail (legacy module).
     * Digunakan untuk penjejakan muat naik fail Excel data entiti.
     */
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('muat_naik');
    }
};
