<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\EntityAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * FASA 8 — paparan jejak audit.
 *
 * Halaman ini hanya membaca. Tiada tindakan cipta, kemas kini atau padam
 * disediakan; rekod jejak audit bersifat append-only dan dikuatkuasakan
 * pada model ActivityLog.
 */
class AuditTrailController extends Controller
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly EntityAccessService $access,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('view-audit-trail');

        $pengguna = $request->user();

        $penapis = [
            'agency_code' => $request->query('agency_code'),
            'action' => $request->query('action'),
            'user_id' => $request->query('user_id'),
            'dari' => $request->query('dari'),
            'hingga' => $request->query('hingga') ? $request->query('hingga').' 23:59:59' : null,
        ];

        // Entiti yang tidak boleh diakses tidak boleh ditapis sama sekali.
        if ($penapis['agency_code'] !== null && ! $this->access->canAccess($pengguna, $penapis['agency_code'])) {
            abort(403, 'Anda tidak mempunyai akses kepada entiti ini.');
        }

        return view('audit.index', [
            'rekod' => $this->audit->halaman($pengguna, $penapis),
            'penapis' => [
                'agency_code' => $penapis['agency_code'],
                'action' => $penapis['action'],
                'user_id' => $penapis['user_id'],
                'dari' => $request->query('dari'),
                'hingga' => $request->query('hingga'),
            ],
            'tindakan' => ActivityLog::ACTIONS,
            'entiti' => $this->access->entitiFor($pengguna),
            'pengguna' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
