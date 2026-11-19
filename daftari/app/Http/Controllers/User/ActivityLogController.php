<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    use ExportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $filtered = AuditLog::forCompany($companyId)
            ->with('admin')
            ->when($request->filled('user_id'), fn ($q) => $q->where('admin_user_id', $request->query('user_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->latest('created_at');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('activity-log.csv',
                [__('When'), __('User'), __('Action'), __('Details')],
                $filtered->get()->map(fn (AuditLog $log) => [
                    $log->created_at->format('Y-m-d H:i'), $log->admin?->name ?? __('System'), $log->action, $log->description ?? '',
                ]));
        }

        $logs = $filtered->paginate($this->resolvePerPage($request, 30))->withQueryString();

        $users = User::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']);
        $actions = AuditLog::forCompany($companyId)->distinct()->orderBy('action')->pluck('action');

        return view('user.activity.index', compact('logs', 'users', 'actions'));
    }
}
