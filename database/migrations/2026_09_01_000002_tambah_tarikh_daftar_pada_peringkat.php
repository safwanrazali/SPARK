<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peringkat 1.2 "Pendaftaran Data" menangkap TARIKH DAFTAR.
 *
 * Lajur berasingan daripada `tarikh_terima`: kedua-duanya tarikh, tetapi
 * menjawab soalan berbeza — bila data DITERIMA (peringkat 1.1) dan bila ia
 * DIDAFTARKAN (peringkat 1.2). Berkongsi satu lajur akan menjadikan salah
 * satu menimpa yang lain apabila kedua-dua peringkat direkod.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stage_status', function (Blueprint $table) {
            $table->date('tarikh_daftar')->nullable()->after('tarikh_terima');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stage_status', function (Blueprint $table) {
            $table->dropColumn('tarikh_daftar');
        });
    }
};
