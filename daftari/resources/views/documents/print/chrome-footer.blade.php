@php($layout = $template->layout ?? 'minimal')
@if ($company->stamp_path)
    <div class="mt-8 flex justify-end">
        <img src="{{ Storage::url($company->stamp_path) }}" alt="{{ __('Company stamp') }}" class="h-24 w-24 object-contain">
    </div>
@endif

@if (in_array($layout, ['bilingual_classic', 'custom_letterhead']))
    <div class="mt-10 border-t border-slate-200 pt-3 text-center text-xs text-slate-400">
        {{ $company->name }} @if ($company->name_ar) — {{ $company->name_ar }} @endif &nbsp;·&nbsp; {{ __('Page 1 of 1') }}
    </div>
@endif
