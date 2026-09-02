<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('laporan_komentar', function (Blueprint $table) {
            $table->id();
            $table->string('agency_code', 50);
            $table->string('agency_name', 255);
            $table->string('section', 100)->comment('Seksyen laporan yang dikomenkari (Intro, Kerangka, Algoritma, dll)');
            $table->text('content')->comment('Kandungan ulasan/komentar');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('agency_code');
            $table->index('user_id');
            $table->index('created_at');
            $table->comment('Komentar KB dan PPA pada laporan — hanya dilihat PA, tidak disertakan dalam PDF');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_komentar');
    }
};
