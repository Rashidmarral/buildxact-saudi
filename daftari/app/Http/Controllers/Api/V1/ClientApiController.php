<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Rules\SaudiCrNumber;
use App\Rules\SaudiPhoneNumber;
use App\Rules\SaudiVatNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientApiController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::orderBy('name')->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($clients->through(fn (Client $client) => $this->transform($client)));
    }

    public function show(Client $client)
    {
        return response()->json($this->transform($client));
    }

    public function store(Request $request)
    {
        if (Auth::user()->company->hasReachedPlanLimit('customers')) {
            return response()->json([
                'message' => __('You have reached your plan\'s customer limit. Upgrade your plan to add more customers.'),
            ], 422);
        }

        $data = $request->validate([
            'type' => ['required', 'in:individual,company'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'is_vat_registered' => ['nullable', 'boolean'],
            'vat_number' => ['nullable', 'string', new SaudiVatNumber],
            'cr_number' => ['nullable', 'string', new SaudiCrNumber],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', new SaudiPhoneNumber],
            'mobile' => ['nullable', 'string', new SaudiPhoneNumber],
            'street_name' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:20'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_vat_registered'] = $request->boolean('is_vat_registered');
        $data['client_code'] = Client::generateClientCode(Auth::user()->company_id);

        $client = Client::create($data);

        AuditLog::record('client.create', $client, __('Created client :name', ['name' => $client->name]));

        return response()->json($this->transform($client), 201);
    }

    private function transform(Client $client): array
    {
        return [
            'id' => $client->id,
            'client_code' => $client->client_code,
            'type' => $client->type,
            'name' => $client->name,
            'name_ar' => $client->name_ar,
            'is_vat_registered' => $client->is_vat_registered,
            'vat_number' => $client->vat_number,
            'cr_number' => $client->cr_number,
            'email' => $client->email,
            'phone' => $client->phone,
            'mobile' => $client->mobile,
            'city' => $client->city,
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
        ];
    }
}
