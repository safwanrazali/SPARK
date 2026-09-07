<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Namakan semula jadual `laporan_komentar` kepada `laporan_catatan`.
 *
 * "Komentar" bukan perkataan Bahasa Melayu; istilah rasmi modul ini ialah
 * CATATAN. Penamaan semula ini menyelaraskan skema dengan nama kelas, laluan,
 * paparan dan teks antara muka yang telah ditukar serentak.
 *
 * BARIS TIDAK DISENTUH. `ALTER TABLE ... RENAME TO` memindahkan jadual beserta
 * kandungannya; tiada baris dicipta, diubah atau dipadam oleh migrasi ini.
 * Struktur lajur, kekangan kunci asing dan pemadaman lembut kekal seperti sedia
 * ada.
 *
 * Nama INDEKS sengaja tidak diubah (`laporan_komentar_*`). Menamakannya semula
 * pada SQLite memerlukan pembinaan semula jadual, dan nama indeks tidak dirujuk
 * oleh mana-mana bahagian aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sudah dinamakan semula (contohnya pada pemasangan yang lebih baharu).
        if (Schema::hasTable('laporan_catatan')) {
            return;
        }

        if (! Schema::hasTable('laporan_komentar')) {
            throw new RuntimeException(
                'Jadual `laporan_komentar` tidak dijumpai dan `laporan_catatan` '.
                'juga tiada. Migrasi dihentikan supaya keadaan skema yang tidak '.
                'dijangka tidak diteruskan secara senyap.'
            );
        }

        Schema::rename('laporan_komentar', 'laporan_catatan');
    }

    public function down(): void
    {
        if (Schema::hasTable('laporan_komentar')) {
            return;
        }

        if (! Schema::hasTable('laporan_catatan')) {
            return;
        }

        Schema::rename('laporan_catatan', 'laporan_komentar');
    }
};
