<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use EnforcesStorageQuota, ResolvesPerPage;

    public function index(Request $request)
    {
        $projects = Project::with('client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('user.projects.index', compact('projects'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.projects.form', [
            'project' => new Project(['status' => 'active', 'type' => 'construction_contract', 'billing_type' => 'milestones', 'retention_calculated_on' => 'excluding_vat']),
            'clients' => Client::orderBy('name')->get(),
            'businessLocations' => Branch::orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(),
            'nextCodePreview' => $company->project_prefix.'_'.str_pad((string) $company->next_project_number, 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $project = $company->projects()->create($data + [
            'code' => ($data['code'] ?? null) ?: $company->nextProjectCode(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('app.projects.show', $project)->with('status', __('Project created.'));
    }

    public function show(Project $project)
    {
        $project->load([
            'client', 'invoices' => fn ($q) => $q->orderByDesc('issue_date')->with('client'),
            'expenses.category',
            'quotations.client', 'purchaseOrders.supplier', 'bills.supplier',
            'projectManager', 'businessLocation', 'costCenter', 'attachments.uploader',
            'boqItems.unit', 'budgetLines.creator', 'projectCosts.creator', 'tasks.assignee',
            'timesheets.employee', 'materialIssues.item', 'machineryDeployments.machinery',
            'subcontracts.supplier', 'subcontracts.employee', 'changeOrders', 'siteDiaryEntries',
            'workOrders.technician', 'workOrders.machinery',
            'uninvoicedBillableTimesheets.employee', 'uninvoicedBillableCosts',
        ]);

        return view('user.projects.show', [
            'project' => $project,
            'units' => \App\Models\Unit::orderBy('name')->get(),
            'employees' => \App\Models\Employee::where('status', 'active')->orderBy('full_name')->get(),
            'items' => \App\Models\Item::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => \App\Models\Warehouse::orderBy('name')->get(),
            'machineryAssets' => \App\Models\MachineryAsset::orderBy('name')->get(),
            'remainingBoq' => $project->boqItems->filter(fn ($item) => $item->remainingToBill() > 0.01),
        ]);
    }

    public function edit(Project $project)
    {
        return view('user.projects.form', [
            'project' => $project,
            'clients' => Client::orderBy('name')->get(),
            'businessLocations' => Branch::orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(),
            'nextCodePreview' => null,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project->id);
        unset($data['code']);

        $project->update($data);

        return redirect()->route('app.projects.show', $project)->with('status', __('Project updated.'));
    }

    public function destroy(Project $project)
    {
        if ($project->invoices()->exists() || $project->expenses()->exists()
            || $project->quotations()->exists() || $project->purchaseOrders()->exists() || $project->bills()->exists()) {
            return back()->withErrors(['project' => __('This project has linked invoices, quotations, purchase orders, bills or expenses and cannot be deleted.')]);
        }

        $project->delete();

        return redirect()->route('app.projects.index')->with('status', __('Project deleted.'));
    }

    public function storeAttachment(Request $request, Project $project)
    {
        if ($rejected = $this->rejectIfStorageQuotaReached($project->company)) {
            return $rejected;
        }

        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('file');
        $project->attachments()->create([
            'company_id' => $project->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('project-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(Project $project, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === Project::class && $attachment->attachable_id === $project->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'code' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('projects')->where('company_id', $companyId)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(Project::TYPES)],
            'status' => ['required', 'in:active,on_hold,completed,cancelled'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'business_location_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'project_manager_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'billing_type' => ['required', Rule::in(Project::BILLING_TYPES)],
            'default_tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'cost_ceiling' => ['nullable', 'numeric', 'min:0'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'retention_calculated_on' => ['nullable', 'in:excluding_vat,including_vat'],
            'advance_recovery_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cost_center_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'site_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
