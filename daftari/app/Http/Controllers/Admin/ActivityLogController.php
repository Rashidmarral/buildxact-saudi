<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::with(['admin', 'impersonatedUser'])->latest('created_at')->paginate(30);

        return view('admin.activity.index', compact('logs'));
    }
}
