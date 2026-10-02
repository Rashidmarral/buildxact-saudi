@php
    $__allowedPerPage = [10, 20, 25, 50, 100];
    $__currentPerPage = $paginator->perPage();
    if (! in_array($__currentPerPage, $__allowedPerPage, true)) {
        $__allowedPerPage[] = $__currentPerPage;
        sort($__allowedPerPage);
    }
@endphp
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4">
    <p class="text-xs text-slate-500">
        @if ($paginator->total() > 0)
            {{ __(':from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        @else
            {{ __('No results.') }}
        @endif
    </p>
    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-xs text-slate-500">
            {{ __('Per page') }}
            <select
                onchange="const u = new URL(window.location.href); u.searchParams.set('per_page', this.value); u.searchParams.delete('page'); window.location.href = u.toString();"
                class="rounded-lg border border-slate-200 text-xs py-1 ps-2 pe-7 focus:border-brand-500 focus:ring-brand-500"
            >
                @foreach ($__allowedPerPage as $__option)
                    <option value="{{ $__option }}" @selected($__currentPerPage == $__option)>{{ $__option }}</option>
                @endforeach
            </select>
        </label>
        <div>{{ $paginator->links() }}</div>
    </div>
</div>
