<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status SETIAP peringkat bagi setiap entiti.
     *
     * `workflow_status` memegang kedudukan semasa entiti — satu baris, satu
     * peringkat. Papan pemuka pula perlu menjawab "apa status peringkat 3
     * bagi entiti ini?" tanpa mengira semula daripada jejak audit, jadi
     * setiap peringkat mendapat barisnya sendiri di sini.
     *
     * Tujuh baris dicipta sekali gus apabila entiti didaftarkan, supaya
     * ketiadaan baris bermakna "belum didaftarkan" dan bukan "belum mula".
     */
    public function up(): void
    {
        Schema::create('workflow_stage_status', function (Blueprint $table) {
            $table->id();

            $table->string('agency_code');
            $table->string('agency_name');
            $table->string('sector_code');
            $table->string('sector_name');

            // Nombor peringkat 1–7 (lihat WorkflowStatus::WORKFLOW_STAGES).
            $table->unsignedTinyInteger('stage');

            // Belum Mula | Dalam Proses | Selesai
            $table->string('status')->default('Belum Mula');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Satu baris sahaja bagi setiap peringkat setiap entiti.
            $table->unique(['agency_code', 'stage']);

            // Papan pemuka menapis mengikut sektor dan peringkat.
            $table->index(['sector_code', 'stage']);
            $table->index(['stage', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stage_status');
    }
};
