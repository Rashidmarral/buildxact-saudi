@if ($customFieldDefinitions->isNotEmpty())
<div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    <h3 class="font-semibold text-slate-900">{{ __('Custom fields') }}</h3>
    @foreach ($customFieldDefinitions as $definition)
        @php($value = old("custom_fields.{$definition->id}", $customFieldValues[$definition->id] ?? null))
        <div>
            <label class="block text-sm font-medium text-slate-700">
                {{ $definition->label }}
                @if ($definition->is_required)<span class="text-red-500">*</span>@endif
            </label>
            @if ($definition->field_type === 'textarea')
                <textarea name="custom_fields[{{ $definition->id }}]" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ $value }}</textarea>
            @elseif ($definition->field_type === 'select')
                <select name="custom_fields[{{ $definition->id }}]" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach ($definition->optionsList() as $option)
                        <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @elseif ($definition->field_type === 'checkbox')
                <input type="hidden" name="custom_fields[{{ $definition->id }}]" value="0">
                <label class="mt-1 flex items-center">
                    <input type="checkbox" name="custom_fields[{{ $definition->id }}]" value="1" @checked($value == '1') class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                </label>
            @elseif ($definition->field_type === 'date')
                <input type="date" name="custom_fields[{{ $definition->id }}]" value="{{ $value }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @elseif ($definition->field_type === 'number')
                <input type="number" step="any" name="custom_fields[{{ $definition->id }}]" value="{{ $value }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @else
                <input type="text" name="custom_fields[{{ $definition->id }}]" value="{{ $value }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @endif
        </div>
    @endforeach
</div>
@endif
