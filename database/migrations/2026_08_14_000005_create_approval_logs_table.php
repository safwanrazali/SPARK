<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel untuk melacak kelulusan dan semakan laporan.
     *
     * Menyimpan:
     * - Siapa yang meluluskan
     * - Status sebelum/sesudah
     * - Masa kelulusan
     * - Komen/catatan semakan
     *
     * Tabel ini memastikan jejak kelulusan laporan lengkap dan boleh diaudit.
     */
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();

            // Entiti yang berkaitan
            $table->string('agency_code')->index();
            $table->string('agency_name')->nullable();

            // Jenis laporan (inventori, risiko, kesiapsiagaan)
            $table->string('report_type');

            // Status sebelum kelulusan
            $table->string('status_before')->nullable();

            // Status selepas kelulusan
            $table->string('status_after')->nullable();

            // Siapa yang meluluskan
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Bila kelulusan dibuat
            $table->timestamp('approved_at')->useCurrent();

            // Komen/alasan kelulusan
            $table->longText('comments')->nullable();

            // Audit timestamps
            $table->timestamps();

            // Indexes
            $table->index(['agency_code', 'report_type']);
            $table->index(['approved_by_user_id', 'approved_at']);
            $table->index(['approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
