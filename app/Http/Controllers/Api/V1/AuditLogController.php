<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        $logs = AuditLog::query()
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', '%'.$action.'%'))
            ->when($request->query('user_id'), fn ($q, $user) => $q->where('user_id', $user))
            ->when($request->query('entity_type'), fn ($q, $type) => $q->where('entity_type', $type))
            ->when($request->query('entity_id'), fn ($q, $id) => $q->where('entity_id', (string) $id))
            ->when($request->query('date_from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('date_to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
