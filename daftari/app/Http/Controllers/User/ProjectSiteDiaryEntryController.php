<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectSiteDiaryEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectSiteDiaryEntryController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'weather' => ['nullable', 'string', 'max:100'],
            'manpower_count' => ['nullable', 'integer', 'min:0'],
            'work_done' => ['nullable', 'string', 'max:2000'],
            'materials_received' => ['nullable', 'string', 'max:2000'],
            'issues_delays' => ['nullable', 'string', 'max:2000'],
        ]);

        $project->siteDiaryEntries()->create($data + ['created_by' => Auth::id()]);

        return back()->with('status', __('Site diary entry added.'));
    }

    public function destroy(Project $project, ProjectSiteDiaryEntry $siteDiaryEntry)
    {
        abort_unless($siteDiaryEntry->project_id === $project->id, 404);

        $siteDiaryEntry->delete();

        return back()->with('status', __('Site diary entry removed.'));
    }
}
