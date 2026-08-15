{{--
    Shared document print/PDF body, used by Invoices, Quotations, Bills and
    Purchase Orders. Callers build a normalized $doc array (see any of those
    show.blade.php files for the exact shape) plus pass $company and
    $template — this partial only renders, it never reaches back into a
    specific Eloquent model, so the same 5 layouts stay pixel-identical no
    matter which document type is printing.
--}}
@php
    $accent = $template->accent_color ?? '#0f766e';
    $layout = $template->layout ?? 'minimal';
    $showLogo = $template->show_logo ?? true;
    $bankAccounts = $doc['bank_accounts'] ?? (($doc['bank_account'] ?? null) ? collect([$doc['bank_account']]) : collect());
@endphp

@if ($layout === 'bilingual_classic')
    {{-- "Bilingual Classic": dual-language mirrored header, centered
         bilingual title, boxed info panel, bilingual 6-column VAT table,
         bank + totals footer, notes list, stamp. --}}
    <div class="border-2 rounded-lg p-1 mb-6 h-3" style="border-color: {{ $accent }}"></div>

    <div class="flex items-start justify-between gap-6">
        <div class="text-sm leading-relaxed text-slate-700">
            <h1 class="text-base font-bold text-slate-900">{{ $company->name }}</h1>
            @if ($company->address)<p>{{ $company->address }}</p>@endif
            @if ($company->city)<p>{{ $company->city }}</p>@endif
            <p>{{ __('Kingdom of Saudi Arabia') }}</p>
            @if ($company->email)<p>{{ $company->email }}</p>@endif
            @if ($company->phone)<p>{{ $company->phone }}</p>@endif
            @if ($company->vat_number)<p>{{ __('VAT number') }} {{ $company->vat_number }}</p>@endif
            @if ($company->cr_number)<p>{{ __('CR Number') }} {{ $company->cr_number }}</p>@endif
        </div>

        @if ($showLogo && $company->logo_path)
            <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-24 w-24 shrink-0 rounded-full object-cover">
        @endif

        <div class="text-sm leading-relaxed text-slate-700 text-end" dir="rtl">
            @if ($company->name_ar)<h1 class="text-base font-bold text-slate-900">{{ $company->name_ar }}</h1>@endif
            @if ($company->address)<p>{{ $company->address }}</p>@endif
            @if ($company->city)<p>{{ $company->city }}</p>@endif
            <p>المملكة العربية السعودية</p>
            @if ($company->email)<p>{{ $company->email }}</p>@endif
            @if ($company->phone)<p>{{ $company->phone }}</p>@endif
            @if ($company->vat_number)<p>رقم التسجيل الضريبي {{ $company->vat_number }}</p>@endif
            @if ($company->cr_number)<p>رقم السجل التجاري {{ $company->cr_number }}</p>@endif
        </div>
    </div>

    <div class="mt-8 pb-4 border-b-2 border-slate-800 text-center">
        <h2 class="text-3xl font-bold text-slate-900"><span dir="rtl">{{ $doc['type_label_ar'] }}</span> &nbsp; {{ $doc['type_label'] }}</h2>
    </div>

    <table class="w-full text-sm mt-6 border border-slate-300">
        <tbody>
            <tr class="border-b border-slate-300">
                <td class="w-1/6 px-3 py-2 font-semibold text-slate-700">{{ $doc['party_label'] }}</td>
                <td class="px-3 py-2 text-center font-medium text-slate-800">{{ $doc['party']->name }}</td>
                <td class="w-1/6 px-3 py-2 text-end font-semibold text-slate-700" dir="rtl">{{ $doc['party_label_ar'] }}</td>
            </tr>
            <tr class="border-b border-slate-300">
                <td class="px-3 py-2 font-semibold text-slate-700">{{ __('VAT number') }}</td>
                <td class="px-3 py-2 text-center text-slate-600">{{ $doc['party']->vat_number ?: '—' }}</td>
                <td class="px-3 py-2 text-end font-semibold text-slate-700" dir="rtl">رقم التسجيل الضريبي</td>
            </tr>
            <tr class="border-b border-slate-300">
                <td class="px-3 py-2 font-semibold text-slate-700">{{ __('Number') }}</td>
                <td class="px-3 py-2 text-center text-slate-600">{{ $doc['number'] }} | {{ $doc['date_label'] }} {{ $doc['date']->format('Y-m-d') }}</td>
                <td class="px-3 py-2 text-end font-semibold text-slate-700" dir="rtl">رقم | التاريخ</td>
            </tr>
            @if (!empty($doc['date2']))
                <tr>
                    <td class="px-3 py-2 font-semibold text-slate-700">{{ $doc['date2_label'] }}</td>
                    <td class="px-3 py-2 text-center text-slate-600">{{ $doc['date2']->format('Y-m-d') }}</td>
                    <td class="px-3 py-2 text-end font-semibold text-slate-700" dir="rtl">{{ $doc['date2_label_ar'] ?? '' }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="w-full text-sm mt-6">
        <thead>
            <tr class="text-left text-slate-600 border-b-2 border-slate-800">
                <th class="py-2 ps-1">#</th>
                <th class="py-2">{{ __('Description') }}<br><span class="font-normal text-xs" dir="rtl">الوصف</span></th>
                <th class="py-2 text-end">{{ __('Qty') }}<br><span class="font-normal text-xs" dir="rtl">الكمية</span></th>
                <th class="py-2 text-end">{{ __('Price') }}<br><span class="font-normal text-xs" dir="rtl">السعر</span></th>
                <th class="py-2 text-end">{{ __('Taxable amount') }}<br><span class="font-normal text-xs" dir="rtl">المبلغ الخاضع للضريبة</span></th>
                <th class="py-2 text-end">{{ __('VAT amount') }}<br><span class="font-normal text-xs" dir="rtl">القيمة المضافة</span></th>
                <th class="py-2 pe-1 text-end">{{ __('Line amount') }}<br><span class="font-normal text-xs" dir="rtl">المجموع</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr class="border-b border-slate-200 align-top">
                    <td class="py-3 ps-1 text-slate-500">{{ $index + 1 }}</td>
                    <td class="py-3 max-w-xs">
                        <p class="font-semibold text-slate-800">{{ $line->description }}</p>
                        @if (!empty($line->item?->description))
                            <p class="mt-1 text-xs text-slate-500">{{ $line->item->description }}</p>
                        @endif
                    </td>
                    <td class="py-3 text-end text-slate-700">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="py-3 text-end text-slate-700">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="py-3 text-end text-slate-700">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                    <td class="py-3 text-end text-slate-700">{{ number_format($line->vat_amount, 2) }}<br><span class="text-xs text-slate-400">{{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</span></td>
                    <td class="py-3 pe-1 text-end font-medium text-slate-900">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-6 flex flex-col-reverse gap-6 sm:flex-row sm:justify-between">
        <div class="text-sm text-slate-600">
            @if ($bankAccounts->isNotEmpty())
                @php
                    $ba = $bankAccounts->first();
                @endphp
                <p><span class="font-semibold text-slate-700">{{ __('Bank Name') }}:</span> {{ $ba->bank_name ?: $ba->name }}</p>
                @if ($ba->account_holder_name)<p><span class="font-semibold text-slate-700">{{ __('Account Name') }}:</span> {{ $ba->account_holder_name }}</p>@endif
                @if ($ba->account_number)<p><span class="font-semibold text-slate-700">{{ __('Account Number') }}:</span> {{ $ba->account_number }}</p>@endif
                @if ($ba->iban)<p><span class="font-semibold text-slate-700">{{ __('IBAN') }}:</span> {{ $ba->iban }}</p>@endif
            @endif
        </div>
        <div class="w-full max-w-xs space-y-1.5 text-sm">
            <div class="flex justify-between text-slate-600"><span>{{ __('Subtotal') }} <span class="text-xs text-slate-400" dir="rtl">المجموع الفرعي</span></span><span>SAR {{ number_format($doc['subtotal'], 2) }}</span></div>
            @if (($doc['discount_total'] ?? 0) > 0)
                <div class="flex justify-between text-slate-600"><span>{{ __('Discount') }}</span><span>-SAR {{ number_format($doc['discount_total'], 2) }}</span></div>
            @endif
            <div class="flex justify-between text-slate-600"><span>{{ __('Total VAT') }} <span class="text-xs text-slate-400" dir="rtl">إجمالي ضريبة القيمة المضافة</span></span><span>SAR {{ number_format($doc['vat_total'], 2) }}</span></div>
            <div class="flex justify-between border-t-2 border-slate-800 pt-2 text-base font-bold text-slate-900"><span>{{ __('Total') }} <span class="text-xs font-normal text-slate-400" dir="rtl">المجموع شامل القيمة المضافة</span></span><span>SAR {{ number_format($doc['total'], 2) }}</span></div>
            @foreach ($doc['extra_rows'] ?? [] as $row)
                @php
                    $rowColor = ($row['variant'] ?? null) === 'red' ? 'font-semibold text-red-600' : (($row['emphasis'] ?? false) ? 'font-semibold text-brand-700' : 'text-slate-600');
                @endphp
                <div class="flex justify-between {{ $rowColor }}"><span>{{ $row['label'] }}</span><span>SAR {{ number_format($row['value'], 2) }}</span></div>
            @endforeach
        </div>
    </div>

    @if (!empty($doc['notes']))
        <div class="mt-8 text-sm">
            <h4 class="font-semibold text-slate-800">{{ __('Notes') }} <span class="text-xs text-slate-400" dir="rtl">ملاحظات</span></h4>
            <p class="mt-1 whitespace-pre-line text-slate-600">{{ $doc['notes'] }}</p>
        </div>
    @endif

    @if ($template && $template->notesFor(app()->getLocale()))
        <div class="mt-2 text-sm text-slate-500 whitespace-pre-line">{{ $template->notesFor(app()->getLocale()) }}</div>
    @endif

    @if ($company->stamp_path)
        <div class="mt-8 flex justify-end">
            <img src="{{ Storage::url($company->stamp_path) }}" alt="{{ __('Company stamp') }}" class="h-28 w-28 object-contain">
        </div>
    @endif

    <div class="mt-10 border-t border-slate-200 pt-3 text-center text-xs text-slate-400">
        {{ $company->name }} @if ($company->name_ar) — {{ $company->name_ar }} @endif &nbsp;·&nbsp; {{ __('Page 1 of 1') }} &nbsp;·&nbsp; {{ $doc['number'] }}
    </div>

@elseif ($layout === 'custom_letterhead')
    {{-- "Custom Letterhead": an uploaded banner image replaces the logo
         header, simple key-value info rows, a compact item table, amount
         spelled out in words, a sales-executive block, and multi-bank
         details — matching letterhead-style quotations some companies
         already issue. --}}
    @if ($template && $template->letterhead_path)
        <img src="{{ Storage::url($template->letterhead_path) }}" alt="{{ $company->name }}" class="w-full object-contain">
    @elseif ($showLogo && $company->logo_path)
        <div class="flex items-center gap-3">
            <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-16 w-16 rounded-lg object-cover">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $company->name }}</h1>
                @if ($company->vat_number)<p class="text-xs text-slate-500">{{ __('VAT') }}: {{ $company->vat_number }}</p>@endif
            </div>
        </div>
    @endif

    <h2 class="mt-4 text-center text-base font-bold uppercase tracking-wide text-slate-900">
        {{ strtoupper($doc['type_label']) }} <span class="font-normal">/ ({{ $doc['type_label_ar'] }})</span>
    </h2>

    <table class="w-full text-sm mt-5">
        <tbody>
            <tr>
                <td class="w-1/4 py-1 font-semibold text-slate-600">{{ $doc['party_label'] }}</td>
                <td class="w-1/4 py-1 text-slate-800">{{ $doc['party']->name }}</td>
                <td class="w-1/4 py-1 font-semibold text-slate-600">{{ __('Number') }}</td>
                <td class="py-1 text-slate-800">{{ $doc['number'] }}</td>
            </tr>
            <tr>
                <td class="py-1 font-semibold text-slate-600">{{ $doc['date_label'] }}</td>
                <td class="py-1 text-slate-800">{{ $doc['date']->format('d-m-Y') }}</td>
                @if (!empty($doc['date2']))
                    <td class="py-1 font-semibold text-slate-600">{{ $doc['date2_label'] }}</td>
                    <td class="py-1 text-slate-800">{{ $doc['date2']->format('d-m-Y') }}</td>
                @endif
            </tr>
            @if (!empty($doc['ref_no']))
                <tr>
                    <td class="py-1 font-semibold text-slate-600">{{ __('Ref No') }}</td>
                    <td class="py-1 text-slate-800">{{ $doc['ref_no'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="w-full text-sm mt-5 border border-slate-300">
        <thead>
            <tr class="bg-slate-50 text-left text-slate-700">
                <th class="border border-slate-300 px-2 py-1.5 w-10">Sr</th>
                <th class="border border-slate-300 px-2 py-1.5">{{ __('Items') }}</th>
                <th class="border border-slate-300 px-2 py-1.5 text-end w-24">{{ __('Quantity') }}</th>
                <th class="border border-slate-300 px-2 py-1.5 text-end w-24">{{ __('Rate') }}</th>
                <th class="border border-slate-300 px-2 py-1.5 text-end w-28">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr>
                    <td class="border border-slate-300 px-2 py-1.5 align-top text-slate-500">{{ $index + 1 }}</td>
                    <td class="border border-slate-300 px-2 py-1.5 align-top text-slate-800">{{ $line->description }}</td>
                    <td class="border border-slate-300 px-2 py-1.5 align-top text-end text-slate-700">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} {{ $line->item?->unit ?? '' }}</td>
                    <td class="border border-slate-300 px-2 py-1.5 align-top text-end text-slate-700">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="border border-slate-300 px-2 py-1.5 align-top text-end font-medium text-slate-900">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 flex flex-col-reverse gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-sm text-sm">
            <p class="font-semibold text-slate-700">{{ __('In Words') }}</p>
            <p class="text-slate-600">{{ \App\Support\NumberToWords::sar($doc['total'], 'SAR') }}</p>
        </div>
        <div class="w-full max-w-xs space-y-1 text-sm">
            <div class="flex justify-between"><span class="font-semibold text-slate-700">{{ __('Total') }}</span><span>{{ number_format($doc['subtotal'] - ($doc['discount_total'] ?? 0), 2) }}</span></div>
            <div class="flex justify-between"><span class="font-semibold text-slate-700">{{ __('Total Tax') }}</span><span>{{ number_format($doc['vat_total'], 2) }}</span></div>
            <div class="flex justify-between text-base font-bold text-slate-900"><span>{{ __('Grand Total') }}</span><span>{{ number_format($doc['total'], 2) }}</span></div>
            @foreach ($doc['extra_rows'] ?? [] as $row)
                <div class="flex justify-between {{ $row['emphasis'] ?? false ? 'font-semibold text-brand-700' : 'text-slate-600' }}"><span>{{ $row['label'] }}</span><span>{{ number_format($row['value'], 2) }}</span></div>
            @endforeach
        </div>
    </div>

    @if (!empty($doc['salesperson']))
        <div class="mt-6 text-sm">
            <p class="font-semibold text-slate-800">{{ __('Sales Executive') }}</p>
            <p class="text-slate-600">{{ $doc['salesperson']->name }}</p>
            @if ($doc['salesperson']->phone)<p class="text-slate-600">{{ __('Contact No') }}: {{ $doc['salesperson']->phone }}</p>@endif
        </div>
    @endif

    @if ($bankAccounts->isNotEmpty())
        <div class="mt-4 text-sm">
            <p class="font-semibold text-slate-800">{{ __('Bank Account Details') }} :</p>
            @foreach ($bankAccounts as $ba)
                <p class="text-slate-600">{{ __('Bank Name') }}: {{ $ba->bank_name ?: $ba->name }} &nbsp; {{ __('IBAN') }}: {{ $ba->iban }} &nbsp; {{ __('A/C #') }}: {{ $ba->account_number }}</p>
            @endforeach
        </div>
    @endif

    @if (!empty($doc['notes']))
        <div class="mt-6 text-sm text-slate-600 whitespace-pre-line">{{ $doc['notes'] }}</div>
    @endif

    @if ($template && $template->notesFor(app()->getLocale()))
        <div class="mt-2 text-sm text-slate-500 whitespace-pre-line">{{ $template->notesFor(app()->getLocale()) }}</div>
    @endif

    @if ($company->stamp_path)
        <div class="mt-8 flex justify-end">
            <img src="{{ Storage::url($company->stamp_path) }}" alt="{{ __('Company stamp') }}" class="h-24 w-24 object-contain">
        </div>
    @endif

    <div class="mt-10 text-center text-xs text-slate-400">{{ __('Page 1 of 1') }}</div>

@else
    {{-- minimal / bordered / boxed — the original three layouts. --}}
    @if ($layout === 'bordered' && $accent)
        <div class="h-2 rounded-full mb-6 -mt-2" style="background-color: {{ $accent }}"></div>
    @endif
    <div class="flex justify-between items-start {{ $layout === 'bordered' && $accent ? 'border-s-4 ps-4' : '' }}" @if ($layout === 'bordered' && $accent) style="border-color: {{ $accent }}" @endif>
        <div class="flex items-center gap-3">
            @if ($showLogo && $company->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-12 w-12 rounded-lg object-cover border border-slate-100">
            @endif
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $company->name }}</h1>
                @if ($company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $company->vat_number }}</p>@endif
                @if ($company->address)<p class="text-sm text-slate-500">{{ $company->address }}</p>@endif
            </div>
        </div>
        <div class="text-end">
            <h2 class="text-xl font-bold" style="color: {{ $layout === 'minimal' && $accent ? $accent : '#0f172a' }}">{{ $doc['type_label'] }}</h2>
            <p class="text-sm text-slate-500">{{ $doc['number'] }}</p>
            <p class="text-sm text-slate-500">{{ $doc['date_label'] }}: {{ $doc['date']->format('Y-m-d') }}</p>
            @if (!empty($doc['date2']))<p class="text-sm text-slate-500">{{ $doc['date2_label'] }}: {{ $doc['date2']->format('Y-m-d') }}</p>@endif
        </div>
    </div>

    <div class="mt-8 grid grid-cols-2 gap-8">
        <div>
            <h3 class="text-xs font-semibold uppercase text-slate-400">{{ $doc['party_label'] }}</h3>
            <p class="mt-1 font-medium text-slate-800">{{ $doc['party']->name }}</p>
            @if ($doc['party']->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $doc['party']->vat_number }}</p>@endif
            @if (!empty($doc['party']->email))<p class="text-sm text-slate-500">{{ $doc['party']->email }}</p>@endif
        </div>
        <div class="text-end">
            @if (!empty($doc['qr_code']))
                <img src="data:image/png;base64,{{ $doc['qr_code'] }}" alt="{{ __('ZATCA QR code') }}" class="ms-auto h-28 w-28" onerror="this.style.display='none'">
                <p class="mt-1 text-xs text-slate-400">{{ __('Scan to verify invoice details') }}</p>
            @endif
        </div>
    </div>

    <table class="w-full text-sm mt-8">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-200">
                <th class="py-2">{{ __('Description') }}</th>
                <th class="py-2 text-end">{{ __('Qty') }}</th>
                <th class="py-2 text-end">{{ __('Unit price') }}</th>
                <th class="py-2 text-end">{{ __('VAT') }}</th>
                <th class="py-2 text-end">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $line)
                <tr class="border-b border-slate-50">
                    <td class="py-2">{{ $line->description }}</td>
                    <td class="py-2 text-end">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="py-2 text-end">SAR {{ number_format($line->unit_price, 2) }}</td>
                    <td class="py-2 text-end">SAR {{ number_format($line->vat_amount, 2) }}</td>
                    <td class="py-2 text-end">SAR {{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-6 flex justify-end">
        <div class="w-full max-w-xs space-y-2 text-sm {{ $layout === 'boxed' && $accent ? 'rounded-lg p-4 text-white' : '' }}" @if ($layout === 'boxed' && $accent) style="background-color: {{ $accent }}" @endif>
            @php
                $boxed = $layout === 'boxed' && $accent;
            @endphp
            <div class="flex justify-between {{ $boxed ? 'text-white/80' : 'text-slate-500' }}"><span>{{ __('Subtotal') }}</span><span>SAR {{ number_format($doc['subtotal'], 2) }}</span></div>
            @if (($doc['discount_total'] ?? 0) > 0)
                <div class="flex justify-between {{ $boxed ? 'text-white/80' : 'text-slate-500' }}"><span>{{ __('Discount') }}</span><span>-SAR {{ number_format($doc['discount_total'], 2) }}</span></div>
            @endif
            <div class="flex justify-between {{ $boxed ? 'text-white/80' : 'text-slate-500' }}"><span>{{ __('VAT') }}</span><span>SAR {{ number_format($doc['vat_total'], 2) }}</span></div>
            <div class="flex justify-between font-bold text-base pt-2 border-t {{ $boxed ? 'border-white/30 text-white' : 'border-slate-200 text-slate-900' }}"><span>{{ __('Total') }}</span><span>SAR {{ number_format($doc['total'], 2) }}</span></div>
            @foreach ($doc['extra_rows'] ?? [] as $row)
                @php
                    $rowColor = 'text-slate-500';
                    if ($boxed) {
                        $rowColor = 'text-white/80';
                    } elseif (($row['variant'] ?? null) === 'red') {
                        $rowColor = 'font-semibold text-red-600';
                    } elseif ($row['emphasis'] ?? false) {
                        $rowColor = 'font-semibold text-brand-700';
                    }
                @endphp
                <div class="flex justify-between {{ $rowColor }}"><span>{{ $row['label'] }}</span><span>SAR {{ number_format($row['value'], 2) }}</span></div>
            @endforeach
        </div>
    </div>

    @if ($bankAccounts->isNotEmpty())
        @php
            $ba = $bankAccounts->first();
        @endphp
        <div class="mt-8 pt-4 border-t border-slate-100 text-sm">
            <h4 class="text-xs font-semibold uppercase text-slate-400 mb-1">{{ __('Payment details') }}</h4>
            <p class="text-slate-600">
                {{ $ba->name }}
                @if ($ba->bank_name) — {{ $ba->bank_name }} @endif
                @if ($ba->iban) — {{ __('IBAN') }}: {{ $ba->iban }} @endif
            </p>
        </div>
    @endif

    @if (!empty($doc['notes']))
        <div class="mt-8 pt-4 border-t border-slate-100 text-sm text-slate-500 whitespace-pre-line">{{ $doc['notes'] }}</div>
    @endif

    @if ($template && $template->notesFor(app()->getLocale()))
        <div class="mt-2 text-sm text-slate-400 whitespace-pre-line">{{ $template->notesFor(app()->getLocale()) }}</div>
    @endif
@endif
