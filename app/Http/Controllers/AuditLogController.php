<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogFilterRequest;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogQueryService;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request, AuditLogQueryService $queryService): View
    {
        $filters = $queryService->normalizeFilters($request->validated());

        return view('audit-logs.index', [
            'auditLogs' => $queryService->paginated($filters),
            'users' => $queryService->users(),
            'modules' => $queryService->modules(),
            'events' => $queryService->events(),
            'filters' => $filters,
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('audit-logs.show', [
            'auditLog' => $auditLog->loadMissing('user'),
        ]);
    }
}
