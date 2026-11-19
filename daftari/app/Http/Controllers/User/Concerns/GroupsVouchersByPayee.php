<?php

namespace App\Http\Controllers\User\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * "I need what we have paid to Shahid, Ashraf and Aljazeera separately
 * and also in one file" — a company recording every payment under one
 * umbrella supplier record (their main point of contact) while the real
 * money goes to several different people/firms, named only in each
 * voucher's free-text reference/payee/notes fields rather than a
 * structured "paid to" field of their own. Shared by PaymentVoucherController
 * and ReceiptVoucherController's summaryPdf() actions — identical logic
 * for both, so it's a trait rather than being duplicated.
 */
trait GroupsVouchersByPayee
{
    /**
     * A single free-text filter against reference/payee-or-payer name/notes
     * — the "give me just what we paid Shahid" case: filters the voucher
     * list itself down to one person's own document. $nameColumn is
     * 'payee_name' for PaymentVoucher, 'payer_name' for ReceiptVoucher —
     * the two tables name this column differently.
     */
    private function applyPayeeSearch($query, string $search, string $nameColumn = 'payee_name')
    {
        if ($search === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('reference', 'like', "%{$search}%")
            ->orWhere($nameColumn, 'like', "%{$search}%")
            ->orWhere('notes', 'like', "%{$search}%"));
    }

    /**
     * The "separately AND in one file" case: splits an already-filtered
     * voucher collection into named groups by keyword, each with its own
     * subtotal, for one combined PDF. $request carries parallel
     * group_label[]/group_terms[] arrays (one row per group from the
     * form — group_terms is a comma-separated list of keywords for that
     * group, matched case-insensitively against the same three fields
     * applyPayeeSearch() does). Anything matching none of the defined
     * groups lands in a final "Other" group instead of silently vanishing
     * from the breakdown — the combined total must always still equal
     * the flat, ungrouped total.
     *
     * Returns null (meaning: render the normal flat table) when no
     * groups were actually submitted.
     */
    private function buildVoucherGroups(Request $request, Collection $vouchers, string $nameColumn = 'payee_name'): ?array
    {
        $labels = (array) $request->query('group_label', []);
        $termLists = (array) $request->query('group_terms', []);

        if (empty(array_filter($labels, fn ($l) => trim((string) $l) !== ''))) {
            return null;
        }

        $remaining = $vouchers->keyBy('id');
        $groups = [];

        foreach ($labels as $i => $label) {
            $label = trim((string) $label);
            $keywords = array_values(array_filter(array_map('trim', explode(',', (string) ($termLists[$i] ?? '')))));

            if ($label === '' || empty($keywords)) {
                continue;
            }

            $matched = $remaining->filter(function ($voucher) use ($keywords, $nameColumn) {
                $haystack = mb_strtolower(($voucher->reference ?? '').' '.($voucher->{$nameColumn} ?? '').' '.($voucher->notes ?? ''));

                foreach ($keywords as $term) {
                    if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                        return true;
                    }
                }

                return false;
            });

            $groups[] = ['label' => $label, 'vouchers' => $matched->values(), 'total' => (float) $matched->sum('amount')];
            $remaining = $remaining->diffKeys($matched->all());
        }

        if ($remaining->isNotEmpty()) {
            $groups[] = ['label' => __('Other / Unmatched'), 'vouchers' => $remaining->values(), 'total' => (float) $remaining->sum('amount')];
        }

        return empty($groups) ? null : $groups;
    }
}
