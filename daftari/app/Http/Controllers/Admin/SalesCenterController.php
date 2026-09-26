<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesTarget;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "How do we get sales for real now?" — a working plan, not another
 * strategy document. Turns the 90-day acquisition plan the operator
 * asked for into a weekly goal tracker (SalesTarget, editable) laid
 * against real numbers pulled live from the existing CRM/Partner/Payment
 * data, plus a Sales Tools tab of ready-to-use outreach scripts. Every
 * "actual" figure here is computed on the fly rather than stored, so the
 * plan can never drift out of sync with the real pipeline.
 */
class SalesCenterController extends Controller
{
    public function plan()
    {
        $planStart = $this->planStart();
        $targets = SalesTarget::orderBy('week_number')->get();

        $today = Carbon::today();
        $currentWeek = $planStart->isFuture()
            ? 1
            : min(SalesTarget::WEEKS, (int) floor($planStart->diffInDays($today) / 7) + 1);

        $rows = $targets->map(function (SalesTarget $target) use ($planStart) {
            $weekStart = $planStart->copy()->addDays(7 * ($target->week_number - 1));
            $weekEnd = $weekStart->copy()->addDays(6);

            return [
                'target' => $target,
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'new_leads_actual' => Lead::whereBetween('created_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])->count(),
                'demos_actual' => Lead::whereNotNull('demo_at')->whereBetween('demo_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])->count(),
                // "Won" per week is approximated from each lead's last
                // status update, since Lead doesn't record a separate
                // won-at timestamp — a directional signal, not an
                // accounting-grade figure.
                'won_actual' => Lead::whereIn('status', Lead::WON_STAGES)->whereBetween('updated_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])->count(),
            ];
        });

        $openStages = array_diff(Lead::STAGES, ['paid', 'active', 'renewal', 'lost']);
        $stats = [
            'open_pipeline' => Lead::whereIn('status', $openStages)->count(),
            'new_leads_this_month' => Lead::where('created_at', '>=', now()->startOfMonth())->count(),
            'won_all_time' => Lead::whereIn('status', Lead::WON_STAGES)->count(),
            'active_partners' => Partner::where('status', 'active')->count(),
            'revenue_this_month' => Payment::withoutGlobalScopes()
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        return view('admin.sales-center.plan', [
            'rows' => $rows,
            'planStart' => $planStart,
            'currentWeek' => $currentWeek,
            'stats' => $stats,
        ]);
    }

    public function updateTargets(Request $request)
    {
        $data = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.new_leads_target' => ['required', 'integer', 'min:0', 'max:100000'],
            'targets.*.demos_target' => ['required', 'integer', 'min:0', 'max:100000'],
            'targets.*.won_target' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        foreach ($data['targets'] as $weekNumber => $values) {
            SalesTarget::where('week_number', (int) $weekNumber)->update($values);
        }

        AuditLog::record('sales_center.targets_updated', null, __('Updated the 90-day sales plan targets.'));

        return back()->with('status', __('Plan targets saved.'));
    }

    public function restart(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
        ]);

        Setting::set('sales_plan_start_date', $request->date('start_date')->toDateString());

        AuditLog::record('sales_center.plan_restarted', null, __('Restarted the 90-day sales plan from :date.', ['date' => $request->date('start_date')->toDateString()]));

        return back()->with('status', __('Plan restarted.'));
    }

    public function tools()
    {
        return view('admin.sales-center.tools');
    }

    /**
     * The plan's week-1 start date. Set once, automatically, the first
     * time this page is ever viewed (so weekly windows are stable and
     * don't shift on every page load) — an admin can move it later via
     * "Restart plan".
     */
    private function planStart(): Carbon
    {
        $stored = Setting::get('sales_plan_start_date');

        if (! $stored) {
            $stored = now()->toDateString();
            Setting::set('sales_plan_start_date', $stored);
        }

        return Carbon::parse($stored)->startOfDay();
    }
}
