<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectBoqItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectBoqItemController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $this->validated($request);

        $project->boqItems()->create($data + ['sort_order' => $project->boqItems()->count()]);

        return back()->with('status', __('BOQ item added.'));
    }

    public function update(Request $request, Project $project, ProjectBoqItem $boqItem)
    {
        abort_unless($boqItem->project_id === $project->id, 404);

        $data = $request->validate([
            'executed_quantity' => ['required', 'numeric', 'min:0'],
            'billed_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $boqItem->update($data);

        return back()->with('status', __('BOQ item updated.'));
    }

    public function destroy(Project $project, ProjectBoqItem $boqItem)
    {
        abort_unless($boqItem->project_id === $project->id, 404);

        $boqItem->delete();

        return back()->with('status', __('BOQ item removed.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'item_no' => ['nullable', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:500'],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'cost_rate' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
