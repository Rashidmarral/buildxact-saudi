<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::orderBy('name')->paginate(20);

        return view('user.clients.index', compact('clients'));
    }

    public function create()
    {
        return view('user.clients.form', ['client' => new Client]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Client::create($data);

        return redirect()->route('app.clients.index')->with('status', __('Client saved.'));
    }

    public function edit(Client $client)
    {
        return view('user.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $this->validated($request);
        $client->update($data);

        return redirect()->route('app.clients.index')->with('status', __('Client updated.'));
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('app.clients.index')->with('status', __('Client deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'cr_number' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
