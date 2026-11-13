@props(['filters' => null])
{{--
    Drop-in toolbar for any index page's data table: wrap the page's
    existing table container in <x-list-toolbar> ... </x-list-toolbar>
    and nothing else needs to change. Purely client-side (Alpine, already
    a project dependency) — finds the first <table> inside the slot and
    adds instant search, column show/hide, and print, without touching
    any controller or route. Server-side filter fields (date range,
    status, etc.) can be passed through the $filters slot to render in
    the collapsible panel next to the search bar — same "تصفية أو فرز"
    pattern used across Hamly ERP's list pages, which this was modeled on
    after a side-by-side comparison.
--}}
<div
    x-data="{
        query: '',
        columns: [],
        filtersOpen: false,
        init() {
            const table = this.$el.querySelector('table');
            if (! table) return;
            this.table = table;
            this.columns = Array.from(table.querySelectorAll('thead th')).map((th, i) => ({
                index: i,
                label: th.textContent.trim() || '—',
                visible: true,
            }));
        },
        filterRows() {
            if (! this.table) return;
            const q = this.query.trim().toLowerCase();
            this.table.querySelectorAll('tbody tr').forEach((row) => {
                row.style.display = (! q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
            });
        },
        toggleColumn(col) {
            col.visible = ! col.visible;
            this.table.querySelectorAll('tr').forEach((row) => {
                const cell = row.children[col.index];
                if (cell) cell.style.display = col.visible ? '' : 'none';
            });
        },
    }"
    class="space-y-3"
>
    <div class="bg-white rounded-xl border border-slate-100 p-3 flex flex-wrap items-center gap-2 print:hidden">
        <div class="relative flex-1 min-w-[180px]">
            <span class="absolute inset-y-0 start-3 flex items-center text-slate-400">
                @include('partials.icon', ['name' => 'search', 'class' => 'h-4 w-4'])
            </span>
            <input
                type="text"
                x-model="query"
                x-on:input="filterRows()"
                placeholder="{{ __('Search...') }}"
                class="w-full rounded-lg border border-slate-200 ps-9 pe-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500"
            >
        </div>

        @if ($filters)
            <button type="button" x-on:click="filtersOpen = ! filtersOpen" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">
                @include('partials.icon', ['name' => 'funnel', 'class' => 'h-4 w-4'])
                {{ __('Filter') }}
            </button>
        @endif

        <div class="relative" x-data="{ colsOpen: false }">
            <button type="button" x-on:click="colsOpen = ! colsOpen" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">
                @include('partials.icon', ['name' => 'columns', 'class' => 'h-4 w-4'])
                {{ __('Columns') }}
            </button>
            <div x-show="colsOpen" x-on:click.outside="colsOpen = false" x-cloak class="absolute z-20 mt-1 w-56 rounded-lg border border-slate-100 bg-white p-2 shadow-lg end-0">
                <template x-for="col in columns" :key="col.index">
                    <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs text-slate-600 hover:bg-slate-50">
                        <input type="checkbox" :checked="col.visible" x-on:change="toggleColumn(col)" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span x-text="col.label"></span>
                    </label>
                </template>
            </div>
        </div>

        <button type="button" x-on:click="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">
            @include('partials.icon', ['name' => 'printer', 'class' => 'h-4 w-4'])
            {{ __('Print') }}
        </button>
    </div>

    @if ($filters)
        <div x-show="filtersOpen" x-cloak class="bg-white rounded-xl border border-slate-100 p-4 print:hidden">
            {{ $filters }}
        </div>
    @endif

    {{--
        QA audit finding: a data table wider than its viewport (every
        column visible at once — invoice #, client, salesperson, dates,
        amounts, status, actions) had nothing clipping it, so it pushed
        the whole page wider than the screen on mobile (390px) instead of
        scrolling on its own — this wraps every list page's table in
        exactly one place instead of 68 individual files.
    --}}
    <div class="overflow-x-auto">
        {{ $slot }}
    </div>
</div>
