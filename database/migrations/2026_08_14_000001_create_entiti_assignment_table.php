<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel untuk melacak penugasan entiti kepada Pegawai Analisis.
     *
     * Koordinator dapat menugaskan entiti (berdasarkan agency_code) kepada Pegawai Analisis.
     * Setiap penugasan direkodkan dengan siapa yang menugaskan dan bila.
     */
    public function up(): void
    {
        Schema::create('entiti_assignment', function (Blueprint $table) {
            $table->id();

            // Entiti/agensi yang ditugaskan
            $table->string('agency_code')->index();
            $table->string('agency_name');
            $table->string('sector_code');
            $table->string('sector_name');

            // Pegawai yang ditugaskan untuk entiti tersebut
            $table->foreignId('assigned_to_user_id')->constrained('users')->onDelete('cascade');

            // Siapa yang membuat penugasan
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Tarikh penugasan
            $table->timestamp('assigned_at')->useCurrent();

            // Status penugasan (active, archived, etc)
            $table->string('status')->default('active')->index();

            // Catatan penugasan jika diperlukan
            $table->text('notes')->nullable();

            // Audit timestamps
            $table->timestamps();

            // Indexes untuk query yang cepat
            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['agency_code', 'status']);

            // Unique constraint: satu entiti tidak boleh ditugaskan kepada lebih daripada seorang secara aktif
            // (tetapi boleh ada beberapa rekod lama jika reassign berlaku)
            $table->unique(['agency_code', 'assigned_to_user_id', 'status'], 'unique_active_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entiti_assignment');
    }
};
