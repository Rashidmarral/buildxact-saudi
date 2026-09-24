<?php

namespace App\Http\Controllers\User\RepairShop;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Item;
use App\Models\RepairJob;
use App\Models\SmsConfig;
use App\Services\Accounting\LedgerPostingService;
use App\Services\RepairShop\RepairJobService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

class RepairJobController extends Controller
{
    public function index()
    {
        return view('user.repair-jobs.index', [
            'openJobs' => RepairJob::whereNotIn('status', ['collected', 'cancelled'])->latest()->get(),
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, RepairJobService $service)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'item_description' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'string', 'max:10'],
            'serial_or_plate' => ['nullable', 'string', 'max:60'],
            'issue_description' => ['nullable', 'string', 'max:2000'],
        ]);

        $job = $service->createJob(Auth::user()->company, $data);

        AuditLog::record('repair_job.create', $job, __('Opened job :number', ['number' => $job->job_number]));

        return redirect()->route('app.repair-jobs.show', $job);
    }

    public function show(RepairJob $job)
    {
        $job->load(['items.item', 'client', 'sale']);

        return view('user.repair-jobs.show', compact('job'));
    }

    public function lookupItem(Request $request)
    {
        $query = trim((string) $request->query('q'));

        $items = Item::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('compatibility_notes', 'like', "%{$query}%");
            })
            ->take(15)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit_price' => (float) $item->unit_price,
                'vat_rate' => (float) $item->vat_rate,
                'compatibility_notes' => $item->compatibility_notes,
            ])
            ->values();

        return response()->json($items);
    }

    public function storeItems(Request $request, RepairJob $job, RepairJobService $service)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.core_exchange_credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->addItems($job, $data['lines']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return back()->with('status', __('Line added.'));
    }

    public function updateDiagnosis(Request $request, RepairJob $job, RepairJobService $service)
    {
        $data = $request->validate(['diagnosis_notes' => ['nullable', 'string', 'max:2000']]);

        try {
            $service->updateDiagnosis($job, $data['diagnosis_notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        return back()->with('status', __('Diagnosis saved.'));
    }

    public function approve(RepairJob $job, RepairJobService $service)
    {
        try {
            $service->approveEstimate($job);
        } catch (RuntimeException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        AuditLog::record('repair_job.approve', $job, __('Approved estimate for job :number', ['number' => $job->job_number]));

        return back()->with('status', __('Estimate approved.'));
    }

    public function updateStatus(Request $request, RepairJob $job, RepairJobService $service)
    {
        $data = $request->validate([
            'status' => ['required', 'in:received,diagnosing,awaiting_approval,in_repair,ready,collected'],
        ]);

        try {
            $service->updateStatus($job, $data['status']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        return back()->with('status', __('Status updated.'));
    }

    public function checkout(Request $request, RepairJob $job, RepairJobService $service, LedgerPostingService $ledger)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'register_id' => ['nullable', Rule::exists('pos_registers', 'id')->where('company_id', $companyId)],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $sale = $service->checkout($job, $data['payments'], $ledger, $data['register_id'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        AuditLog::record('repair_job.checkout', $job, __('Checked out job :number', ['number' => $job->job_number]));

        return redirect()->route('app.pos.sales.show', $sale)->with('status', __('Job completed.'));
    }

    public function cancel(Request $request, RepairJob $job, RepairJobService $service)
    {
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:255']]);

        try {
            $service->cancelJob($job, $data['cancel_reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['job' => $e->getMessage()]);
        }

        AuditLog::record('repair_job.cancel', $job, __('Cancelled job :number', ['number' => $job->job_number]));

        return redirect()->route('app.repair-jobs.index')->with('status', __('Job cancelled.'));
    }

    /**
     * Free-text SMS rather than a WhatsApp template message — a "ready for
     * pickup" notice doesn't fit the fixed positional-parameter shape a
     * Meta-approved WhatsApp template requires, and SMS has no such
     * approval step (see InvoiceController::sendSms(), the same pattern).
     */
    public function notifySms(RepairJob $job, SmsService $sms)
    {
        $config = SmsConfig::first();
        abort_unless($config && $config->is_enabled, 404);

        $phone = $job->client?->mobile ?: $job->client?->phone;

        if (! $phone) {
            return back()->withErrors(['job' => __('This client has no phone number on file. Add one on the client record first.')]);
        }

        $message = __(':company: your :item is ready for pickup. Job :number.', [
            'company' => $job->company->name,
            'item' => $job->item_description,
            'number' => $job->job_number,
        ]);

        $result = $sms->send($config, $phone, $message);

        if (! $result['success']) {
            return back()->withErrors(['job' => __('SMS send failed: :error', ['error' => $result['error']])]);
        }

        AuditLog::record('repair_job.sms_sent', $job, __('Sent "ready for pickup" SMS for job :number', ['number' => $job->job_number]));

        return back()->with('status', __('SMS sent.'));
    }
}
