<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel untuk melacak peringkat workflow semasa setiap entiti.
     *
     * Setiap entiti melalui 7 peringkat:
     * 1. Penerimaan & Pendaftaran Data
     * 2. Semakan Awal Data
     * 3. Penyediaan & Pengesahan Data
     * 4. Pelaksanaan Analisis
     * 5. Penjanaan Laporan
     * 6. Semakan & Kelulusan
     * 7. Penyerahan & Penutupan
     *
     * Tabel ini menyimpan peringkat semasa dan tarikh perubahan.
     */
    public function up(): void
    {
        Schema::create('workflow_status', function (Blueprint $table) {
            $table->id();

            // Entiti yang sedang dalam workflow
            $table->string('agency_code')->unique()->index();
            $table->string('agency_name');
            $table->string('sector_code');
            $table->string('sector_name');

            // Peringkat workflow semasa (1-7)
            $table->integer('current_stage')->default(1);

            // Nama peringkat untuk kemudahan bacaan
            $table->string('stage_name')->default('Penerimaan & Pendaftaran Data');

            // Tarikh entiti memasuki peringkat semasa
            $table->timestamp('status_since')->useCurrent();

            // Siapa yang mengemas kini peringkat terakhir
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Catatan peringkat (cth: alasan untuk kembali ke peringkat sebelumnya)
            $table->text('notes')->nullable();

            // Audit timestamps
            $table->timestamps();

            // Index untuk query yang cepat
            $table->index(['current_stage']);
            $table->index(['sector_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_status');
    }
};
