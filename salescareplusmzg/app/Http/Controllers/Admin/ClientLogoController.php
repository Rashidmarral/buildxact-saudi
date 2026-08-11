<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\ClientLogo;
use Illuminate\Http\Request;

class ClientLogoController extends Controller
{
    use HandlesUploads;

    public function index()
    {
        $clientLogos = ClientLogo::orderBy('sort_order')->get();

        return view('admin.client-logos.index', compact('clientLogos'));
    }

    public function create()
    {
        return view('admin.client-logos.form', ['clientLogo' => new ClientLogo]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['logo_path'] = $this->storeUpload($request, 'logo', 'clients');

        ClientLogo::create($validated);

        return redirect()->route('admin.client-logos.index')->with('status', 'Client added.');
    }

    public function edit(ClientLogo $clientLogo)
    {
        return view('admin.client-logos.form', compact('clientLogo'));
    }

    public function update(Request $request, ClientLogo $clientLogo)
    {
        $validated = $this->validated($request);
        $validated['logo_path'] = $this->storeUpload($request, 'logo', 'clients', $clientLogo->logo_path);

        $clientLogo->update($validated);

        return redirect()->route('admin.client-logos.index')->with('status', 'Client updated.');
    }

    public function destroy(ClientLogo $clientLogo)
    {
        $clientLogo->delete();

        return redirect()->route('admin.client-logos.index')->with('status', 'Client removed.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'website_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_visible'] = $request->boolean('is_visible');

        return $validated;
    }
}
