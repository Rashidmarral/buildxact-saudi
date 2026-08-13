<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::orderBy('name')->paginate(20);

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

        return view('user.clients.form', compact('client'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $contacts = $data['contacts'] ?? [];
        unset($data['contacts']);

        DB::transaction(function () use ($data, $contacts) {
            $client = Client::create($data);
            $this->syncContacts($client, $contacts);
        });

        return redirect()->route('app.clients.index')->with('status', __('Client saved.'));
    }

    public function edit(Client $client)
    {
        $client->load('contacts');

        return view('user.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $this->validated($request, $client);
        $contacts = $data['contacts'] ?? [];
        unset($data['contacts']);

        DB::transaction(function () use ($client, $data, $contacts) {
            $client->update($data);
            $client->contacts()->delete();
            $this->syncContacts($client, $contacts);
        });

        return redirect()->route('app.clients.index')->with('status', __('Client updated.'));
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('app.clients.index')->with('status', __('Client deleted.'));
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
            'vat_number' => ['nullable', 'string', 'max:32'],
            'cr_number' => ['nullable', 'string', 'max:32'],
            'additional_id_type' => ['nullable', 'in:national_id,iqama,passport,gcc_id'],
            'additional_id_number' => ['nullable', 'string', 'max:50'],
            'initial_balance' => ['nullable', 'numeric'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
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
        ]);

        $data['is_vat_registered'] = $request->boolean('is_vat_registered');

        return $data;
    }
}
