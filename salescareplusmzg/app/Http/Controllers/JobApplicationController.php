<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsAgainstSpam;
use App\Models\ContentItem;
use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobApplicationController extends Controller
{
    use GuardsAgainstSpam;

    public function create(Request $request)
    {
        $job = null;

        if ($request->query('job')) {
            $job = ContentItem::group('job_opening')->find($request->query('job'));
        }

        return view('pages.careers-apply', compact('job'));
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->isSpamSubmission($request)) {
            return redirect()
                ->route('careers')
                ->with('status', 'Thank you for applying! Our team will review your application and get in touch if there\'s a match.');
        }

        $validated = $request->validate([
            'job_title' => ['required', 'string', 'max:150'],
            'content_item_id' => ['nullable', 'integer', 'exists:content_items,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cover_message' => ['nullable', 'string', 'max:3000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        if ($request->hasFile('resume')) {
            // Stored on the private 'local' disk (not 'public') since resumes contain
            // personal data — only downloadable by an authenticated admin, never by URL.
            $validated['resume_path'] = $request->file('resume')->store('resumes');
        }

        unset($validated['resume']);

        JobApplication::create($validated);

        return redirect()
            ->route('careers')
            ->with('status', 'Thank you for applying! Our team will review your application and get in touch if there\'s a match.');
    }
}
