<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Principal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrincipalController extends Controller
{
    use HandlesUploads;

    public function index()
    {
        $principals = Principal::orderBy('sort_order')->get();

        return view('admin.principals.index', compact('principals'));
    }

    public function create()
    {
        return view('admin.principals.form', ['principal' => new Principal]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['slug'] = Str::slug($request->name);
        $validated['logo_path'] = $this->storeUpload($request, 'logo', 'principals');

        Principal::create($validated);

        return redirect()->route('admin.principals.index')->with('status', 'Principal created.');
    }

    public function edit(Principal $principal)
    {
        return view('admin.principals.form', compact('principal'));
    }

    public function update(Request $request, Principal $principal)
    {
        $validated = $this->validated($request);
        $validated['slug'] = Str::slug($request->name);
        $validated['logo_path'] = $this->storeUpload($request, 'logo', 'principals', $principal->logo_path);

        $principal->update($validated);

        return redirect()->route('admin.principals.index')->with('status', 'Principal updated.');
    }

    public function destroy(Principal $principal)
    {
        $principal->delete();

        return redirect()->route('admin.principals.index')->with('status', 'Principal deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'initials' => ['required', 'string', 'max:5'],
            'tagline' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'years_partnership' => ['required', 'integer', 'min:0'],
            'products_count' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }
}
