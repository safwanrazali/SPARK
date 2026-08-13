<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisis_inventori', function (Blueprint $table) {
            $table->id();
            $table->string('sector_code');
            $table->string('sector_name');
            $table->string('agency_code')->unique();
            $table->string('agency_name');
            $table->date('tarikh_laporan')->nullable();
            $table->string('kod_rujukan')->nullable();
            $table->string('status_laporan')->default('Muktamad');
            // Keseluruhan dapatan berstruktur (status data, profil, algoritma,
            // protokol, pustaka, vendor, tindakan, kesimpulan) disimpan sebagai JSON.
            $table->json('data');
            $table->boolean('selesai')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_inventori');
    }
};
