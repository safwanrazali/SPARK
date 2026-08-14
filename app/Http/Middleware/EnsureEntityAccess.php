<?php

namespace App\Http\Middleware;

use App\Services\EntityAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FASA 4 — kawalan akses entiti pada peringkat route.
 *
 * Digunakan pada mana-mana route yang mempunyai parameter {agencyCode}.
 * Permintaan ditolak dengan 403 sebelum controller dijalankan, termasuk
 * capaian melalui URL langsung atau permintaan JSON/API.
 */
class EnsureEntityAccess
{
    public function __construct(private readonly EntityAccessService $access) {}

    public function handle(Request $request, Closure $next, string $parameter = 'agencyCode'): Response
    {
        abort_unless($request->user() !== null, 403);

        $agencyCode = $request->route($parameter);

        $this->access->authorize($request->user(), is_string($agencyCode) ? $agencyCode : null);

        return $next($request);
    }
}
