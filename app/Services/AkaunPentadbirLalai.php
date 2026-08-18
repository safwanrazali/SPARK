<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Akaun pentadbir lalai — satu-satunya pintu masuk pemasangan baharu.
 *
 * Sistem mesti sentiasa mempunyai sekurang-kurangnya satu Pentadbir Sistem,
 * kerana hanya peranan itu boleh menambah pengguna. Kelas ini ialah tempat
 * tunggal yang mencipta atau memulihkan akaun tersebut, dikongsi oleh
 * AdminUserSeeder (pemasangan) dan arahan `pentadbir:sedia` (pemulihan).
 *
 * Peraturan keselamatan yang dikekalkan:
 * - Kata laluan tidak pernah ditulis di dalam kod.
 * - Kata laluan akaun sedia ada TIDAK ditetapkan semula melainkan diminta
 *   secara eksplisit; menjalankan semula semaian tidak memulangkan kata
 *   laluan lama.
 * - Pada pelayan pengeluaran, kata laluan mesti datang daripada .env atau
 *   diberikan secara eksplisit; tiada penjanaan rawak senyap di sana.
 */
class AkaunPentadbirLalai
{
    /**
     * Pastikan akaun pentadbir lalai wujud dan memegang peranan pentadbir.
     *
     * @param  string|null  $kataLaluan  kata laluan eksplisit; null = ikut .env
     * @param  bool  $tetapSemula  benarkan kata laluan akaun sedia ada ditukar
     * @return array{user: User, dicipta: bool, kataLaluan: string|null}
     *                                             kataLaluan hanya diisi jika
     *                                             ia baharu ditetapkan
     */
    public function pastikan(?string $kataLaluan = null, bool $tetapSemula = false): array
    {
        $username = (string) config('pentadbir.username');

        $sediaAda = User::where('username', $username)->first();

        if ($sediaAda === null) {
            $baharu = $kataLaluan ?? $this->kataLaluanBaharu();

            $user = User::create([
                'name' => (string) config('pentadbir.name'),
                'username' => $username,
                'email' => (string) config('pentadbir.email'),
                'password' => Hash::make($baharu),
                'email_verified_at' => now(),
                'roles' => [User::ROLE_ADMINISTRATOR],
            ]);

            return ['user' => $user, 'dicipta' => true, 'kataLaluan' => $baharu];
        }

        // Peranan pentadbir mesti ada, tanpa membuang peranan lain yang
        // mungkin telah diberikan kepada akaun itu.
        if (! $sediaAda->hasRole(User::ROLE_ADMINISTRATOR)) {
            $sediaAda->forceFill([
                'roles' => [...$sediaAda->assignedRoles(), User::ROLE_ADMINISTRATOR],
            ])->save();
        }

        if (! $tetapSemula && $kataLaluan === null) {
            return ['user' => $sediaAda, 'dicipta' => false, 'kataLaluan' => null];
        }

        $baharu = $kataLaluan ?? $this->kataLaluanBaharu();

        $sediaAda->forceFill(['password' => Hash::make($baharu)])->save();

        return ['user' => $sediaAda, 'dicipta' => false, 'kataLaluan' => $baharu];
    }

    /**
     * Kata laluan daripada .env, atau kata laluan rawak untuk pemasangan
     * bukan pengeluaran.
     */
    private function kataLaluanBaharu(): string
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

        return Str::password(16, symbols: false);
    }
}
