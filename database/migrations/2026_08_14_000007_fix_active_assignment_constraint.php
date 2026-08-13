<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASA 3 — membetulkan kekangan penugasan entiti.
     *
     * Kekangan Fasa 1, unique(agency_code, assigned_to_user_id, status),
     * tidak menguatkuasakan peraturan yang dimaksudkan dalam komennya:
     *
     * 1. Dua pegawai BERBEZA masih boleh mempunyai penugasan 'active' pada
     *    entiti yang sama (tuple berbeza) — iaitu konflik penugasan.
     * 2. Sejarah penugasan berulang (Pegawai A → B → A → B) akan melanggar
     *    kekangan tersebut kerana baris kedua (entiti, A, 'reassigned')
     *    menjadi pendua — sedangkan spesifikasi bahagian 8 menghendaki
     *    sejarah penugasan disimpan.
     *
     * Kekangan digantikan dengan unique(agency_code, active_flag), di mana
     * active_flag = 1 hanya untuk penugasan aktif dan NULL untuk rekod
     * sejarah. Nilai NULL tidak dibandingkan dalam unique index (SQLite,
     * MySQL, PostgreSQL), jadi:
     *
     * - satu entiti hanya boleh mempunyai SATU penugasan aktif
     * - bilangan rekod sejarah bagi entiti yang sama tidak terhad
     *
     * active_flag diselenggara secara automatik oleh model EntitasAssignment
     * dan bukan medan yang diisi secara manual.
     */
    public function up(): void
    {
        Schema::table('entiti_assignment', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_flag')->nullable()->after('status');
        });

        Schema::table('entiti_assignment', function (Blueprint $table) {
            $table->dropUnique('unique_active_assignment');
        });

        $this->selesaikanPenugasanAktifBerkonflik();

        DB::table('entiti_assignment')
            ->where('status', 'active')
            ->update(['active_flag' => 1]);

        DB::table('entiti_assignment')
            ->where('status', '!=', 'active')
            ->update(['active_flag' => null]);

        Schema::table('entiti_assignment', function (Blueprint $table) {
            $table->unique(['agency_code', 'active_flag'], 'unique_active_assignment_per_entity');
        });
    }

    public function down(): void
    {
        Schema::table('entiti_assignment', function (Blueprint $table) {
            $table->dropUnique('unique_active_assignment_per_entity');
            $table->dropColumn('active_flag');
        });

        Schema::table('entiti_assignment', function (Blueprint $table) {
            $table->unique(['agency_code', 'assigned_to_user_id', 'status'], 'unique_active_assignment');
        });
    }

    /**
     * Data sedia ada mungkin mengandungi lebih daripada satu penugasan aktif
     * bagi entiti yang sama kerana kekangan lama membenarkannya. Keadaan itu
     * mesti diselesaikan sebelum kekangan baharu boleh dikuatkuasakan.
     *
     * Penugasan aktif terkini bagi setiap entiti dikekalkan; yang lebih lama
     * ditandakan 'reassigned' supaya kekal sebagai sejarah (tiada baris dibuang).
     */
    private function selesaikanPenugasanAktifBerkonflik(): void
    {
        $berkonflik = DB::table('entiti_assignment')
            ->select('agency_code')
            ->where('status', 'active')
            ->groupBy('agency_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('agency_code');

        foreach ($berkonflik as $agencyCode) {
            $kekal = DB::table('entiti_assignment')
                ->where('agency_code', $agencyCode)
                ->where('status', 'active')
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('entiti_assignment')
                ->where('agency_code', $agencyCode)
                ->where('status', 'active')
                ->where('id', '!=', $kekal)
                ->update([
                    'status' => 'reassigned',
                    'updated_at' => now(),
                ]);
        }
    }
};
