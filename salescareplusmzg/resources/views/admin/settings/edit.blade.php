@php
    $grouped = collect($fields)->map(fn ($field, $key) => ['key' => $key, 'label' => $field[0], 'group' => $field[1], 'type' => $field[2]])->groupBy('group');
    $groupTitles = [
        'company' => 'Company Details',
        'contact' => 'Contact Details',
        'theme' => 'Theme Colors',
        'stats' => 'Homepage Stats & Coverage',
        'social' => 'Social Links',
        'hero' => 'Homepage Hero Video Banner',
        'about' => 'About Page Text',
        'services' => 'Services Page Text',
        'quality' => 'Quality Page Text',
        'careers' => 'Careers Page Text',
    ];
@endphp
<x-admin.layout title="Site Settings">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Site Settings</h2>
        <p class="mt-1 text-sm text-slate-500">Text and details used across the whole website — no code changes needed.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-2xl space-y-8">
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h3 class="mb-4 text-lg font-semibold text-slate-900">Branding &amp; Logo</h3>
            <div class="space-y-5">
                <div>
                    <x-admin.field label="Upload Logo" name="company_logo_file" type="file" accept="image/*" hint="Shown in the header, footer and admin sidebar. Transparent PNG recommended." />
                    @if ($values['company_logo_path'] ?? null)
                        <div class="mt-2 flex items-center gap-3 rounded-lg bg-teal-50 px-3 py-2 text-xs text-teal-700">
                            <img src="{{ asset('storage/'.$values['company_logo_path']) }}" alt="" class="h-8 w-auto object-contain">
                            <span>Current logo</span>
                            <label class="ml-auto flex items-center gap-1.5">
                                <input type="checkbox" name="remove_company_logo" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-coral-500">
                                Remove
                            </label>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @foreach ($groupTitles as $groupKey => $groupTitle)
            @if ($grouped->has($groupKey))
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900">{{ $groupTitle }}</h3>
                    <div class="space-y-5">
                        @foreach ($grouped[$groupKey] as $field)
                            @php
                                $rawValue = $values[$field['key']] ?? null;
                                $fieldType = match ($field['type']) {
                                    'lines', 'textarea' => 'textarea',
                                    'color' => 'color',
                                    default => 'text',
                                };
                                $fieldValue = $field['type'] === 'lines' && $rawValue ? implode("\n", json_decode($rawValue, true) ?: []) : $rawValue;
                                $fieldValue = $field['type'] === 'color' ? ($rawValue ?: '#2a9078') : $fieldValue;
                            @endphp
                            <x-admin.field :label="$field['label']" :name="$field['key']" :type="$fieldType" :value="$fieldValue" :class="$field['type'] === 'color' ? 'h-11 w-24 cursor-pointer p-1' : ''" />
                        @endforeach

                        @if ($groupKey === 'hero')
                            <x-admin.field label="Upload Video File" name="home_hero_video_file" type="file" accept="video/*" hint="Overrides the URL above if both are set." />
                            @if ($values['home_hero_video_path'] ?? null)
                                <div class="flex items-center gap-3 rounded-lg bg-teal-50 px-3 py-2 text-xs text-teal-700">
                                    <span>Current file: {{ basename($values['home_hero_video_path']) }}</span>
                                    <label class="ml-auto flex items-center gap-1.5">
                                        <input type="checkbox" name="remove_home_hero_video" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-coral-500">
                                        Remove
                                    </label>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            Save Settings
        </button>
    </form>

</x-admin.layout>
