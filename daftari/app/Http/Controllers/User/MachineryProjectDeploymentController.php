<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\MachineryAsset;
use App\Models\MachineryProjectDeployment;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A machine used on the company's OWN project — no client, no invoice.
 * See the migration's docblock for why internal_daily_rate never feeds
 * Project::costs()/cashPaid(); the real cash cost of running the machine
 * is whatever fuel/maintenance/operator-wage Expense rows are tagged to
 * this project (see Expense.machinery_asset_id).
 */
class MachineryProjectDeploymentController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $deployments = MachineryProjectDeployment::with('machinery', 'project')->orderByDesc('start_date')->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.machinery.deployments.index', compact('deployments'));
    }

    public function create(Request $request)
    {
        return view('user.machinery.deployments.form', [
            'deployment' => new MachineryProjectDeployment(['start_date' => now()->toDateString()]),
            'machinery' => MachineryAsset::where('status', 'available')->orderBy('name')->get(),
            'selectedMachineryId' => $request->integer('machinery_asset_id') ?: null,
            'projects' => Project::orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'machinery_asset_id' => ['required', Rule::exists('machinery_assets', 'id')->where('company_id', $companyId)],
            'project_id' => ['required', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'internal_daily_rate' => ['nullable', 'numeric', 'min:0'],
            'operator_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $machinery = MachineryAsset::findOrFail($data['machinery_asset_id']);
        abort_unless($machinery->status === 'available', 422, __('This machine is not available to deploy.'));

        $deployment = DB::transaction(function () use ($data, $machinery) {
            $deployment = MachineryProjectDeployment::create($data + [
                'company_id' => $machinery->company_id,
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            $machinery->update(['status' => 'deployed']);

            return $deployment;
        });

        AuditLog::record('machinery.deployment.create', $deployment, __('Deployed :code on :project', ['code' => $machinery->asset_code, 'project' => $deployment->project->name]));

        return redirect()->route('app.machinery.deployments.index')->with('status', __('Machine deployed on project.'));
    }

    public function end(Request $request, MachineryProjectDeployment $deployment)
    {
        abort_unless($deployment->status === 'active', 404);

        $data = $request->validate(['end_date' => ['required', 'date']]);

        DB::transaction(function () use ($deployment, $data) {
            $deployment->update(['status' => 'completed', 'end_date' => $data['end_date']]);
            $deployment->machinery->update(['status' => 'available']);
        });

        AuditLog::record('machinery.deployment.end', $deployment, __('Ended deployment of :code', ['code' => $deployment->machinery->asset_code]));

        return redirect()->route('app.machinery.deployments.index')->with('status', __('Deployment ended — machine is available again.'));
    }
}
