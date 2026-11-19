<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomFieldDefinition extends Model
{
    use BelongsToCompany;

    public const ENTITY_TYPES = [
        'client' => 'Clients',
        'supplier' => 'Suppliers',
        'item' => 'Items & Services',
    ];

    public const FIELD_TYPES = [
        'text' => 'Text',
        'textarea' => 'Long text',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown',
        'checkbox' => 'Checkbox',
    ];

    protected $fillable = [
        'company_id', 'entity_type', 'key', 'label', 'field_type',
        'options', 'is_required', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomFieldDefinition $definition) {
            if (empty($definition->key) && $definition->label) {
                $definition->key = static::uniqueKeyFor($definition->company_id, $definition->entity_type, $definition->label);
            }
            if (empty($definition->sort_order) && $definition->company_id && $definition->entity_type) {
                $definition->sort_order = 1 + (int) static::withoutGlobalScopes()
                    ->where('company_id', $definition->company_id)
                    ->where('entity_type', $definition->entity_type)
                    ->max('sort_order');
            }
        });
    }

    public static function uniqueKeyFor(?int $companyId, string $entityType, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base;
        $suffix = 1;

        while (static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('entity_type', $entityType)
            ->where('key', $key)
            ->exists()) {
            $suffix++;
            $key = $base.'_'.$suffix;
        }

        return $key;
    }

    public function optionsList(): array
    {
        return array_values(array_filter((array) $this->options));
    }
}
