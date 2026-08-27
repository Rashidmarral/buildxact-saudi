@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }} @if($required)<span class="text-coral-500">*</span>@endif
    </label>

    @if ($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $attributes->get('rows', 4) }}"
            {{ $attributes->except(['rows'])->merge(['class' => 'mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500']) }}>{{ old($name, $value) }}</textarea>
    @elseif ($type === 'select')
        <select name="{{ $name }}" id="{{ $name }}"
            {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500']) }}>
            {{ $slot }}
        </select>
    @elseif ($type === 'checkbox')
        <div class="mt-1.5 flex items-center gap-2">
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox" name="{{ $name }}" id="{{ $name }}" value="1" @checked(old($name, $value))
                {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500']) }}>
            <span class="text-sm text-slate-600">{{ $hint }}</span>
        </div>
    @elseif ($type === 'file')
        <input type="file" name="{{ $name }}" id="{{ $name }}"
            {{ $attributes->merge(['class' => 'mt-1.5 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-teal-700 hover:file:bg-teal-100']) }}>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $value) }}"
            {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500']) }}>
    @endif

    @if ($hint && $type !== 'checkbox')
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
