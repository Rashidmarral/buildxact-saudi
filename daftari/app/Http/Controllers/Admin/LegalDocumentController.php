<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LegalDocument;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegalDocumentController extends Controller
{
    public function index()
    {
        $documents = LegalDocument::orderBy('sort_order')->get();

        return view('admin.legal-documents.index', compact('documents'));
    }

    public function edit(LegalDocument $legalDocument)
    {
        return view('admin.legal-documents.edit', ['document' => $legalDocument]);
    }

    public function update(Request $request, LegalDocument $legalDocument)
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'body_en' => ['nullable', 'string'],
            'body_ar' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
            'requires_legal_review' => ['nullable', 'boolean'],
        ]);

        $legalDocument->update([
            'title_en' => $data['title_en'],
            'title_ar' => $data['title_ar'] ?? null,
            'body_en' => RichText::sanitize($data['body_en'] ?? null, LegalDocument::MAX_BODY_LENGTH),
            'body_ar' => RichText::sanitize($data['body_ar'] ?? null, LegalDocument::MAX_BODY_LENGTH),
            'status' => $data['status'],
            'requires_legal_review' => $request->boolean('requires_legal_review'),
            'updated_by' => Auth::id(),
        ]);

        AuditLog::record('legal_documents.update', $legalDocument, __('Updated legal document: :title', ['title' => $legalDocument->title_en]));

        return redirect()->route('admin.legal-documents.index')->with('status', __('Legal document saved.'));
    }
}
