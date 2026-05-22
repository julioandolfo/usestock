<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $q = AuditLog::with('user:id,name,email')->latest();

        if ($action = $request->string('action')->toString()) {
            $q->where('action', $action);
        }

        return Inertia::render('admin/audit/index', [
            'logs' => $q->paginate(40)->withQueryString(),
            'filters' => $request->only(['action']),
        ]);
    }
}
