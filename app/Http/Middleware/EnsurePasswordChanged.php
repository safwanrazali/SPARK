<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kata laluan sementara mesti ditukar sebelum sistem boleh digunakan.
 *
 * Akaun yang ditanda `must_change_password` dikunci kepada skrin tukar kata
 * laluan: setiap route lain dialihkan ke sana. Tanpa ini, kata laluan yang
 * dikeluarkan oleh pentadbir boleh kekal digunakan selama-lamanya.
 */
class EnsurePasswordChanged
{
    /**
     * Route yang mesti kekal boleh dicapai, jika tidak pengguna terperangkap:
     * skrin tukar kata laluan itu sendiri dan log keluar.
     */
    private const DIKECUALIKAN = [
        'kata-laluan.tukar',
        'kata-laluan.simpan',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna === null || ! $pengguna->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(self::DIKECUALIKAN)) {
            return $next($request);
        }

        return redirect()->route('kata-laluan.tukar');
    }
}
