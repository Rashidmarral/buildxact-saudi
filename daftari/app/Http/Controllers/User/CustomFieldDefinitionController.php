<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CustomFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomFieldDefinitionController extends Controller
{
    public function index()
    {
        $definitions = CustomFieldDefinition::orderBy('entity_type')->orderBy('sort_order')->get()->groupBy('entity_type');

        return view('user.settings.custom-fields.index', [
            'definitionsByEntity' => $definitions,
            'entityTypes' => CustomFieldDefinition::ENTITY_TYPES,
            'fieldTypes' => CustomFieldDefinition::FIELD_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $definition = CustomFieldDefinition::create($data);

        AuditLog::record('custom_field.create', $definition, __('Added custom field ":label"', ['label' => $definition->label]));

        return back()->with('status', __('Custom field added.'));
    }

    public function update(Request $request, CustomFieldDefinition $customField)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'options' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customField->update([
            'label' => $data['label'],
            'options' => $customField->field_type === 'select' ? $this->parseOptions($data['options'] ?? '') : null,
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLog::record('custom_field.update', $customField, __('Updated custom field ":label"', ['label' => $customField->label]));

        return back()->with('status', __('Custom field updated.'));
    }

    public function destroy(CustomFieldDefinition $customField)
    {
        $label = $customField->label;
        $customField->delete();

        AuditLog::record('custom_field.delete', null, __('Deleted custom field ":label"', ['label' => $label]));

        return back()->with('status', __('Custom field deleted.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'entity_type' => ['required', Rule::in(array_keys(CustomFieldDefinition::ENTITY_TYPES))],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(array_keys(CustomFieldDefinition::FIELD_TYPES))],
            'options' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        return [
            'company_id' => $companyId,
            'entity_type' => $data['entity_type'],
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options' => $data['field_type'] === 'select' ? $this->parseOptions($data['options'] ?? '') : null,
            'is_required' => $request->boolean('is_required'),
            'is_active' => true,
        ];
    }

    private function parseOptions(string $raw): array
    {
        return collect(explode("\n", $raw))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
