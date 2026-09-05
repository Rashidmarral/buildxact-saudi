@extends('layouts.admin')

@section('title', __('Certificates'))

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.settings.edit') }}" class="text-sm text-slate-500 hover:text-brand-700">&larr; {{ __('Platform settings') }}</a>
    <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ __('Certificates & compliance documents') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Shown publicly on the marketing site\'s Certificates page.') }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-8">
    @if ($documents->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No certificates uploaded yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('File') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Order') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documents as $document)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-800">
                            {{ $document->title }}
                            @if ($document->title_ar)<div class="text-xs text-slate-400">{{ $document->title_ar }}</div>@endif
                        </td>
                        <td class="px-6 py-3"><a href="{{ Storage::url($document->file_path) }}" target="_blank" class="text-brand-700 hover:underline">{{ __('View') }}</a></td>
                        <td class="px-6 py-3 text-slate-500">{{ $document->sort_order }}</td>
                        <td class="px-6 py-3 text-right">
                            <form method="POST" action="{{ route('admin.certificates.destroy', $document) }}" onsubmit="return confirm('{{ __('Delete this certificate?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 max-w-xl">
    <h2 class="font-semibold text-slate-900 mb-4">{{ __('Upload certificate') }}</h2>
    <form method="POST" action="{{ route('admin.certificates.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Title') }}</label>
            <input type="text" name="title" required placeholder="{{ __('e.g. Commercial Registration Certificate') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Title (Arabic)') }}</label>
            <input type="text" name="title_ar" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Description (optional)') }}</label>
            <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('File') }}</label>
            <input type="file" name="file" required accept="application/pdf,image/png,image/jpeg,image/webp" class="mt-1 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
            <p class="text-xs text-slate-400 mt-1">{{ __('PDF, PNG, JPEG, or WebP · max 10MB') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Display order') }}</label>
            <input type="number" name="sort_order" min="0" value="0" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Upload') }}</button>
    </form>
</div>
@endsection
