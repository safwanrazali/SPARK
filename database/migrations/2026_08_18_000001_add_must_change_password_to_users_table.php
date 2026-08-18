<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kata laluan yang dikeluarkan oleh Pentadbir Sistem ialah kata laluan
 * sementara: pemiliknya wajib menukarnya pada log masuk pertama, supaya
 * tiada akaun kekal dengan kata laluan yang diketahui orang lain.
 *
 * Lalai `false` — akaun sedia ada tidak dipaksa menukar kata laluan semasa
 * naik taraf; hanya akaun baharu (dan tetapan semula oleh pentadbir) ditanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')
                ->default(false)
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
