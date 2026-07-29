<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('muat_naik', function (Blueprint $table) {

            $table->string('nama_helaian')
                ->nullable();

            $table->integer('jumlah_helaian')
                ->nullable();

            $table->integer('jumlah_baris')
                ->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('muat_naik', function (Blueprint $table) {

            $table->dropColumn([
                'nama_helaian',
                'jumlah_helaian',
                'jumlah_baris',
            ]);

        });
    }
};
