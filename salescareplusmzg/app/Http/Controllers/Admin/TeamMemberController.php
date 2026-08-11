<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    use HandlesUploads;

    public function index()
    {
        $teamMembers = TeamMember::orderBy('sort_order')->get();

        return view('admin.team-members.index', compact('teamMembers'));
    }

    public function create()
    {
        return view('admin.team-members.form', ['teamMember' => new TeamMember]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['photo_path'] = $this->storeUpload($request, 'photo', 'team');

        TeamMember::create($validated);

        return redirect()->route('admin.team-members.index')->with('status', 'Team member created.');
    }

    public function edit(TeamMember $teamMember)
    {
        return view('admin.team-members.form', compact('teamMember'));
    }

    public function update(Request $request, TeamMember $teamMember)
    {
        $validated = $this->validated($request);
        $validated['photo_path'] = $this->storeUpload($request, 'photo', 'team', $teamMember->photo_path);

        $teamMember->update($validated);

        return redirect()->route('admin.team-members.index')->with('status', 'Team member updated.');
    }

    public function destroy(TeamMember $teamMember)
    {
        $teamMember->delete();

        return redirect()->route('admin.team-members.index')->with('status', 'Team member deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['required', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'initials' => ['required', 'string', 'max:5'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }
}
