{{--
    Browser-rendered (Tailwind) counterpart to pdf-terms-and-conditions
    — included once after documents.print.body's whole layout @if/
    @elseif chain, so it renders the same regardless of which template
    layout the document itself uses. $doc['terms_sections'] is an array
    of {title_en, title_ar, paragraphs: [{heading_en, heading_ar,
    body_en, body_ar}, ...]} — see Quotation::pdfData() for where it's
    built and QuotationController::normalizeTermsSections() for how
    empty sections/paragraphs are already stripped before this ever
    sees them.
--}}
@if (! empty($doc['terms_sections']))
    @php
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $toArabicNumber = fn (int $n) => strtr((string) $n, array_combine(range(0, 9), $arabicDigits));
    @endphp
    <div class="mt-10 print:break-before-page">
        <div class="bg-slate-900 rounded-t-xl px-6 py-4 flex items-center justify-between gap-4">
            <h3 class="text-white text-lg font-bold">{{ __('Terms and Conditions') }}</h3>
            <h3 class="text-white text-lg font-bold" dir="rtl">الشروط والأحكام</h3>
        </div>
        <div class="border border-t-0 border-slate-200 rounded-b-xl px-6 py-5 space-y-5">
            @foreach ($doc['terms_sections'] as $i => $section)
                @php $n = $i + 1; @endphp
                <div>
                    <div class="flex items-start justify-between gap-6 border-b border-slate-200 pb-1.5 mb-2.5">
                        <h4 class="font-bold text-slate-900">{{ $n }}. {{ $section['title_en'] }}</h4>
                        <h4 class="font-bold text-slate-900" dir="rtl">{{ $toArabicNumber($n) }}. {{ $section['title_ar'] }}</h4>
                    </div>
                    @foreach ($section['paragraphs'] ?? [] as $p)
                        <div class="grid sm:grid-cols-2 gap-6 mb-3 last:mb-0">
                            <div>
                                @if (! empty($p['heading_en']))<p class="font-semibold text-sm text-slate-700 mb-1">{{ $p['heading_en'] }}</p>@endif
                                <p class="text-sm text-slate-600 whitespace-pre-line leading-relaxed">{{ $p['body_en'] }}</p>
                            </div>
                            <div dir="rtl">
                                @if (! empty($p['heading_ar']))<p class="font-semibold text-sm text-slate-700 mb-1">{{ $p['heading_ar'] }}</p>@endif
                                <p class="text-sm text-slate-600 whitespace-pre-line leading-relaxed">{{ $p['body_ar'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endif
