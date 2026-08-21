<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel untuk melacak versi draf laporan analisis.
     *
     * Membolehkan:
     * - Simpan draf seksyen demi seksyen
     * - Jejak versi draf
     * - Resume dari tempat terakhir
     * - Mencegah kehilangan data
     * - Audit versi laporan
     *
     * Setiap kali pengguna menyimpan, satu rekod dibuat.
     * Rekod semasa ditandakan dengan is_current = true.
     */
    public function up(): void
    {
        Schema::create('analisis_draft_history', function (Blueprint $table) {
            $table->id();

            // Analisis Inventori Kriptografi yang sedang draf
            $table->foreignId('analisis_inventori_id')->constrained('analisis_inventori')->onDelete('cascade');

            // Versi draf (incrementing untuk setiap simpanan)
            $table->integer('version')->default(1);

            // Nama seksyen yang disimpan (optional - jika save per-seksyen)
            $table->string('section_name')->nullable();

            // Data seksyen/keseluruhan (JSON)
            $table->json('section_data');

            // Adakah seksyen ini lengkap?
            $table->boolean('completed')->default(false);

            // Tarikh simpanan
            $table->timestamp('saved_at')->useCurrent();

            // Siapa yang menyimpan
            $table->foreignId('saved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Adakah ini versi semasa? (untuk pemulihan cepat)
            $table->boolean('is_current')->default(true);

            // Audit timestamps
            $table->timestamps();

            // Indexes
            $table->index(['analisis_inventori_id', 'is_current']);
            $table->index(['saved_by_user_id', 'saved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_draft_history');
    }
};
