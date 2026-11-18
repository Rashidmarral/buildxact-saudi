{{--
    mPDF counterpart of documents.print.terms-and-conditions — included
    once after pdf.blade.php's whole layout @if/@elseif chain, so a
    "Terms and Conditions" page renders the same regardless of which
    template layout the document body itself uses. <pagebreak /> is
    mPDF's own reliable forced-break tag (CSS page-break-before is
    documented elsewhere in this file as unreliable after a table —
    see pdf.blade.php's own note on that). $doc['terms_sections'] is an
    array of {title_en, title_ar, paragraphs: [{heading_en, heading_ar,
    body_en, body_ar}, ...]}.
--}}
@if (! empty($doc['terms_sections']))
    @php
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $toArabicNumber = fn (int $n) => strtr((string) $n, array_combine(range(0, 9), $arabicDigits));
    @endphp
    <pagebreak />

    <table style="width: 100%;">
        <tr>
            <td style="background-color: #0f172a; padding: 14px 18px; width: 50%;">
                <span style="color: #ffffff; font-size: 15pt; font-weight: bold;">{{ __('Terms and Conditions') }}</span>
            </td>
            <td style="background-color: #0f172a; padding: 14px 18px; width: 50%; text-align: right;" class="ar">
                <span style="color: #ffffff; font-size: 15pt; font-weight: bold;">الشروط والأحكام</span>
            </td>
        </tr>
    </table>

    @foreach ($doc['terms_sections'] as $i => $section)
        @php $n = $i + 1; @endphp
        <table style="margin-top: {{ $i === 0 ? 16 : 14 }}px; border-bottom: 1pt solid #cbd5e1;">
            <tr>
                <td style="width: 50%; padding-bottom: 3px; font-weight: bold; font-size: 11pt; color: #0f172a;">{{ $n }}. {{ $section['title_en'] }}</td>
                <td style="width: 50%; padding-bottom: 3px; font-weight: bold; font-size: 11pt; color: #0f172a; text-align: right;" class="ar">{{ $toArabicNumber($n) }}. {{ $section['title_ar'] }}</td>
            </tr>
        </table>

        @foreach ($section['paragraphs'] ?? [] as $p)
            @if (! empty($p['heading_en']) || ! empty($p['heading_ar']))
                <table style="margin-top: 8px;">
                    <tr>
                        <td style="width: 50%; font-weight: bold; font-size: 9.5pt; color: #334155;">{{ $p['heading_en'] ?? '' }}</td>
                        <td style="width: 50%; font-weight: bold; font-size: 9.5pt; color: #334155; text-align: right;" class="ar">{{ $p['heading_ar'] ?? '' }}</td>
                    </tr>
                </table>
            @endif
            <table style="margin-top: 4px;">
                <tr>
                    <td style="width: 50%; font-size: 9.5pt; line-height: 1.5; vertical-align: top; padding-right: 10px;">{!! nl2br(e($p['body_en'] ?? '')) !!}</td>
                    <td style="width: 50%; font-size: 9.5pt; line-height: 1.7; vertical-align: top; text-align: right;" class="ar">{!! nl2br(e($p['body_ar'] ?? '')) !!}</td>
                </tr>
            </table>
        @endforeach
    @endforeach
@endif
