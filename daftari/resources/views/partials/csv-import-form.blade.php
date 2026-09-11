{{-- Expects: $importAction, $templateUrl, $backUrl, $columns (array of ['key' => ..., 'label' => ..., 'required' => bool]) --}}
<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    @if (session('import_result'))
        @php $result = session('import_result'); @endphp
        <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
            {{ __(':imported of :total row(s) imported.', ['imported' => $result['imported'], 'total' => $result['total']]) }}
        </div>
        @if (! empty($result['errors']))
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                <p class="font-semibold mb-1">{{ __('Some rows were skipped:') }}</p>
                <ul class="list-disc ps-4 space-y-0.5">
                    @foreach ($result['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    <h2 class="font-semibold text-slate-900 mb-1">{{ __('Upload CSV file') }}</h2>
    <p class="text-sm text-slate-500 mb-4">{{ __('The first row must be column headers. Download the template below to get the exact column names.') }}</p>

    <a href="{{ $templateUrl }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline mb-5">
        @include('partials.icon', ['name' => 'clipboard', 'class' => 'h-4 w-4'])
        {{ __('Download CSV template') }}
    </a>

    <form method="POST" action="{{ $importAction }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('CSV file') }}</label>
            <input type="file" name="file" accept=".csv,text/csv" required class="mt-1 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Import') }}</button>
            <a href="{{ $backUrl }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Cancel') }}</a>
        </div>
    </form>

    <div class="mt-6 pt-5 border-t border-slate-100">
        <p class="text-xs font-semibold text-slate-500 mb-2">{{ __('Columns') }}</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($columns as $column)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $column['required'] ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $column['label'] }}{{ $column['required'] ? '*' : '' }}
                </span>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-400">{{ __('* Required column.') }}</p>
    </div>
</div>
