{{--
    Shared "modern branded report" look, lifted verbatim from
    client-statement-pdf.blade.php (navy header band, colored summary
    cards, period banner, underlined section titles, striped data
    tables, closing balance banner) so every report PDF below gets the
    same design instead of each hand-rolling its own plainer CSS.
    @include this inside a <style> tag. Variant classes on summary
    cards/banners are named by meaning (good/warn/bad/neutral), not by
    statement-specific labels, so this works for any report.
--}}
body { font-family: cairo, sans-serif; color: #1e293b; font-size: 9.5pt; }
table { border-collapse: collapse; width: 100%; }
.ar { direction: rtl; text-align: right; font-family: cairo, sans-serif; }
.muted { color: #94a3b8; font-size: 8pt; }
.header-band { background: #0f172a; color: #ffffff; padding: 16px 18px; }
.header-band td { vertical-align: top; }
.header-title { font-size: 18pt; font-weight: bold; }
.header-sub { color: #cbd5e1; font-size: 8.5pt; margin-top: 2px; }
.header-company { font-size: 10pt; color: #e2e8f0; margin-top: 6px; }
.meta-table { margin-top: 14px; }
.meta-table td { padding: 0 14px 0 0; }
.meta-k { color: #94a3b8; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; }
.meta-v { font-weight: bold; color: #0f172a; font-size: 9.5pt; margin-top: 2px; }
.party-box { margin-top: 14px; padding: 10px 0; border-top: 1pt solid #e2e8f0; border-bottom: 1pt solid #e2e8f0; }
.party-box .k { color: #64748b; font-size: 8pt; }
.party-box .v { font-weight: bold; color: #0f172a; font-size: 10pt; }
.summary-table { margin-top: 14px; }
.summary-table td { padding: 12px; border: 1pt solid #e2e8f0; text-align: center; background: #f0fdfa; }
.summary-table .k { color: #64748b; font-size: 7.5pt; text-transform: uppercase; }
.summary-table .v { font-size: 13pt; font-weight: bold; margin-top: 4px; }
.v-neutral { color: #0f172a; }
.v-good { color: #0f766e; }
.v-warn { color: #b45309; }
.v-bad { color: #b91c1c; }
.period-banner { background: #0f172a; color: #ffffff; padding: 7px 12px; margin-top: 14px; font-size: 8.5pt; font-weight: bold; }
.section-title { margin-top: 18px; padding-bottom: 4px; border-bottom: 1.5pt solid #0f172a; }
.section-title .en { font-size: 11pt; font-weight: bold; color: #0f172a; }
.section-title .ar { font-size: 10.5pt; font-weight: bold; color: #0f172a; }
.data-table { margin-top: 8px; }
.data-table th { background: #f1f5f9; color: #475569; font-size: 8pt; text-align: left; padding: 6px 8px; border-bottom: 1pt solid #cbd5e1; text-transform: uppercase; }
.data-table td { padding: 6px 8px; border-bottom: 0.5pt solid #e2e8f0; font-size: 9pt; }
.num { text-align: right; }
.total-row td { font-weight: bold; background: #f8fafc; border-top: 1pt solid #cbd5e1; }
.status-good { color: #0f766e; font-weight: bold; }
.status-warn { color: #b45309; font-weight: bold; }
.status-bad { color: #b91c1c; font-weight: bold; }
.closing-banner { margin-top: 16px; padding: 12px 16px; background: #fff7ed; border: 1pt solid #fed7aa; }
.closing-banner .k { color: #9a3412; font-size: 9pt; font-weight: bold; }
.closing-banner .sub { color: #c2703d; font-size: 7.5pt; margin-top: 2px; }
.closing-banner .v { color: #9a3412; font-size: 15pt; font-weight: bold; }
.closing-banner.variant-good { background: #f0fdfa; border-color: #99f6e4; }
.closing-banner.variant-good .k, .closing-banner.variant-good .v { color: #0f766e; }
.closing-banner.variant-good .sub { color: #0d9488; }
.footer-note { margin-top: 16px; color: #94a3b8; font-size: 7.5pt; border-top: 0.5pt solid #e2e8f0; padding-top: 8px; }
