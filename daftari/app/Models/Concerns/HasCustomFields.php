<?php

namespace App\Models\Concerns;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lets company admins define their own extra fields for this model (Settings
 * > Custom fields) without a schema change per field — definitions live in
 * custom_field_definitions, values in custom_field_values keyed by
 * (definition, entity_id). The model using this trait must define a
 * CUSTOM_FIELD_ENTITY_TYPE constant matching the key it registers under in
 * CustomFieldDefinition::ENTITY_TYPES.
 */
trait HasCustomFields
{
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', static::customFieldEntityType());
    }

    public static function customFieldEntityType(): string
    {
        return static::CUSTOM_FIELD_ENTITY_TYPE;
    }

    public static function customFieldDefinitions(): Collection
    {
        return CustomFieldDefinition::where('entity_type', static::customFieldEntityType())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function customFieldValuesMap(): array
    {
        return $this->customFieldValues->pluck('value', 'custom_field_definition_id')->all();
    }

    public function syncCustomFieldValues(array $values): void
    {
        $definitions = static::customFieldDefinitions()->keyBy('id');

        foreach ($values as $definitionId => $value) {
            $definition = $definitions->get((int) $definitionId);
            if (! $definition) {
                continue;
            }

            if ($value === null || $value === '') {
                CustomFieldValue::where('entity_type', static::customFieldEntityType())
                    ->where('entity_id', $this->id)
                    ->where('custom_field_definition_id', $definitionId)
                    ->delete();

                continue;
            }

            CustomFieldValue::updateOrCreate(
                [
                    'entity_type' => static::customFieldEntityType(),
                    'entity_id' => $this->id,
                    'custom_field_definition_id' => $definitionId,
                ],
                [
                    'company_id' => $this->company_id,
                    'value' => $value,
                ]
            );
        }
    }
}
