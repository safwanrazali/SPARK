<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASA 2 — melengkapkan state workflow bagi setiap entiti.
     *
     * Fasa 1 telah menyediakan: current_stage, stage_name, status_since
     * dan updated_by_user_id. Spesifikasi (bahagian 11) turut menuntut satu
     * medan `status` bagi peringkat semasa, iaitu kedudukan kerja di dalam
     * peringkat tersebut — berasingan daripada nombor peringkat itu sendiri.
     *
     * Nilai status menggunakan semula kitaran sedia ada dalam codebase
     * (StatusLaporan::KITARAN) supaya tiada sistem status pendua dicipta:
     * Belum Bermula → Dalam Proses → Siap
     */
    public function up(): void
    {
        Schema::table('workflow_status', function (Blueprint $table) {
            $table->string('status')
                ->default('Belum Bermula')
                ->after('stage_name');

            $table->index(['status']);
            $table->index(['current_stage', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_status', function (Blueprint $table) {
            $table->dropIndex(['current_stage', 'status']);
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
