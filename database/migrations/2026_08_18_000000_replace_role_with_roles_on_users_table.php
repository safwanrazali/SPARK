<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seorang pengguna kini boleh memegang lebih daripada satu peranan.
 *
 * Lajur tunggal `role` digantikan dengan `roles` (JSON). Data sedia ada
 * dipindahkan dahulu supaya setiap pengguna mengekalkan peranan asalnya
 * sebagai satu-satunya elemen dalam senarai baharu — tiada akaun kehilangan
 * kebenarannya semasa naik taraf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('username');
        });

        foreach (DB::table('users')->select('id', 'role')->get() as $pengguna) {
            DB::table('users')
                ->where('id', $pengguna->id)
                ->update(['roles' => json_encode(array_values(array_filter([$pengguna->role])))]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('analyst')->after('username');
        });

        // Hanya peranan pertama dapat dikekalkan; lajur tunggal tidak mampu
        // menyimpan selebihnya.
        foreach (DB::table('users')->select('id', 'roles')->get() as $pengguna) {
            $peranan = json_decode((string) $pengguna->roles, true) ?: [];

            DB::table('users')
                ->where('id', $pengguna->id)
                ->update(['role' => $peranan[0] ?? 'analyst']);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
