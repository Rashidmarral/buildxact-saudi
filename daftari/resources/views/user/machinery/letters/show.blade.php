@extends('layouts.app')

@section('title', $letter->title)

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('app.machinery.letters.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Letters & Agreements') }}</a>
        <h1 class="text-xl font-bold text-slate-900 mt-1">{{ $letter->title }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $letter->reference_number }} — {{ $letter->letter_date->format('Y-m-d') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.machinery.letters.pdf', $letter) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.machinery.letters.edit', $letter) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Parties') }}</h3>
        <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-slate-500">{{ $letter->party_a_role }}</dt><dd>{{ $letter->partyAName() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ $letter->party_b_role }}</dt><dd>{{ $letter->party_b_name }}</dd></div>
        </dl>
        @if ($letter->party_b_details)
            <p class="text-xs text-slate-400 mt-3">{{ $letter->party_b_details }}</p>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('Linked to') }}</h3>
        <dl class="text-sm space-y-2">
            @if ($letter->machinery)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Machine') }}</dt><dd><a href="{{ route('app.machinery.assets.show', $letter->machinery) }}" class="text-brand-700 hover:underline">{{ $letter->machinery->name }}</a></dd></div>
            @endif
            @if ($letter->project)
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Project') }}</dt><dd>{{ $letter->project->name }}</dd></div>
            @endif
            @if (! $letter->machinery && ! $letter->project)
                <p class="text-sm text-slate-400">{{ __('Not linked to a specific machine or project.') }}</p>
            @endif
        </dl>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
    <h3 class="font-semibold text-slate-900">{{ __('Content') }}</h3>
    @foreach ($letter->content as $block)
        <div class="grid sm:grid-cols-2 gap-4 {{ $block['is_heading'] ? 'font-semibold text-slate-900' : 'text-slate-600' }} text-sm border-b border-slate-50 pb-4 last:border-0">
            <p>{{ $block['text_en'] }}</p>
            <p dir="rtl">{{ $block['text_ar'] }}</p>
        </div>
    @endforeach
</div>
@endsection
