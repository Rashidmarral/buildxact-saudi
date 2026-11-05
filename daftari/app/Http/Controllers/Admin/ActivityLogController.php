<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $logs = AuditLog::with(['admin', 'impersonatedUser'])->latest('created_at')->paginate($this->resolvePerPage($request, 30))->withQueryString();

        return view('admin.activity.index', compact('logs'));
    }
}
