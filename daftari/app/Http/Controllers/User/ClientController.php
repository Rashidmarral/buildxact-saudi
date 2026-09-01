<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ImportsCsv;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Webhook;
use App\Rules\SaudiCrNumber;
use App\Rules\SaudiPhoneNumber;
use App\Rules\SaudiVatNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    use ExportsCsv, ImportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $query = Client::orderBy('name');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse(
                'clients.csv',
                [__('Code'), __('Name'), __('Type'), __('VAT number'), __('Email'), __('Phone')],
                $query->get()->map(fn ($client) => [
                    $client->client_code,
                    $client->name,
                    $client->type === 'individual' ? __('Individual') : __('Company'),
                    $client->vat_number,
                    $client->email,
                    $client->phone,
                ])
            );
        }

        $clients = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.clients.index', compact('clients'));
    }

    public function outstandingInvoices(Client $client)
    {
        $invoices = $client->invoices()
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->get()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'balance' => number_format($invoice->balanceDue(), 2),
            ])
            ->values();

        return response()->json($invoices);
    }

    public function create()
    {
        $client = new Client(['client_code' => Client::generateClientCode(Auth::user()->company_id)]);

        return view('user.clients.form', [
            'client' => $client,
            'customFieldDefinitions' => Client::customFieldDefinitions(),
            'customFieldValues' => [],
        ]);
    }

    public function store(Request $request)
    {
        if (Auth::user()->company->hasReachedPlanLimit('customers')) {
            return back()->withErrors(['plan_limit' => __('You have reached your plan\'s customer limit. Upgrade your plan to add more customers.')])->withInput();
        }

        $data = $this->validated($request);
        $contacts = $data['contacts'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        unset($data['contacts'], $data['custom_fields']);

        $client = DB::transaction(function () use ($data, $contacts, $customFields) {
            $client = Client::create($data);
            $this->syncContacts($client, $contacts);
            $client->syncCustomFieldValues($customFields);

            return $client;
        });

        AuditLog::record('client.create', $client, __('Created client :name', ['name' => $client->name]));

        Webhook::trigger($client->company_id, 'client.created', [
            'id' => $client->id,
            'client_code' => $client->client_code,
            'name' => $client->name,
            'email' => $client->email,
        ]);

        return redirect()->route('app.clients.index')->with('status', __('Client saved.'));
    }

    public function edit(Client $client)
    {
        $client->load(['contacts', 'customFieldValues']);

        return view('user.clients.form', [
            'client' => $client,
            'customFieldDefinitions' => Client::customFieldDefinitions(),
            'customFieldValues' => $client->customFieldValuesMap(),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $data = $this->validated($request, $client);
        $contacts = $data['contacts'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        unset($data['contacts'], $data['custom_fields']);

        DB::transaction(function () use ($client, $data, $contacts, $customFields) {
            $client->update($data);
            $client->contacts()->delete();
            $this->syncContacts($client, $contacts);
            $client->syncCustomFieldValues($customFields);
        });

        AuditLog::record('client.update', $client, __('Updated client :name', ['name' => $client->name]));

        return redirect()->route('app.clients.index')->with('status', __('Client updated.'));
    }

    public function destroy(Client $client)
    {
        AuditLog::record('client.delete', $client, __('Deleted client :name', ['name' => $client->name]));

        $client->delete();

        return redirect()->route('app.clients.index')->with('status', __('Client deleted.'));
    }

    public function showImport()
    {
        return view('user.clients.import');
    }

    public function importTemplate()
    {
        $csv = "name,type,email,phone,vat_number,cr_number,city,notes\n"
            .'"Al Rashid Trading Co.",company,billing@example.com,0512345678,300012345600003,1010123456,Riyadh,"Preferred customer"'."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="clients-template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $company = Auth::user()->company;
        $companyId = $company->id;

        $plan = $company->activeSubscription()?->plan;
        $maxRows = $plan && $plan->max_customers !== null
            ? max(0, $plan->max_customers - $company->clients()->count())
            : null;

        $result = $this->runCsvImport(
            $request->file('file'),
            function (array $row) {
                return Validator::make([
                    'type' => strtolower($row['type'] ?? '') === 'individual' ? 'individual' : 'company',
                    'name' => $row['name'] ?: null,
                    'email' => $row['email'] ?: null,
                    'phone' => $row['phone'] ?: null,
                    'vat_number' => $row['vat_number'] ?: null,
                    'cr_number' => $row['cr_number'] ?: null,
                    'city' => $row['city'] ?: null,
                    'notes' => $row['notes'] ?: null,
                ], [
                    'name' => ['required', 'string', 'max:255'],
                    'type' => ['required', 'in:individual,company'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', new SaudiPhoneNumber],
                    'vat_number' => ['nullable', 'string', new SaudiVatNumber],
                    'cr_number' => ['nullable', 'string', new SaudiCrNumber],
                    'city' => ['nullable', 'string', 'max:100'],
                    'notes' => ['nullable', 'string', 'max:2000'],
                ])->validate();
            },
            function (array $data) use ($companyId) {
                Client::create($data + ['company_id' => $companyId]);
            },
            $maxRows
        );

        AuditLog::record('client.import', null, __(':count client(s) imported via CSV', ['count' => $result['imported']]));

        return redirect()->route('app.clients.import')->with('import_result', $result);
    }

    private function syncContacts(Client $client, array $contacts): void
    {
        foreach ($contacts as $contact) {
            if (empty($contact['name'])) {
                continue;
            }
            $client->contacts()->create([
                'name' => $contact['name'],
                'phone' => $contact['phone'] ?? null,
                'email' => $contact['email'] ?? null,
            ]);
        }
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        $data = $request->validate([
            'client_code' => [
                'required', 'string', 'max:20',
                Rule::unique('clients', 'client_code')
                    ->where('company_id', Auth::user()->company_id)
                    ->ignore($client?->id),
            ],
            'type' => ['required', 'in:individual,company'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'is_vat_registered' => ['nullable', 'boolean'],
            'vat_number' => ['nullable', 'string', new SaudiVatNumber],
            'cr_number' => ['nullable', 'string', new SaudiCrNumber],
            'additional_id_type' => ['nullable', 'in:national_id,iqama,passport,gcc_id'],
            'additional_id_number' => ['nullable', 'string', 'max:50'],
            'initial_balance' => ['nullable', 'numeric'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', new SaudiPhoneNumber],
            'mobile' => ['nullable', 'string', new SaudiPhoneNumber],
            'street_name' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:20'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_vat_registered'] = $request->boolean('is_vat_registered');

        $this->validateRequiredCustomFields(Client::customFieldDefinitions(), $data['custom_fields'] ?? []);

        return $data;
    }

    private function validateRequiredCustomFields($definitions, array $submitted): void
    {
        $errors = [];
        foreach ($definitions as $definition) {
            if (! $definition->is_required) {
                continue;
            }
            $value = $submitted[$definition->id] ?? null;
            if ($value === null || $value === '') {
                $errors["custom_fields.{$definition->id}"] = __(':field is required.', ['field' => $definition->label]);
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }
}
