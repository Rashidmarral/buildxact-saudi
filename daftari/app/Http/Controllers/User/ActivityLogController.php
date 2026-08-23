<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::where('company_id', Auth::user()->company_id)
            ->with('admin')
            ->latest('created_at')
            ->paginate(30);

        return view('user.activity.index', compact('logs'));
    }
}
