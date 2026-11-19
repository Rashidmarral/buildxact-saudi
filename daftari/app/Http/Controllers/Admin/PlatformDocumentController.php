<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlatformDocumentController extends Controller
{
    public function index()
    {
        return view('admin.certificates.index', [
            'documents' => PlatformDocument::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $file = $request->file('file');

        PlatformDocument::create([
            'title' => $data['title'],
            'title_ar' => $data['title_ar'] ?? null,
            'description' => $data['description'] ?? null,
            'file_path' => $file->store('platform-documents', 'public'),
            'mime_type' => $file->getMimeType(),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        AuditLog::record('platform_document.create', null, __('Uploaded certificate: :title', ['title' => $data['title']]));

        return back()->with('status', __('Certificate uploaded.'));
    }

    public function destroy(PlatformDocument $certificate)
    {
        Storage::disk('public')->delete($certificate->file_path);

        AuditLog::record('platform_document.delete', null, __('Deleted certificate: :title', ['title' => $certificate->title]));

        $certificate->delete();

        return back()->with('status', __('Certificate deleted.'));
    }
}
