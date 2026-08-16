<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * FASA 13 — akaun pentadbir awal.
 *
 * Peraturan keselamatan:
 * - Kata laluan TIDAK ditulis di dalam kod; ia dibaca daripada konfigurasi
 *   (.env) supaya setiap pemasangan mempunyai kelayakan tersendiri.
 * - Pada pelayan pengeluaran, ADMIN_PASSWORD wajib ditetapkan.
 * - Jika akaun pentadbir telah wujud, kata laluannya TIDAK ditetapkan semula.
 *   Menjalankan semula seeder tidak boleh memulangkan kata laluan lama.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = (string) config('pentadbir.username');

        $sediaAda = User::where('username', $username)->first();

        if ($sediaAda !== null) {
            // Pastikan peranan kekal betul tanpa menyentuh kata laluan.
            if ($sediaAda->role !== User::ROLE_ADMINISTRATOR) {
                $sediaAda->forceFill(['role' => User::ROLE_ADMINISTRATOR])->save();
            }

            $this->command?->info(
                "Akaun pentadbir [{$username}] telah wujud. Kata laluan tidak diubah."
            );

            return;
        }

        User::create([
            'name' => (string) config('pentadbir.name'),
            'username' => $username,
            'email' => (string) config('pentadbir.email'),
            'password' => Hash::make($this->kataLaluan($username)),
            'email_verified_at' => now(),
            'role' => User::ROLE_ADMINISTRATOR,
        ]);
    }

    /**
     * Kata laluan daripada .env, atau kata laluan rawak untuk pemasangan
     * bukan pengeluaran (dipaparkan sekali sahaja pada konsol).
     */
    private function kataLaluan(string $username): string
    {
        $dariEnv = config('pentadbir.password');

        if (is_string($dariEnv) && $dariEnv !== '') {
            return $dariEnv;
        }

        if (app()->environment('production')) {
            throw new RuntimeException(
                'ADMIN_PASSWORD wajib ditetapkan dalam .env sebelum akaun pentadbir '
                .'boleh disemai pada pelayan pengeluaran.'
            );
        }

        $rawak = Str::password(16, symbols: false);

        $this->command?->warn(
            "Kata laluan pentadbir [{$username}] dijana: {$rawak}"
        );
        $this->command?->warn(
            'Simpan sekarang — ia tidak akan dipaparkan semula. Tukar selepas log masuk pertama.'
        );

        return $rawak;
    }
}
