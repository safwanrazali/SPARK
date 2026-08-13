<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel untuk jejak audit - mencatat semua perubahan penting dalam sistem.
     *
     * Termasuk:
     * - Perubahan status workflow
     * - Perubahan penugasan
     * - Perubahan status laporan
     * - Kelulusan/semakan
     * - Perubahan analisis
     *
     * Tabel ini memastikan setiap tindakan boleh dijejak kepada pengguna dan masa.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();

            // Entiti yang terlibat
            $table->string('agency_code')->index();
            $table->string('agency_name')->nullable();

            // Jenis tindakan (status_changed, assigned, analysis_saved, report_generated, approved, etc)
            $table->string('action')->index();

            // Nilai lama (sebelum perubahan)
            $table->longText('old_value')->nullable();

            // Nilai baharu (selepas perubahan)
            $table->longText('new_value')->nullable();

            // Siapa yang membuat perubahan
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Bila perubahan dibuat
            $table->timestamp('changed_at')->useCurrent();

            // Metadata tambahan (JSON) - untuk maklumat konteks tambahan
            $table->json('metadata')->nullable();

            // Audit timestamps
            $table->timestamps();

            // Indexes untuk query yang cepat
            $table->index(['agency_code', 'changed_at']);
            $table->index(['action', 'changed_at']);
            $table->index(['changed_by_user_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
