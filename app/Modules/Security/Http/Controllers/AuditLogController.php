<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return AuditLog::with('actor:id,full_name')
            ->when($request->entity_name, fn ($q, $e) => $q->where('entity_name', $e))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 30));
    }
}
