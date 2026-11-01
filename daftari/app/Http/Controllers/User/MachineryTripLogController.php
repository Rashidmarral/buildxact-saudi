<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\MachineryAsset;
use App\Models\MachineryTripLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MachineryTripLogController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $trips = MachineryTripLog::with('machinery', 'operator', 'project')
            ->orderByDesc('trip_date')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('user.machinery.trips.index', compact('trips'));
    }

    public function create(Request $request)
    {
        return view('user.machinery.trips.form', [
            'machinery' => MachineryAsset::orderBy('name')->get(),
            'selectedMachineryId' => $request->integer('machinery_asset_id') ?: null,
            'operators' => Employee::where('is_operator', true)->where('status', 'active')->orderBy('full_name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'machinery_asset_id' => ['required', Rule::exists('machinery_assets', 'id')->where('company_id', $companyId)],
            'operator_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'trip_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:255'],
            'from_location' => ['nullable', 'string', 'max:255'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $machinery = MachineryAsset::findOrFail($data['machinery_asset_id']);

        $trip = MachineryTripLog::create($data + [
            'company_id' => $machinery->company_id,
            'created_by' => Auth::id(),
        ]);

        AuditLog::record('machinery.trip.create', $trip, __('Trip logged for :code', ['code' => $machinery->asset_code]));

        return redirect()->route('app.machinery.trips.index')->with('status', __('Trip logged.'));
    }
}
