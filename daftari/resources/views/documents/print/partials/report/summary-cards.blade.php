{{--
    2-4 colored summary cards. $cards: array of
    ['label_en' => ..., 'label_ar' => ... (optional), 'value' => formatted string, 'variant' => 'neutral'|'good'|'warn'|'bad'].
--}}
<table class="summary-table">
    <tr>
        @foreach ($cards as $card)
            <td style="width: {{ number_format(100 / count($cards), 2) }}%;">
                <div class="k">{{ $card['label_en'] }}</div>
                <div class="v v-{{ $card['variant'] ?? 'neutral' }}">{{ $card['value'] }}</div>
                @if (! empty($card['label_ar']))<div class="muted ar" style="margin-top: 2px;">{{ $card['label_ar'] }}</div>@endif
            </td>
        @endforeach
    </tr>
</table>
