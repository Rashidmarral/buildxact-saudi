{{--
    Shared line-item "select item" combobox, included by every document
    form with an items table (invoices, quotations, recurring invoices,
    purchase orders, bills). Replaces the old plain <select> — which
    dumped every catalog item into one long dropdown with no way to
    filter it — with a type-to-search box backed by the already-loaded
    CATALOG array (no extra request; company catalogs here run in the
    hundreds, not thousands, so client-side filtering stays instant).

    Expects, from the including page's own <script> block: CATALOG,
    populateUnitOptions(tr, itemId, selectedUnitId), recalc(), and fmt(n)
    — all already defined by the time addRow() runs.

    NOTE: this file is @include()'d from inside an already-open <script>
    block in each form, so it must never itself contain <script> tags —
    doing so would close the parent block early and turn the rest of the
    page's JS into inert text.
--}}
let itemResultsPanel = null;
let itemResultsMatches = [];
let itemResultsActiveIndex = -1;
let itemResultsContext = null;

function ensureItemResultsPanel() {
    if (itemResultsPanel) return itemResultsPanel;
    itemResultsPanel = document.createElement('div');
    itemResultsPanel.id = 'item-search-results';
    itemResultsPanel.className = 'hidden fixed z-50 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg';
    itemResultsPanel.addEventListener('mousedown', (e) => e.preventDefault());
    itemResultsPanel.addEventListener('click', (e) => {
        const btn = e.target.closest('.item-result-option');
        if (! btn || ! itemResultsContext) return;
        const item = itemResultsMatches[Number(btn.dataset.idx)];
        if (item) itemResultsContext.select(item);
    });
    document.body.appendChild(itemResultsPanel);
    return itemResultsPanel;
}

function closeItemResults() {
    if (! itemResultsPanel) return;
    itemResultsPanel.classList.add('hidden');
    itemResultsPanel.innerHTML = '';
    itemResultsMatches = [];
    itemResultsActiveIndex = -1;
    itemResultsContext = null;
}

function highlightItemResult(idx) {
    itemResultsActiveIndex = idx;
    itemResultsPanel.querySelectorAll('.item-result-option').forEach((el, i) => {
        el.classList.toggle('bg-brand-50', i === idx);
    });
}

function renderItemResults(input, query, onSelect) {
    const panel = ensureItemResultsPanel();
    const q = query.trim().toLowerCase();
    itemResultsMatches = (! q ? CATALOG : CATALOG.filter(c =>
        c.name.toLowerCase().includes(q) ||
        (c.name_ar && c.name_ar.toLowerCase().includes(q)) ||
        (c.sku && c.sku.toLowerCase().includes(q)) ||
        (c.barcode && c.barcode.toLowerCase().includes(q))
    )).slice(0, 50);

    panel.innerHTML = itemResultsMatches.length
        ? itemResultsMatches.map((c, idx) => `
            <button type="button" data-idx="${idx}" class="item-result-option flex w-full items-center justify-between gap-3 px-4 py-2.5 text-start hover:bg-slate-50">
                <span class="truncate text-sm font-medium text-slate-800">${c.name}</span>
                <span class="shrink-0 text-xs text-slate-400">${c.sku ? c.sku + ' &middot; ' : ''}${fmt(c.unit_price)}</span>
            </button>
        `).join('')
        : `<div class="px-4 py-2.5 text-sm text-slate-400">${@json(__('No items found'))}</div>`;

    const rect = input.getBoundingClientRect();
    panel.style.left = rect.left + 'px';
    panel.style.top = (rect.bottom + 4) + 'px';
    panel.style.width = Math.max(rect.width, 320) + 'px';
    panel.classList.remove('hidden');
    itemResultsActiveIndex = -1;
    itemResultsContext = { select: onSelect };
}

document.addEventListener('click', (e) => {
    if (e.target.closest('[data-role="item-search"]') || e.target.closest('#item-search-results')) return;
    closeItemResults();
});
window.addEventListener('scroll', closeItemResults, true);
window.addEventListener('resize', closeItemResults);

function itemCellHtml(i, data) {
    return `
        <div class="relative">
            <input type="text" data-role="item-search" autocomplete="off" placeholder="${@json(__('Search item name, SKU or barcode…'))}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 pe-6">
            <button type="button" data-role="item-clear" tabindex="-1" class="hidden absolute inset-y-0 end-1.5 flex items-center text-slate-300 hover:text-slate-500" title="${@json(__('Clear'))}">&times;</button>
            <input type="hidden" name="items[${i}][item_id]" data-role="item_id" value="${data.item_id || ''}">
        </div>
    `;
}

function wireItemPicker(tr, data) {
    const input = tr.querySelector('[data-role="item-search"]');
    const hiddenId = tr.querySelector('[data-role="item_id"]');
    const clearBtn = tr.querySelector('[data-role="item-clear"]');

    function updateClearVisibility() {
        clearBtn.classList.toggle('hidden', ! hiddenId.value);
    }

    function selectItem(item) {
        hiddenId.value = item.id;
        input.value = item.name;
        tr.querySelector('[data-role="description"]').value = item.name;
        tr.querySelector('[data-role="unit_price"]').value = item.unit_price;
        tr.querySelector('[data-role="vat_rate"]').value = item.vat_rate;
        populateUnitOptions(tr, item.id, null);
        updateClearVisibility();
        closeItemResults();
        recalc();
    }

    input.addEventListener('focus', () => {
        input.select();
        renderItemResults(input, '', selectItem);
    });

    // 'focus' only fires when focus actually moves to the input, so a
    // click while it's already focused (e.g. right after clearing a
    // selection, which refocuses it) wouldn't otherwise reopen the list.
    input.addEventListener('click', () => {
        if (! itemResultsPanel || itemResultsPanel.classList.contains('hidden')) {
            renderItemResults(input, input.value, selectItem);
        }
    });

    input.addEventListener('input', () => renderItemResults(input, input.value, selectItem));

    input.addEventListener('keydown', (e) => {
        if (! itemResultsPanel || itemResultsPanel.classList.contains('hidden')) {
            if (['ArrowDown', 'ArrowUp'].includes(e.key)) renderItemResults(input, input.value, selectItem);
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightItemResult(Math.min(itemResultsActiveIndex + 1, itemResultsMatches.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightItemResult(Math.max(itemResultsActiveIndex - 1, 0));
        } else if (e.key === 'Enter') {
            if (itemResultsActiveIndex >= 0 && itemResultsMatches[itemResultsActiveIndex]) {
                e.preventDefault();
                selectItem(itemResultsMatches[itemResultsActiveIndex]);
            }
        } else if (e.key === 'Escape') {
            closeItemResults();
        }
    });

    clearBtn.addEventListener('click', () => {
        hiddenId.value = '';
        input.value = '';
        populateUnitOptions(tr, '', null);
        updateClearVisibility();
        recalc();
        input.focus();
    });

    if (data.item_id) {
        const existing = CATALOG.find(c => String(c.id) === String(data.item_id));
        if (existing) input.value = existing.name;
    }
    updateClearVisibility();
}
