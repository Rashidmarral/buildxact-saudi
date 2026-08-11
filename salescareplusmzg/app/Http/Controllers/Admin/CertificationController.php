<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function index()
    {
        $certifications = Certification::orderBy('sort_order')->get();

        return view('admin.certifications.index', compact('certifications'));
    }

    public function create()
    {
        return view('admin.certifications.form', ['certification' => new Certification]);
    }

    public function store(Request $request)
    {
        Certification::create($this->validated($request));

        return redirect()->route('admin.certifications.index')->with('status', 'Certification created.');
    }

    public function edit(Certification $certification)
    {
        return view('admin.certifications.form', compact('certification'));
    }

    public function update(Request $request, Certification $certification)
    {
        $certification->update($this->validated($request));

        return redirect()->route('admin.certifications.index')->with('status', 'Certification updated.');
    }

    public function destroy(Certification $certification)
    {
        $certification->delete();

        return redirect()->route('admin.certifications.index')->with('status', 'Certification deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'issuing_body' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }
}
