<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Client;

class ClientController extends Controller
{
    public function index(): void
    {
        $clients = Client::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/clients/index', ['pageTitle' => 'Clients', 'clients' => $clients], 'layouts/app');
    }

    public function create(): void
    {
        $this->view('user/clients/form', ['pageTitle' => 'New Client', 'client' => null], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Client name is required.');
            self::redirect('/app/clients/create');
        }
        Client::create([
            'company_id' => Auth::companyId(),
            'name' => $name,
            'email' => $this->input('email', ''),
            'phone' => $this->input('phone', ''),
            'address' => $this->input('address', ''),
        ]);
        $this->flash('success', 'Client added.');
        self::redirect('/app/clients');
    }

    public function edit(string $id): void
    {
        $client = $this->findOwned((int) $id);
        $this->view('user/clients/form', ['pageTitle' => 'Edit Client', 'client' => $client], 'layouts/app');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $client = $this->findOwned((int) $id);
        Client::update($client['id'], [
            'name' => trim((string) $this->input('name')),
            'email' => $this->input('email', ''),
            'phone' => $this->input('phone', ''),
            'address' => $this->input('address', ''),
        ]);
        $this->flash('success', 'Client updated.');
        self::redirect('/app/clients');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $client = $this->findOwned((int) $id);
        Client::delete($client['id']);
        $this->flash('success', 'Client removed.');
        self::redirect('/app/clients');
    }

    private function findOwned(int $id): array
    {
        $client = Client::find($id);
        if (!$client || (int) $client['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Client not found.');
        }
        return $client;
    }
}
