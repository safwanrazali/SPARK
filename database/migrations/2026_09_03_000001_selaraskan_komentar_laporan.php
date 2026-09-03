<?php

use App\Support\SeksyenAnalisis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar laporan — maklum balas + pengakuan, bukan kitaran kelulusan.
 *
 * Tiga perkara diselaraskan di sini:
 *
 * 1. STATUS. Komentar kini mempunyai status tersendiri (Terbuka →
 *    Tindakan Diambil) berserta identiti Pegawai Analisis yang mengambil
 *    tindakan dan cap masanya. Status ini SEPENUHNYA berasingan daripada
 *    status peringkat 3.1 (Belum Selesai → Selesai); tiada satu pun
 *    mempengaruhi yang lain.
 *
 * 2. PEMADAMAN LEMBUT. Komentar yang dipadam pengarangnya kekal dalam
 *    pangkalan data supaya jejak maklum balas tidak hilang.
 *
 * 3. KUNCI SEKSYEN. Komentar ditambat pada SEMBILAN seksyen Borang Input
 *    (App\Support\SeksyenAnalisis), bukan pada tajuk laporan yang direka
 *    khusus untuk modul komentar. Baris sedia ada dipetakan semula.
 */
return new class extends Migration
{
    /**
     * Kunci lama (dijana bersama modul komentar asal) => kunci seksyen borang.
     */
    private const PEMETAAN = [
        'pengenalan' => 'maklumat',
        'kerangka_kerja' => 'maklumat',
        'lampiran' => 'maklumat',
        'keadaan_semasa' => 'data_status',
        'algoritma_kenal_pasti' => 'algoritma',
        'profil_sistem_aset' => 'profil',
        'algoritma_kriptografi' => 'algoritma',
        'protokol_kriptografi' => 'protokol',
        'pustaka_modul_kriptografi' => 'pustaka',
        'maklumat_vendor' => 'vendor',
    ];

    public function up(): void
    {
        Schema::table('laporan_komentar', function (Blueprint $table) {
            $table->string('status', 30)
                ->default('terbuka')
                ->after('content');

            $table->foreignId('tindakan_oleh_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('tindakan_pada')
                ->nullable()
                ->after('tindakan_oleh_user_id');

            $table->softDeletes();
        });

        foreach (self::PEMETAAN as $lama => $baharu) {
            DB::table('laporan_komentar')->where('section', $lama)->update(['section' => $baharu]);
        }

        // Jaring keselamatan: sebarang kunci yang tidak dikenali diletakkan
        // pada seksyen pertama borang supaya setiap baris kekal sah terhadap
        // pengesahan pelayan yang baharu.
        DB::table('laporan_komentar')
            ->whereNotIn('section', SeksyenAnalisis::kunci())
            ->update(['section' => 'maklumat']);
    }

    /**
     * Pemetaan semula kunci seksyen TIDAK diterbalikkan — ia kehilangan
     * maklumat (beberapa kunci lama bertemu pada satu kunci baharu) dan
     * kunci lama itu sendiri tiada tempat dalam borang.
     */
    public function down(): void
    {
        Schema::table('laporan_komentar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tindakan_oleh_user_id');
            $table->dropColumn(['status', 'tindakan_pada', 'deleted_at']);
        });
    }
};
