{{--
    A titled box of label/value rows — the "Seller:"/"Buyer:" (sage_classic)
    and "Seller"/"Customer" (retail_classic) national-address blocks both
    real reference invoices used this exact shape for, just with different
    field sets/order and header styling. Shared here so that structure
    isn't duplicated four times (two blocks × two layouts) in body.blade.php.

    Params: $title, $titleClass (header bar classes), $boxClass (outer
    border classes), $rows (array of ['label' => string, 'value' => mixed]
    — a null/empty value prints as an em dash, matching how every other
    layout here shows an unset field).
--}}
<div class="{{ $boxClass ?? 'border border-slate-300' }}">
    <div class="{{ $titleClass ?? 'bg-slate-700 text-white' }} text-sm font-semibold px-3 py-1.5">{{ $title }}</div>
    <table class="w-full text-sm">
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-b border-slate-200 last:border-0">
                    <td class="px-3 py-1.5 font-semibold text-slate-600 w-2/5 align-top">{{ $row['label'] }}</td>
                    <td class="px-3 py-1.5 text-slate-800">{{ $row['value'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
