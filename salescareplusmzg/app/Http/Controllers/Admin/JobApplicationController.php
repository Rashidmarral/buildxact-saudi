<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationController extends Controller
{
    public function index()
    {
        $applications = JobApplication::latest()->paginate(20);

        return view('admin.job-applications.index', compact('applications'));
    }

    public function show(JobApplication $jobApplication)
    {
        if (! $jobApplication->is_read) {
            $jobApplication->update(['is_read' => true]);
        }

        return view('admin.job-applications.show', ['application' => $jobApplication]);
    }

    public function resume(JobApplication $jobApplication): StreamedResponse
    {
        abort_unless($jobApplication->resume_path && Storage::exists($jobApplication->resume_path), 404);

        return Storage::download($jobApplication->resume_path, $jobApplication->name.' - '.$jobApplication->job_title.'.'.pathinfo($jobApplication->resume_path, PATHINFO_EXTENSION));
    }

    public function destroy(JobApplication $jobApplication)
    {
        if ($jobApplication->resume_path) {
            Storage::delete($jobApplication->resume_path);
        }

        $jobApplication->delete();

        return redirect()->route('admin.job-applications.index')->with('status', 'Application deleted.');
    }
}
