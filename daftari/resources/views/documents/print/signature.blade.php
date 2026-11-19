{{--
    Shared signature block, included by each layout branch in
    documents/print/body.blade.php right before that layout's closing
    footer-info line — so the signature sits inside the document body,
    above the bottom-of-page strip, not below it. Relies on $template,
    $primary, $showEn, $showAr and $languageMode already being in scope
    (this is an @include, not a component, so the parent's variables carry
    over).
--}}
@if ($template && $template->show_signature)
    <div class="mt-14 flex justify-end">
        <div class="w-56 text-center">
            <div class="h-16 border-b border-slate-400"></div>
            <p class="mt-2 text-sm text-slate-600">
                @if ($showEn){{ $primary($template->signature_label_en ?: __('Authorized Signature'), $template->signature_label_ar) }}@endif
                @if ($showAr && $languageMode === 'bilingual' && $template->signature_label_ar)
                    <span dir="rtl" class="block text-xs text-slate-500">{{ $template->signature_label_ar }}</span>
                @endif
            </p>
        </div>
    </div>
@endif
