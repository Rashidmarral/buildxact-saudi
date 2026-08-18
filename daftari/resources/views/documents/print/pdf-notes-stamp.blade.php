{{-- Notes + template notes + company stamp, shared by all layouts that end this way. --}}
@if (!empty($doc['notes']))
    <div class="notes-block">
        <strong>{{ __('Notes') }}</strong>
        <div class="muted" style="margin-top: 2px; white-space: pre-line;">{{ $doc['notes'] }}</div>
    </div>
@endif

@if ($template && $template->notesFor(app()->getLocale()))
    <div class="muted" style="margin-top: 6px; font-size: 8.5pt; white-space: pre-line;">{{ $template->notesFor(app()->getLocale()) }}</div>
@endif

@if ($stampData)
    <table style="margin-top: 20px;">
        <tr>
            <td style="width: 70%;"></td>
            <td style="width: 30%; text-align: right;">
                <img src="{{ $stampData }}" class="stamp-img" style="width: {{ $stampSize ?? 90 }}px; height: {{ $stampSize ?? 90 }}px;" alt="">
            </td>
        </tr>
    </table>
@endif
