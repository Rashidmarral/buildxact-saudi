{{--
    Hamly-parity type-color system, ported from the web reports (Trial
    Balance / Balance Sheet / Income Statement) to mPDF-safe CSS: plain
    hex colors and physical border-left (mPDF's CSS2.1-era engine does
    not support border-inline-start or Tailwind utility classes). One
    class pair per account type — "type-<key>" for a tinted row/section
    background with a left accent bar, "type-<key>-text" for colored
    text alone (e.g. a subtotal figure) — so any report PDF can
    @include this once and reuse the same palette.
--}}
.type-asset td { background: #eff6ff; }
.type-asset td:first-child { border-left: 3pt solid #2563eb; }
.type-asset-text { color: #1d4ed8; }
.type-liability td { background: #fffbeb; }
.type-liability td:first-child { border-left: 3pt solid #f59e0b; }
.type-liability-text { color: #b45309; }
.type-equity td { background: #f5f3ff; }
.type-equity td:first-child { border-left: 3pt solid #7c3aed; }
.type-equity-text { color: #6d28d9; }
.type-revenue td { background: #ecfdf5; }
.type-revenue td:first-child { border-left: 3pt solid #059669; }
.type-revenue-text { color: #047857; }
.type-expense td { background: #fff1f2; }
.type-expense td:first-child { border-left: 3pt solid #e11d48; }
.type-expense-text { color: #be123c; }
.type-fallback td { background: #f8fafc; }
.type-fallback td:first-child { border-left: 3pt solid #64748b; }
.type-fallback-text { color: #475569; }

{{-- Lighter tint for category-level rows nested under a type section --}}
.type-asset-soft td { background: #f7fafd; }
.type-liability-soft td { background: #fffdf7; }
.type-equity-soft td { background: #faf9fe; }
.type-revenue-soft td { background: #f6fdfa; }
.type-expense-soft td { background: #fff9fa; }
.type-fallback-soft td { background: #fcfcfd; }
