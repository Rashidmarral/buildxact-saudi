<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $logs = AuditLog::forCompany(Auth::user()->company_id)
            ->with('admin')
            ->latest('created_at')
            ->paginate($this->resolvePerPage($request, 30))
            ->withQueryString();

        return view('user.activity.index', compact('logs'));
    }
}
