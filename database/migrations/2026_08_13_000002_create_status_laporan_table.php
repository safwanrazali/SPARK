<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_laporan', function (Blueprint $table) {
            $table->id();
            $table->string('sector_code');
            $table->string('sector_name');
            $table->string('agency_code');
            $table->string('agency_name');
            // inventori | risiko | kesiapsiagaan
            $table->string('jenis');
            // Belum Bermula | Dalam Proses | Siap
            $table->string('status')->default('Belum Bermula');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency_code', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_laporan');
    }
};
